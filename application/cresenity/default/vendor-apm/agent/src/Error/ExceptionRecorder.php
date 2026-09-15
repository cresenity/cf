<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Error;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Agent;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\Span as OtelSpan;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\StatusCode;
use Throwable;
/**
 * Records a Throwable onto the current span (SPEC §22): an `exception` event
 * carrying `exception.type`/`exception.message`/`exception.stacktrace` (via
 * the OTel API's own `recordException()` - not reimplemented here) plus the
 * span status, which `recordException()` alone does not set. Also increments
 * the `exception.count` metric (SPEC §51) when metrics are configured.
 *
 * The single place manual capture (`API\Apm::captureException()`), automatic
 * capture (`Instrumentation\Exception\ExceptionInstrumentation`), and every
 * `Tracer::trace()` call's own failure path (`Tracing\ContextManager`) all go
 * through, so none of them can drift apart from the others.
 */
final class ExceptionRecorder
{
    public static function record(Throwable $exception): void
    {
        $span = OtelSpan::getCurrent();
        $span->recordException($exception);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        Agent::metrics()?->count('exception.count', 1, ['exception.type' => $exception::class]);
    }
}
/**
 * Records a Throwable onto the current span (SPEC §22): an `exception` event
 * carrying `exception.type`/`exception.message`/`exception.stacktrace` (via
 * the OTel API's own `recordException()` - not reimplemented here) plus the
 * span status, which `recordException()` alone does not set. Also increments
 * the `exception.count` metric (SPEC §51) when metrics are configured.
 *
 * The single place manual capture (`API\Apm::captureException()`), automatic
 * capture (`Instrumentation\Exception\ExceptionInstrumentation`), and every
 * `Tracer::trace()` call's own failure path (`Tracing\ContextManager`) all go
 * through, so none of them can drift apart from the others.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Error\ExceptionRecorder', 'Cresenity\DevCloud\APM\Error\ExceptionRecorder', \false);
