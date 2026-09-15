<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Bootstrap\Bootstrap;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config\AgentConfig;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config\ConfigLoader;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Native\NativeBridge;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\Logger;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\Psr\Log\LogLevel;
/**
 * Package entry point (SPEC §7, §28). One process-wide agent instance — application code
 * calls `Agent::start()` once, then uses `API\Apm` for everything else.
 */
final class Agent
{
    public const VERSION = '0.1.0';
    private static bool $started = \false;
    private static ?Tracer $tracer = null;
    private static ?Metrics $metrics = null;
    private static ?AgentConfig $config = null;
    /**
     * @param array<string, mixed> $overrides         highest-precedence runtime config (SPEC §9)
     * @param array<string, mixed> $applicationConfig  a framework integration's own resolved config
     */
    public static function start(array $overrides = [], array $applicationConfig = []): void
    {
        if (self::$started) {
            return;
        }
        self::$started = \true;
        self::$config = ConfigLoader::load($overrides, $applicationConfig);
        $logger = new Logger(self::$config->debug ? LogLevel::DEBUG : LogLevel::WARNING);
        $result = Bootstrap::boot(self::$config, $logger);
        self::$tracer = $result->tracer;
        self::$metrics = $result->metrics;
    }
    /**
     * Null when the agent was never started, or started but disabled/failed to initialize —
     * callers must treat null as "tracing is off", not as an error (SPEC §7, §35).
     */
    public static function tracer(): ?Tracer
    {
        return self::$tracer;
    }
    /**
     * Null when metrics were never configured (no metrics endpoint — SPEC §51 makes
     * metrics opt-in on top of tracing), or failed to initialize.
     */
    public static function metrics(): ?Metrics
    {
        return self::$metrics;
    }
    public static function config(): ?AgentConfig
    {
        return self::$config;
    }
    public static function isEnabled(): bool
    {
        return self::$tracer !== null;
    }
    /**
     * Test-only: undoes start() so a test suite can reconfigure and start again.
     */
    public static function reset(): void
    {
        self::$started = \false;
        self::$tracer = null;
        self::$metrics = null;
        self::$config = null;
        NativeBridge::reset();
    }
}
/**
 * Package entry point (SPEC §7, §28). One process-wide agent instance — application code
 * calls `Agent::start()` once, then uses `API\Apm` for everything else.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Agent', 'Cresenity\DevCloud\APM\Agent', \false);
