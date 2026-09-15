<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Error\ExceptionRecorder;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanInterface;
use Throwable;
/**
 * Runs a callback with a span active as the current context, recording an exception and
 * marking the span as errored if the callback throws — then always ends the span and
 * detaches the scope, so a caller can never leak either by forgetting to (SPEC §7, §35:
 * telemetry bookkeeping must never itself become a source of failure or leaks).
 *
 * Delegates exception handling to `Error\ExceptionRecorder` rather than recording inline -
 * this is the path every `Tracer::trace()`/`Apm::trace()`/`QueueJob::run()` call goes
 * through, so it must count towards `exception.count` (SPEC §51) the same as automatic
 * uncaught-error capture does, not a separate untracked code path.
 */
final class ContextManager
{
    /**
     * @template T
     *
     * @param callable(SpanInterface): T $callback
     *
     * @return T
     */
    public static function runInSpan(SpanInterface $span, callable $callback): mixed
    {
        $scope = $span->activate();
        try {
            return $callback($span);
        } catch (Throwable $exception) {
            ExceptionRecorder::record($exception);
            throw $exception;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}
/**
 * Runs a callback with a span active as the current context, recording an exception and
 * marking the span as errored if the callback throws — then always ends the span and
 * detaches the scope, so a caller can never leak either by forgetting to (SPEC §7, §35:
 * telemetry bookkeeping must never itself become a source of failure or leaks).
 *
 * Delegates exception handling to `Error\ExceptionRecorder` rather than recording inline -
 * this is the path every `Tracer::trace()`/`Apm::trace()`/`QueueJob::run()` call goes
 * through, so it must count towards `exception.count` (SPEC §51) the same as automatic
 * uncaught-error capture does, not a separate untracked code path.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\ContextManager', 'Cresenity\DevCloud\APM\Tracing\ContextManager', \false);
