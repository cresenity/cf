<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Redis;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Agent;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use Redis;
/**
 * Manual `ext-redis` (phpredis) integration (SPEC §18) - wrap an existing
 * `Redis` instance instead of extending it: phpredis's commands are all
 * concrete methods, not resolved through `__call()`, so extending the class
 * cannot intercept a generic command the way overriding a handful of PDO
 * entry points can. Composition + `__call()` proxying covers every command
 * (including ones added by future phpredis versions) without enumerating
 * them, at the cost of this class not being a drop-in `instanceof Redis`.
 *
 * Same rationale as TracedPdo (SPEC §4/§67) for being a manual fallback
 * rather than the ideal automatic path: that needs `ext-opentelemetry` +
 * `open-telemetry/opentelemetry-auto-redis`, not assumed installed.
 */
final class TracedRedis
{
    private readonly ?Tracer $tracer;
    public function __construct(private readonly Redis $redis, ?Tracer $tracer = null)
    {
        $this->tracer = $tracer ?? Agent::tracer();
    }
    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($this->tracer === null) {
            return $this->redis->{$name}(...$arguments);
        }
        return $this->tracer->trace(RedisAttributes::spanName($name), fn() => $this->redis->{$name}(...$arguments), RedisAttributes::forCommand($name, $this->redis), SpanKind::KIND_CLIENT);
    }
}
/**
 * Manual `ext-redis` (phpredis) integration (SPEC §18) - wrap an existing
 * `Redis` instance instead of extending it: phpredis's commands are all
 * concrete methods, not resolved through `__call()`, so extending the class
 * cannot intercept a generic command the way overriding a handful of PDO
 * entry points can. Composition + `__call()` proxying covers every command
 * (including ones added by future phpredis versions) without enumerating
 * them, at the cost of this class not being a drop-in `instanceof Redis`.
 *
 * Same rationale as TracedPdo (SPEC §4/§67) for being a manual fallback
 * rather than the ideal automatic path: that needs `ext-opentelemetry` +
 * `open-telemetry/opentelemetry-auto-redis`, not assumed installed.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Redis\TracedRedis', 'Cresenity\DevCloud\APM\Instrumentation\Redis\TracedRedis', \false);
