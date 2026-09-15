<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\API;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Agent;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Error\ExceptionRecorder;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\SpanContext;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\Span as OtelSpan;
use Throwable;
/**
 * The public developer-facing manual instrumentation API (SPEC §28-§29). This is the only
 * surface application code is meant to call directly — everything is a no-op when the agent
 * was never started or failed to initialize (SPEC §7, fail-open).
 */
final class Apm
{
    /**
     * @param array<string, bool|int|float|string|null> $attributes
     */
    public static function startSpan(string $name, array $attributes = []): Span
    {
        $tracer = Agent::tracer();
        if ($tracer === null) {
            return Span::noop();
        }
        return Span::wrap($tracer->startSpan($name, $attributes));
    }
    /**
     * @template T
     *
     * @param callable(): T                              $callback
     * @param array<string, bool|int|float|string|null> $attributes
     *
     * @return T
     */
    public static function trace(string $name, callable $callback, array $attributes = []): mixed
    {
        $tracer = Agent::tracer();
        if ($tracer === null) {
            return $callback();
        }
        return $tracer->trace($name, static fn() => $callback(), $attributes);
    }
    public static function setAttribute(string $key, bool|int|float|string|null $value): void
    {
        OtelSpan::getCurrent()->setAttribute($key, $value);
    }
    /**
     * @param array<string, bool|int|float|string|null> $attributes
     */
    public static function addEvent(string $name, array $attributes = []): void
    {
        OtelSpan::getCurrent()->addEvent($name, $attributes);
    }
    /**
     * Records an exception onto the current span without ending it — for an exception the
     * caller catches and handles itself but still wants visible on the trace (SPEC §22, §29).
     */
    public static function captureException(Throwable $exception): void
    {
        ExceptionRecorder::record($exception);
    }
    /**
     * The current trace/span id, for correlating an application's own log lines with a
     * trace (SPEC §32) - deliberately not a logging integration itself. Null when there is
     * no active span (agent disabled, or called outside any traced call).
     *
     * Example: `$logger->info('...', ['trace_id' => Apm::currentTraceContext()?->traceId]);`
     */
    public static function currentTraceContext(): ?SpanContext
    {
        $context = OtelSpan::getCurrent()->getContext();
        if (!$context->isValid()) {
            return null;
        }
        return SpanContext::fromOtel($context);
    }
}
/**
 * The public developer-facing manual instrumentation API (SPEC §28-§29). This is the only
 * surface application code is meant to call directly — everything is a no-op when the agent
 * was never started or failed to initialize (SPEC §7, fail-open).
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\API\Apm', 'Cresenity\DevCloud\APM\API\Apm', \false);
