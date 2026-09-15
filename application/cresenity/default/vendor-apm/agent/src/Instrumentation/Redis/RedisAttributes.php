<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Redis;

use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\DbAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\ServerAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Incubating\Attributes\DbIncubatingAttributes;
use Redis;
use Throwable;
/**
 * Builds span name/attributes for a Redis command (SPEC §18). Never includes
 * the command's arguments (the key, and especially the value on a SET-style
 * command) - only the command name itself, matching SPEC §18's
 * `capture_redis_keys=false` default posture. There is no config toggle for
 * this yet (a known, deliberate gap, not an oversight).
 */
final class RedisAttributes
{
    /**
     * @return array<string, bool|int|string|null>
     */
    public static function forCommand(string $command, Redis $redis): array
    {
        [$host, $port] = self::connectionInfo($redis);
        return array_filter([DbAttributes::DB_SYSTEM_NAME => DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_REDIS, DbAttributes::DB_OPERATION_NAME => strtoupper($command), ServerAttributes::SERVER_ADDRESS => $host, ServerAttributes::SERVER_PORT => $port], static fn($value) => $value !== null);
    }
    public static function spanName(string $command): string
    {
        return 'Redis ' . strtoupper($command);
    }
    /**
     * @return array{0: string|null, 1: int|null}
     */
    private static function connectionInfo(Redis $redis): array
    {
        try {
            return [$redis->getHost(), $redis->getPort()];
        } catch (Throwable) {
            // Not connected yet, or a Redis build without these accessors -
            // attributes are best-effort, never worth failing the command over.
            return [null, null];
        }
    }
}
/**
 * Builds span name/attributes for a Redis command (SPEC §18). Never includes
 * the command's arguments (the key, and especially the value on a SET-style
 * command) - only the command name itself, matching SPEC §18's
 * `capture_redis_keys=false` default posture. There is no config toggle for
 * this yet (a known, deliberate gap, not an oversight).
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Redis\RedisAttributes', 'Cresenity\DevCloud\APM\Instrumentation\Redis\RedisAttributes', \false);
