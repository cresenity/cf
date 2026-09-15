<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Http;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\Clock;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\StatusCode;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ScopeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\ClientAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\HttpAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\ServerAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\UrlAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\UserAgentAttributes;
/**
 * The incoming-request server span (SPEC §11). One per PHP process: `start()`
 * extracts W3C trace context from the incoming request and opens the span,
 * `end()` (registered as a shutdown function) closes it with the final
 * response status - the only two calls a plain PHP entry point needs.
 *
 * Also records the `request.count`/`request.duration`/`request.errors` metrics
 * (SPEC §51) when metrics are configured - the same request attributes (method,
 * status code) go onto both the span and the metric data points.
 *
 * Deliberately does not capture request/response headers or bodies by
 * default (SPEC §11, §33-§34) - only the well-known, low-cardinality
 * attributes below.
 */
final class HttpServerSpan
{
    private static ?SpanInterface $span = null;
    private static ?ScopeInterface $parentScope = null;
    private static ?ScopeInterface $spanScope = null;
    private static ?Metrics $metrics = null;
    private static ?int $startNanos = null;
    /** @var array<string, bool|int|float|string|null> */
    private static array $metricAttributes = [];
    public static function start(Tracer $tracer, ?Metrics $metrics = null): void
    {
        if (self::$span !== null) {
            return;
        }
        $extractedContext = TraceContextPropagator::getInstance()->extract(self::collectHeaders());
        self::$parentScope = $extractedContext->activate();
        $attributes = self::requestAttributes();
        self::$span = $tracer->startSpan(self::spanName(), $attributes, SpanKind::KIND_SERVER);
        self::$spanScope = self::$span->activate();
        self::$metrics = $metrics;
        self::$startNanos = (new Clock())->nowNanos();
        self::$metricAttributes = array_filter([HttpAttributes::HTTP_REQUEST_METHOD => $attributes[HttpAttributes::HTTP_REQUEST_METHOD] ?? null], static fn($value) => $value !== null);
        register_shutdown_function([self::class, 'end']);
    }
    public static function end(): void
    {
        if (self::$span === null) {
            return;
        }
        $statusCode = http_response_code();
        $isError = \false;
        if ($statusCode !== \false) {
            self::$span->setAttribute(HttpAttributes::HTTP_RESPONSE_STATUS_CODE, $statusCode);
            self::$metricAttributes[HttpAttributes::HTTP_RESPONSE_STATUS_CODE] = $statusCode;
            $isError = $statusCode >= 500;
            if ($isError) {
                self::$span->setStatus(StatusCode::STATUS_ERROR);
            }
        }
        if (self::$metrics !== null && self::$startNanos !== null) {
            $durationMs = ((new Clock())->nowNanos() - self::$startNanos) / 1000000;
            self::$metrics->count('request.count', 1, self::$metricAttributes);
            self::$metrics->recordDuration('request.duration', $durationMs, self::$metricAttributes);
            if ($isError) {
                self::$metrics->count('request.errors', 1, self::$metricAttributes);
            }
        }
        self::$span->end();
        self::$spanScope->detach();
        self::$parentScope->detach();
        self::$span = null;
        self::$spanScope = null;
        self::$parentScope = null;
        self::$metrics = null;
        self::$startNanos = null;
        self::$metricAttributes = [];
    }
    /**
     * `{METHOD} {PATH}` - high-cardinality until a framework integration
     * overrides it with a normalized route (SPEC §12). Generic instrumentation
     * has no router to ask, so this is the honest fallback, not a shortcut.
     */
    private static function spanName(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?: '/';
        return $method . ' ' . $path;
    }
    /**
     * @return array<string, bool|int|float|string|null>
     */
    private static function requestAttributes(): array
    {
        $host = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? null;
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        return array_filter([HttpAttributes::HTTP_REQUEST_METHOD => $_SERVER['REQUEST_METHOD'] ?? null, UrlAttributes::URL_FULL => $host !== null ? $scheme . '://' . $host . $path : null, ServerAttributes::SERVER_ADDRESS => $host, ServerAttributes::SERVER_PORT => isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : null, UserAgentAttributes::USER_AGENT_ORIGINAL => $_SERVER['HTTP_USER_AGENT'] ?? null, ClientAttributes::CLIENT_ADDRESS => $_SERVER['REMOTE_ADDR'] ?? null], static fn($value) => $value !== null);
    }
    /**
     * @return array<string, string>
     */
    private static function collectHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if ($headers !== \false) {
                return $headers;
            }
        }
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') && is_string($value)) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
}
/**
 * The incoming-request server span (SPEC §11). One per PHP process: `start()`
 * extracts W3C trace context from the incoming request and opens the span,
 * `end()` (registered as a shutdown function) closes it with the final
 * response status - the only two calls a plain PHP entry point needs.
 *
 * Also records the `request.count`/`request.duration`/`request.errors` metrics
 * (SPEC §51) when metrics are configured - the same request attributes (method,
 * status code) go onto both the span and the metric data points.
 *
 * Deliberately does not capture request/response headers or bodies by
 * default (SPEC §11, §33-§34) - only the well-known, low-cardinality
 * attributes below.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Http\HttpServerSpan', 'Cresenity\DevCloud\APM\Instrumentation\Http\HttpServerSpan', \false);
