<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Native;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config\AgentConfig;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Database\PdoAttributes;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\Clock;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\StatusCode;
use PDO;
use PDOStatement;
use Throwable;
/**
 * Bridges the `devcloud_apm` PHP extension's zend_observer hooks to this
 * package's tracer (SPEC §4/§67's "automatic instrumentation" path).
 *
 * With the extension loaded, an application needs no wrapper classes at all:
 * the engine itself reports every watched call, SQL text included, the way
 * New Relic's agent does. Without it, nothing here runs and the manual
 * integrations (`TracedPdo` and friends) remain the only path - so this is
 * purely additive, never required.
 *
 * Everything below is wrapped so a failure inside a hook can never surface in
 * the observed application (SPEC §7, §35): these callbacks run in the middle
 * of somebody else's `PDO::query()`, and an exception thrown from here would
 * come out of *their* call.
 */
final class NativeBridge
{
    /**
     * Guards against unbounded growth if an end hook never fires (a fatal
     * error mid-call, say) - far more spans than this open at once means
     * something is wrong, and dropping is better than growing forever.
     */
    private const MAX_OPEN_SPANS = 256;
    /** @var list<array{span: SpanInterface, attributes: array<string, bool|int|string|null>, startNanos: int}> */
    private static array $open = [];
    private static ?Tracer $tracer = null;
    private static ?Metrics $metrics = null;
    private static bool $registered = \false;
    public static function isAvailable(): bool
    {
        return extension_loaded('devcloud_apm') && function_exists('devcloud_apm_is_enabled') && devcloud_apm_is_enabled();
    }
    public static function register(AgentConfig $config, Tracer $tracer, ?Metrics $metrics = null): void
    {
        if (self::$registered || !self::isAvailable()) {
            return;
        }
        $watchList = self::watchList($config);
        if ($watchList === []) {
            return;
        }
        self::$registered = \true;
        self::$tracer = $tracer;
        self::$metrics = $metrics;
        self::$open = [];
        foreach ($watchList as $signature) {
            devcloud_apm_watch($signature);
        }
        devcloud_apm_set_hooks(static function (string $function, ?string $class, array $arguments, ?object $object): void {
            self::onBegin($function, $class, $arguments, $object);
        }, static function (string $function, ?string $class, array $arguments, ?object $object): void {
            self::onEnd();
        });
        // A span left open by an end hook that never fired would otherwise
        // never reach the exporter, which only ever sees ended spans.
        register_shutdown_function([self::class, 'endAllOpenSpans']);
    }
    /**
     * Only PDO so far. Each future group (curl/Guzzle/Redis) gets its own
     * config flag here rather than one blanket switch, so turning database
     * capture off cannot silently take outbound HTTP capture with it.
     *
     * @return list<string>
     */
    private static function watchList(AgentConfig $config): array
    {
        $signatures = [];
        if ($config->captureDatabase) {
            $signatures[] = 'PDO::query';
            $signatures[] = 'PDO::exec';
            $signatures[] = 'PDO::prepare';
            $signatures[] = 'PDOStatement::execute';
        }
        return $signatures;
    }
    /**
     * @param array<int, mixed> $arguments
     */
    private static function onBegin(string $function, ?string $class, array $arguments, ?object $object): void
    {
        try {
            if (self::$tracer === null || count(self::$open) >= self::MAX_OPEN_SPANS) {
                return;
            }
            $sql = self::resolveSql($function, $class, $arguments, $object);
            if ($sql === null) {
                return;
            }
            // The DSN is not reachable from a hooked call (PDO never exposes
            // it), so server.address/server.port are simply absent here -
            // forQuery() filters out the nulls. TracedPdo, which is handed the
            // DSN explicitly, still records them.
            $attributes = PdoAttributes::forQuery($sql, self::driverName($object), '', \true);
            $span = self::$tracer->startSpan(PdoAttributes::spanName((string) ($attributes['db.operation.name'] ?? '')), $attributes, SpanKind::KIND_CLIENT);
            self::$open[] = ['span' => $span, 'attributes' => $attributes, 'startNanos' => (new Clock())->nowNanos()];
        } catch (Throwable $exception) {
            // Deliberately silent: this runs inside the application's own call.
        }
    }
    private static function onEnd(): void
    {
        try {
            $entry = array_pop(self::$open);
            if ($entry === null) {
                return;
            }
            $durationMs = ((new Clock())->nowNanos() - $entry['startNanos']) / 1000000;
            PdoAttributes::recordMetrics(self::$metrics, $entry['attributes'], $durationMs);
            $entry['span']->end();
        } catch (Throwable $exception) {
            // Deliberately silent, same reason as onBegin().
        }
    }
    public static function endAllOpenSpans(): void
    {
        try {
            while (self::$open !== []) {
                $entry = array_pop(self::$open);
                $entry['span']->setStatus(StatusCode::STATUS_ERROR, 'span left open at shutdown');
                $entry['span']->end();
            }
        } catch (Throwable $exception) {
            // Deliberately silent, same reason as onBegin().
        }
    }
    /**
     * `PDOStatement::execute()` carries no SQL of its own - the text belongs
     * to the statement the call is made on.
     *
     * @param array<int, mixed> $arguments
     */
    private static function resolveSql(string $function, ?string $class, array $arguments, ?object $object): ?string
    {
        if ($class === 'PDO' && in_array($function, ['query', 'exec', 'prepare'], \true)) {
            $sql = $arguments[0] ?? null;
            return is_string($sql) ? $sql : null;
        }
        if ($class === 'PDOStatement' && $function === 'execute' && $object instanceof PDOStatement) {
            return $object->queryString;
        }
        return null;
    }
    private static function driverName(?object $object): string
    {
        try {
            if ($object instanceof PDO) {
                return (string) $object->getAttribute(PDO::ATTR_DRIVER_NAME);
            }
        } catch (Throwable $exception) {
            // A driver that refuses the attribute just leaves it unknown.
        }
        return 'other_sql';
    }
    /**
     * Test seam - the extension's own state is reset per request, this mirrors
     * that for a process running many tests in one PHP lifetime.
     */
    public static function reset(): void
    {
        self::$open = [];
        self::$tracer = null;
        self::$metrics = null;
        self::$registered = \false;
    }
}
/**
 * Bridges the `devcloud_apm` PHP extension's zend_observer hooks to this
 * package's tracer (SPEC §4/§67's "automatic instrumentation" path).
 *
 * With the extension loaded, an application needs no wrapper classes at all:
 * the engine itself reports every watched call, SQL text included, the way
 * New Relic's agent does. Without it, nothing here runs and the manual
 * integrations (`TracedPdo` and friends) remain the only path - so this is
 * purely additive, never required.
 *
 * Everything below is wrapped so a failure inside a hook can never surface in
 * the observed application (SPEC §7, §35): these callbacks run in the middle
 * of somebody else's `PDO::query()`, and an exception thrown from here would
 * come out of *their* call.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Native\NativeBridge', 'Cresenity\DevCloud\APM\Instrumentation\Native\NativeBridge', \false);
