<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Guzzle;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Agent;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Error\ExceptionRecorder;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\Clock;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\StatusCode;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ScopeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\HttpAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\ServerAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\UrlAttributes;
use CresenityDevCloudAPMVendor\Psr\Http\Message\RequestInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ResponseInterface;
use Throwable;
/**
 * Outbound HTTP instrumentation for Guzzle (SPEC §19), via its documented
 * middleware extension point rather than any call-site change - add it once
 * to a client's HandlerStack and every request/response the client makes is
 * traced, including outgoing W3C trace context propagation (SPEC §20) and
 * `external.http.count`/`external.http.duration` metrics (SPEC §51).
 *
 * Deliberately does not capture request/response bodies (SPEC §19, §33-§34),
 * and never puts the full URL into a metric attribute (only the host) to
 * avoid unbounded cardinality on the metrics backend (SPEC §57) - the full
 * `url.full` stays a span-only attribute.
 */
final class TracingMiddleware
{
    public static function create(?Tracer $tracer = null, ?Metrics $metrics = null): callable
    {
        return static function (callable $handler) use ($tracer, $metrics): callable {
            return static function (RequestInterface $request, array $options) use ($handler, $tracer, $metrics) {
                $tracer ??= Agent::tracer();
                if ($tracer === null) {
                    return $handler($request, $options);
                }
                $metrics ??= Agent::metrics();
                $uri = $request->getUri();
                $spanAttributes = array_filter([HttpAttributes::HTTP_REQUEST_METHOD => $request->getMethod(), UrlAttributes::URL_FULL => (string) $uri, ServerAttributes::SERVER_ADDRESS => $uri->getHost() !== '' ? $uri->getHost() : null, ServerAttributes::SERVER_PORT => $uri->getPort()], static fn($value) => $value !== null);
                $span = $tracer->startSpan($request->getMethod() . ' ' . $uri->getHost(), $spanAttributes, SpanKind::KIND_CLIENT);
                $scope = $span->activate();
                $carrier = [];
                TraceContextPropagator::getInstance()->inject($carrier);
                foreach ($carrier as $name => $value) {
                    $request = $request->withHeader($name, $value);
                }
                $metricAttributes = array_filter([HttpAttributes::HTTP_REQUEST_METHOD => $spanAttributes[HttpAttributes::HTTP_REQUEST_METHOD], ServerAttributes::SERVER_ADDRESS => $spanAttributes[ServerAttributes::SERVER_ADDRESS] ?? null], static fn($value) => $value !== null);
                $start = (new Clock())->nowNanos();
                return $handler($request, $options)->then(static function (ResponseInterface $response) use ($span, $scope, $metrics, $metricAttributes, $start) {
                    self::finish($span, $scope, $metrics, $metricAttributes, $start, $response);
                    return $response;
                }, static function (Throwable $reason) use ($span, $scope, $metrics, $metricAttributes, $start) {
                    self::finish($span, $scope, $metrics, $metricAttributes, $start, null, $reason);
                    throw $reason;
                });
            };
        };
    }
    /**
     * @param array<string, bool|int|float|string|null> $metricAttributes
     */
    private static function finish(SpanInterface $span, ScopeInterface $scope, ?Metrics $metrics, array $metricAttributes, int $startNanos, ?ResponseInterface $response, ?Throwable $reason = null): void
    {
        $isError = $reason !== null;
        if ($response !== null) {
            $span->setAttribute(HttpAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
            $metricAttributes[HttpAttributes::HTTP_RESPONSE_STATUS_CODE] = $response->getStatusCode();
            $isError = $response->getStatusCode() >= 500;
            if ($isError) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            }
        }
        if ($reason !== null) {
            ExceptionRecorder::record($reason);
        }
        if ($metrics !== null) {
            $durationMs = ((new Clock())->nowNanos() - $startNanos) / 1000000;
            $metrics->count('external.http.count', 1, $metricAttributes);
            $metrics->recordDuration('external.http.duration', $durationMs, $metricAttributes);
            if ($isError) {
                $metrics->count('external.http.errors', 1, $metricAttributes);
            }
        }
        $span->end();
        $scope->detach();
    }
}
/**
 * Outbound HTTP instrumentation for Guzzle (SPEC §19), via its documented
 * middleware extension point rather than any call-site change - add it once
 * to a client's HandlerStack and every request/response the client makes is
 * traced, including outgoing W3C trace context propagation (SPEC §20) and
 * `external.http.count`/`external.http.duration` metrics (SPEC §51).
 *
 * Deliberately does not capture request/response bodies (SPEC §19, §33-§34),
 * and never puts the full URL into a metric attribute (only the host) to
 * avoid unbounded cardinality on the metrics backend (SPEC §57) - the full
 * `url.full` stays a span-only attribute.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Guzzle\TracingMiddleware', 'Cresenity\DevCloud\APM\Instrumentation\Guzzle\TracingMiddleware', \false);
