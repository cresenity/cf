<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Cli;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ScopeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Incubating\Attributes\ProcessIncubatingAttributes;
/**
 * The root span for a CLI script run (SPEC §30) - a `php artisan some:command`
 * invocation, a cron script, a one-shot console tool. One per process, same
 * shape as `HttpServerSpan` for the web SAPI: started once, ended via a
 * shutdown function so it always closes even on a fatal error.
 *
 * Deliberately does not check `error_get_last()` itself - `ExceptionInstrumentation`
 * already owns that (works the same for CLI and web) and is registered before this
 * class in Bootstrap specifically so its shutdown function runs first and can still
 * record onto this span before it closes.
 *
 * Not for a long-running worker loop processing many jobs in one process - that
 * is `Instrumentation\Queue\QueueJob`, one span per job. This is for the CLI
 * process itself: a worker's outer loop could reasonably use both, one root CLI
 * span for the whole daemon's lifetime and one QueueJob span per job inside it.
 */
final class CliCommandSpan
{
    private static ?SpanInterface $span = null;
    private static ?ScopeInterface $scope = null;
    public static function start(Tracer $tracer): void
    {
        if (self::$span !== null) {
            return;
        }
        $args = self::argv();
        $script = isset($args[0]) ? basename($args[0]) : 'cli';
        $pid = getmypid();
        self::$span = $tracer->startSpan($script, array_filter([ProcessIncubatingAttributes::PROCESS_COMMAND => $args[0] ?? null, ProcessIncubatingAttributes::PROCESS_COMMAND_ARGS => array_slice($args, 1) ?: null, ProcessIncubatingAttributes::PROCESS_PID => $pid !== \false ? $pid : null], static fn($value) => $value !== null), SpanKind::KIND_INTERNAL);
        self::$scope = self::$span->activate();
        register_shutdown_function([self::class, 'end']);
    }
    public static function end(): void
    {
        if (self::$span === null) {
            return;
        }
        self::$span->end();
        self::$scope->detach();
        self::$span = null;
        self::$scope = null;
    }
    /**
     * @return list<string>
     */
    private static function argv(): array
    {
        global $argv;
        return is_array($argv) ? array_values($argv) : [];
    }
}
/**
 * The root span for a CLI script run (SPEC §30) - a `php artisan some:command`
 * invocation, a cron script, a one-shot console tool. One per process, same
 * shape as `HttpServerSpan` for the web SAPI: started once, ended via a
 * shutdown function so it always closes even on a fatal error.
 *
 * Deliberately does not check `error_get_last()` itself - `ExceptionInstrumentation`
 * already owns that (works the same for CLI and web) and is registered before this
 * class in Bootstrap specifically so its shutdown function runs first and can still
 * record onto this span before it closes.
 *
 * Not for a long-running worker loop processing many jobs in one process - that
 * is `Instrumentation\Queue\QueueJob`, one span per job. This is for the CLI
 * process itself: a worker's outer loop could reasonably use both, one root CLI
 * span for the whole daemon's lifetime and one QueueJob span per job inside it.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Cli\CliCommandSpan', 'Cresenity\DevCloud\APM\Instrumentation\Cli\CliCommandSpan', \false);
