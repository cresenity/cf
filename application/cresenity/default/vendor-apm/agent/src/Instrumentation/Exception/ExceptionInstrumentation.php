<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Exception;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Error\ExceptionRecorder;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\InstrumentationInterface;
use ErrorException;
use Throwable;
/**
 * Automatic capture of uncaught errors (SPEC §22, Goal #2). Two mechanisms,
 * because PHP splits fatal conditions across two APIs:
 *
 * - `set_exception_handler()` catches any uncaught `Throwable` - in PHP 8
 *   this already includes `TypeError`/`ArgumentCountError`/etc., not just
 *   `Exception` subclasses.
 * - A shutdown-function check of `error_get_last()` catches the remaining
 *   fatals that never become a `Throwable` at all (out-of-memory, a parse
 *   error, a core/compile error).
 *
 * Registered before `HttpInstrumentation` in the Bootstrap sequence so its
 * shutdown function runs first and can still record onto a span that
 * `HttpServerSpan::end()` (registered after it) has not closed yet -
 * shutdown functions fire in the order they were registered.
 */
final class ExceptionInstrumentation implements InstrumentationInterface
{
    private const FATAL_ERROR_TYPES = [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR];
    public function name(): string
    {
        return 'exception';
    }
    public function isAvailable(): bool
    {
        return \true;
    }
    public function register(): void
    {
        $previousHandler = set_exception_handler(static function (Throwable $exception) use (&$previousHandler): void {
            try {
                ExceptionRecorder::record($exception);
            } catch (Throwable $recordingFailure) {
                // Recording must never be the reason a request dies, and an
                // exception escaping here would be dispatched straight back
                // into this same handler.
            }
            if ($previousHandler !== null) {
                $previousHandler($exception);
                return;
            }
            // No previous handler: fall back to PHP's own default uncaught-exception
            // behavior (message + non-zero exit) rather than silently swallowing it -
            // the agent must never change what the application does (SPEC §7, §64).
            //
            // It must be set_exception_handler(null), not restore_exception_handler().
            // Restoring from *inside* the handler does not affect the dispatch in
            // progress: PHP re-invokes this same still-installed handler for the
            // exception thrown below, and again for the next one, until the process
            // dies with "Maximum call stack size reached. Infinite recursion?".
            // Every uncaught exception in an instrumented app hit that, replacing
            // the application's own error with a stack-overflow fatal.
            set_exception_handler(null);
            throw $exception;
        });
        register_shutdown_function([self::class, 'recordFatalErrorIfAny']);
    }
    public static function recordFatalErrorIfAny(): void
    {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], self::FATAL_ERROR_TYPES, \true)) {
            return;
        }
        ExceptionRecorder::record(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
    }
}
/**
 * Automatic capture of uncaught errors (SPEC §22, Goal #2). Two mechanisms,
 * because PHP splits fatal conditions across two APIs:
 *
 * - `set_exception_handler()` catches any uncaught `Throwable` - in PHP 8
 *   this already includes `TypeError`/`ArgumentCountError`/etc., not just
 *   `Exception` subclasses.
 * - A shutdown-function check of `error_get_last()` catches the remaining
 *   fatals that never become a `Throwable` at all (out-of-memory, a parse
 *   error, a core/compile error).
 *
 * Registered before `HttpInstrumentation` in the Bootstrap sequence so its
 * shutdown function runs first and can still record onto a span that
 * `HttpServerSpan::end()` (registered after it) has not closed yet -
 * shutdown functions fire in the order they were registered.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Exception\ExceptionInstrumentation', 'Cresenity\DevCloud\APM\Instrumentation\Exception\ExceptionInstrumentation', \false);
