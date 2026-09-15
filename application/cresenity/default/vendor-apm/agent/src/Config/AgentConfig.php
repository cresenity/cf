<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config;

/**
 * Fully resolved, immutable agent configuration (SPEC §8-§10).
 * Built by ConfigLoader — never constructed directly outside this package.
 */
final class AgentConfig
{
    /**
     * @param list<string>          $redactHeaders
     * @param array<string, mixed>  $resourceAttributes extra `service.*`/custom resource attributes
     */
    public function __construct(public readonly bool $enabled, public readonly string $serviceName, public readonly string $environment, public readonly ?string $serviceVersion, public readonly ?string $otlpEndpoint, public readonly ?string $metricsEndpoint, public readonly ?string $apiKey, public readonly float $sampleRate, public readonly bool $captureExceptions, public readonly bool $captureDatabase, public readonly bool $captureRedis, public readonly bool $captureHttp, public readonly bool $captureCli, public readonly bool $debug, public readonly int $maxQueueSize, public readonly int $batchSize, public readonly int $flushIntervalMs, public readonly array $redactHeaders, public readonly array $resourceAttributes = [])
    {
    }
    /**
     * Config with the smallest possible footprint: disabled, no export target.
     * Used when the caller supplies neither environment variables nor overrides —
     * the agent must still construct cleanly and simply do nothing (SPEC §7, fail-open).
     */
    public static function disabled(): self
    {
        return new self(enabled: \false, serviceName: 'unknown-service', environment: 'production', serviceVersion: null, otlpEndpoint: null, metricsEndpoint: null, apiKey: null, sampleRate: 1.0, captureExceptions: \true, captureDatabase: \true, captureRedis: \true, captureHttp: \true, captureCli: \true, debug: \false, maxQueueSize: 8192, batchSize: 512, flushIntervalMs: 5000, redactHeaders: ['authorization', 'cookie', 'set-cookie', 'x-api-key']);
    }
}
/**
 * Fully resolved, immutable agent configuration (SPEC §8-§10).
 * Built by ConfigLoader — never constructed directly outside this package.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config\AgentConfig', 'Cresenity\DevCloud\APM\Config\AgentConfig', \false);
