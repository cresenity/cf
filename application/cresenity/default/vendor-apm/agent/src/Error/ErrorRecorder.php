<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Error;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Agent;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\Span as OtelSpan;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\StatusCode;
/**
 * Records a PHP diagnostic - a warning, a failed include, a fatal - onto the
 * current span as a `php.error` event.
 *
 * The counterpart to ExceptionRecorder, for everything that never becomes a
 * Throwable and so can never reach an exception handler. Only the
 * `devcloud_apm` extension's engine-level error hook can see these at the
 * moment they happen; without it the best available was reconstructing the
 * last one from `error_get_last()` at shutdown, which loses every non-fatal
 * and every fatal but the final one.
 */
final class ErrorRecorder
{
    /**
     * Levels that mean the request is not going to produce what it was asked
     * for, so the span's status should say so rather than only carrying an
     * event. A warning is worth recording but does not by itself make the
     * operation a failure.
     */
    private const FATAL_LEVELS = \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR;
    private const LEVEL_NAMES = [\E_ERROR => 'E_ERROR', \E_WARNING => 'E_WARNING', \E_PARSE => 'E_PARSE', \E_NOTICE => 'E_NOTICE', \E_CORE_ERROR => 'E_CORE_ERROR', \E_CORE_WARNING => 'E_CORE_WARNING', \E_COMPILE_ERROR => 'E_COMPILE_ERROR', \E_COMPILE_WARNING => 'E_COMPILE_WARNING', \E_USER_ERROR => 'E_USER_ERROR', \E_USER_WARNING => 'E_USER_WARNING', \E_USER_NOTICE => 'E_USER_NOTICE', \E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', \E_DEPRECATED => 'E_DEPRECATED', \E_USER_DEPRECATED => 'E_USER_DEPRECATED'];
    public static function record(int $type, string $file, int $line, string $message): void
    {
        $level = self::levelName($type);
        $span = OtelSpan::getCurrent();
        $span->addEvent('php.error', ['php.error.level' => $level, 'php.error.message' => $message, 'code.filepath' => $file, 'code.lineno' => $line]);
        if (($type & self::FATAL_LEVELS) !== 0) {
            $span->setStatus(StatusCode::STATUS_ERROR, $message);
        }
        // Level only, never the message: a metric label carrying arbitrary
        // error text would explode cardinality (SPEC §57), the same reason
        // db.query.text is stripped before recording query metrics.
        Agent::metrics()?->count('error.count', 1, ['php.error.level' => $level]);
    }
    private static function levelName(int $type): string
    {
        return self::LEVEL_NAMES[$type] ?? 'E_UNKNOWN_' . $type;
    }
}
/**
 * Records a PHP diagnostic - a warning, a failed include, a fatal - onto the
 * current span as a `php.error` event.
 *
 * The counterpart to ExceptionRecorder, for everything that never becomes a
 * Throwable and so can never reach an exception handler. Only the
 * `devcloud_apm` extension's engine-level error hook can see these at the
 * moment they happen; without it the best available was reconstructing the
 * last one from `error_get_last()` at shutdown, which loses every non-fatal
 * and every fatal but the final one.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Error\ErrorRecorder', 'Cresenity\DevCloud\APM\Error\ErrorRecorder', \false);
