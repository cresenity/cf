<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Native;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config\AgentConfig;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Error\ErrorRecorder;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Database\PdoAttributes;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Redis\RedisAttributes;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\Clock;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\StatusCode;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\HttpAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\ServerAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\UrlAttributes;
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
    /**
     * The phpredis commands worth a span. Not exhaustive on purpose - these
     * are the ones real applications actually spend time in, and an unlisted
     * command goes untraced rather than breaking.
     *
     * Blocking commands (blPop, brPop, subscribe) are deliberately absent: a
     * span covering a deliberate multi-second wait says nothing useful and
     * would dominate every trace it appears in.
     *
     * @var list<string>
     */
    private const REDIS_COMMANDS = ['get', 'set', 'setex', 'setnx', 'del', 'unlink', 'exists', 'expire', 'ttl', 'incr', 'incrBy', 'decr', 'decrBy', 'mget', 'mset', 'hGet', 'hSet', 'hSetNx', 'hGetAll', 'hDel', 'hMGet', 'hMSet', 'hIncrBy', 'lPush', 'rPush', 'lPop', 'rPop', 'lRange', 'lLen', 'lRem', 'sAdd', 'sRem', 'sMembers', 'sIsMember', 'sCard', 'zAdd', 'zRange', 'zRevRange', 'zRem', 'zCard', 'zScore', 'keys', 'scan', 'type', 'rename', 'eval', 'evalSha', 'publish', 'multi', 'exec', 'pipeline', 'flushDB', 'flushAll'];
    /** @var list<array{kind: string, span: SpanInterface, attributes: array<string, bool|int|string|null>, startNanos: int, handle: ?object}> */
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
        $captureErrors = $config->captureExceptions;
        if ($watchList === [] && !$captureErrors) {
            return;
        }
        self::$registered = \true;
        self::$tracer = $tracer;
        self::$metrics = $metrics;
        self::$open = [];
        if ($watchList !== []) {
            foreach ($watchList as $signature) {
                devcloud_apm_watch($signature);
            }
            devcloud_apm_set_hooks(static function (string $function, ?string $class, array $arguments, ?object $object): void {
                self::onBegin($function, $class, $arguments, $object);
            }, static function (string $function, ?string $class, array $arguments, ?object $object, ?Throwable $exception = null): void {
                self::onEnd($exception);
            });
        }
        if ($captureErrors) {
            // Engine-level diagnostics: warnings, failed includes, fatals -
            // none of which ever become a Throwable, so none of which an
            // exception handler can see.
            devcloud_apm_set_error_hook(static function (int $type, string $file, int $line, string $message): void {
                self::onError($type, $file, $line, $message);
            });
        }
        // A span left open by an end hook that never fired would otherwise
        // never reach the exporter, which only ever sees ended spans.
        register_shutdown_function([self::class, 'endAllOpenSpans']);
    }
    /**
     * Each group gets its own config flag rather than one blanket switch, so
     * turning database capture off cannot silently take outbound HTTP capture
     * with it.
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
        if ($config->captureRedis) {
            // phpredis resolves every command through its own concrete method
            // rather than __call(), so there is no single signature to watch -
            // it is one entry per command. This is the common set; anything
            // missing simply goes untraced rather than breaking, and the list
            // is matched case-insensitively so the declared spelling
            // (hSet/hset) does not matter.
            foreach (self::REDIS_COMMANDS as $command) {
                $signatures[] = 'Redis::' . $command;
            }
        }
        if ($config->captureHttp) {
            // curl_exec covers both bare curl and every library built on it
            // (Guzzle included) without any of them wiring anything up. The
            // Guzzle middleware remains for applications without the
            // extension, and for outgoing trace-context propagation, which
            // this cannot do - see beginHttp().
            $signatures[] = 'curl_exec';
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
            if ($class === null && $function === 'curl_exec') {
                self::beginHttp($arguments);
                return;
            }
            if ($class === 'Redis') {
                self::beginRedis($function, $object);
                return;
            }
            self::beginDatabase($function, $class, $arguments, $object);
        } catch (Throwable $exception) {
            // Deliberately silent: this runs inside the application's own call.
        }
    }
    /**
     * @param array<int, mixed> $arguments
     */
    private static function beginDatabase(string $function, ?string $class, array $arguments, ?object $object): void
    {
        $sql = self::resolveSql($function, $class, $arguments, $object);
        if ($sql === null) {
            return;
        }
        // The DSN is not reachable from a hooked call (PDO never exposes
        // it), so server.address/server.port are simply absent here -
        // forQuery() filters out the nulls. TracedPdo, which is handed the
        // DSN explicitly, still records them.
        $attributes = PdoAttributes::forQuery($sql, self::driverName($object), '', \true);
        $span = self::$tracer?->startSpan(PdoAttributes::spanName((string) ($attributes['db.operation.name'] ?? '')), $attributes, SpanKind::KIND_CLIENT);
        if ($span === null) {
            return;
        }
        self::$open[] = ['kind' => 'db', 'span' => $span, 'attributes' => $attributes, 'startNanos' => (new Clock())->nowNanos(), 'handle' => null];
    }
    /**
     * Arguments are deliberately not read here: a Redis command's arguments
     * are the key and, on a SET, the value itself - application data that has
     * no business leaving the process (SPEC §18). Only the command name goes
     * on the span, matching what TracedRedis records.
     */
    private static function beginRedis(string $command, ?object $object): void
    {
        if (!$object instanceof \Redis) {
            return;
        }
        $attributes = RedisAttributes::forCommand($command, $object);
        $span = self::$tracer?->startSpan(RedisAttributes::spanName($command), $attributes, SpanKind::KIND_CLIENT);
        if ($span === null) {
            return;
        }
        self::$open[] = ['kind' => 'redis', 'span' => $span, 'attributes' => $attributes, 'startNanos' => (new Clock())->nowNanos(), 'handle' => null];
    }
    /**
     * The URL is not an argument to curl_exec() - it was set on the handle,
     * possibly many lines earlier - so it comes from curl_getinfo(). Reading
     * the handle is safe here: curl_getinfo is not watched, and the hook's
     * own reentrancy guard keeps the observer off it either way.
     *
     * Note what this cannot do that the Guzzle middleware can: inject the
     * outgoing `traceparent` header. Adding headers here would mean
     * overwriting whatever the caller already set on the handle, which is not
     * a trade worth making silently. Distributed tracing across a curl call
     * still needs the middleware.
     *
     * @param array<int, mixed> $arguments
     */
    private static function beginHttp(array $arguments): void
    {
        $handle = $arguments[0] ?? null;
        if (!$handle instanceof \CurlHandle) {
            return;
        }
        $url = (string) (curl_getinfo($handle, \CURLINFO_EFFECTIVE_URL) ?: '');
        $host = $url !== '' ? parse_url($url, \PHP_URL_HOST) ?: null : null;
        $port = $url !== '' ? parse_url($url, \PHP_URL_PORT) ?: null : null;
        $attributes = array_filter([UrlAttributes::URL_FULL => $url !== '' ? $url : null, ServerAttributes::SERVER_ADDRESS => $host, ServerAttributes::SERVER_PORT => $port !== null ? (int) $port : null], static fn($value) => $value !== null);
        // No method: curl only reveals it through options that cannot be read
        // back, so the span is named for the host it talked to rather than
        // claiming a verb it does not know.
        $span = self::$tracer?->startSpan('HTTP ' . ($host ?? 'request'), $attributes, SpanKind::KIND_CLIENT);
        if ($span === null) {
            return;
        }
        self::$open[] = ['kind' => 'http', 'span' => $span, 'attributes' => $attributes, 'startNanos' => (new Clock())->nowNanos(), 'handle' => $handle];
    }
    /**
     * The exception is whatever the watched call is throwing right now - a
     * query that failed is exactly the span worth marking, and the engine
     * hands it over rather than leaving the span to look like it succeeded.
     */
    private static function onEnd(?Throwable $exception = null): void
    {
        try {
            $entry = array_pop(self::$open);
            if ($entry === null) {
                return;
            }
            if ($exception !== null) {
                $entry['span']->recordException($exception);
                $entry['span']->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            }
            $durationMs = ((new Clock())->nowNanos() - $entry['startNanos']) / 1000000;
            if ($entry['kind'] === 'http') {
                self::finishHttp($entry, $durationMs);
            } elseif ($entry['kind'] === 'redis') {
                self::$metrics?->count('redis.command.count', 1, $entry['attributes']);
                self::$metrics?->recordDuration('redis.command.duration', $durationMs, $entry['attributes']);
            } else {
                PdoAttributes::recordMetrics(self::$metrics, $entry['attributes'], $durationMs);
            }
            $entry['span']->end();
        } catch (Throwable $exception) {
            // Deliberately silent, same reason as onBegin().
        }
    }
    /**
     * curl reports the outcome on the handle, so the status code is only
     * available now, after the call returned. A code of 0 means the request
     * never completed at all (DNS failure, timeout, refused connection) -
     * worth marking as failed just as loudly as a 500.
     *
     * @param array{kind: string, span: SpanInterface, attributes: array<string, bool|int|string|null>, startNanos: int, handle: ?object} $entry
     */
    private static function finishHttp(array $entry, float $durationMs): void
    {
        $status = null;
        if ($entry['handle'] instanceof \CurlHandle) {
            $status = (int) curl_getinfo($entry['handle'], \CURLINFO_RESPONSE_CODE);
        }
        $metricAttributes = $entry['attributes'];
        // The full URL would carry ids and query strings into a metric label
        // and explode its cardinality (SPEC §57); the host alone is the part
        // worth grouping by.
        unset($metricAttributes[UrlAttributes::URL_FULL]);
        $failed = $status === null || $status === 0 || $status >= 400;
        if ($status !== null && $status !== 0) {
            $entry['span']->setAttribute(HttpAttributes::HTTP_RESPONSE_STATUS_CODE, $status);
            $metricAttributes[HttpAttributes::HTTP_RESPONSE_STATUS_CODE] = $status;
        }
        if ($failed) {
            $entry['span']->setStatus(StatusCode::STATUS_ERROR, $status === null || $status === 0 ? 'request did not complete' : 'HTTP ' . $status);
        }
        if (self::$metrics !== null) {
            self::$metrics->count('external.http.count', 1, $metricAttributes);
            self::$metrics->recordDuration('external.http.duration', $durationMs, $metricAttributes);
            if ($failed) {
                self::$metrics->count('external.http.errors', 1, $metricAttributes);
            }
        }
    }
    private static function onError(int $type, string $file, int $line, string $message): void
    {
        try {
            ErrorRecorder::record($type, $file, $line, $message);
        } catch (Throwable $exception) {
            // Deliberately silent, same reason as onBegin() - this runs in
            // the middle of the engine reporting somebody else's error.
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
