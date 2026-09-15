<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config;

/**
 * Resolves AgentConfig following SPEC §9's precedence:
 * runtime/API overrides > application config > environment variables > defaults.
 *
 * "Application config" (a framework's own config file, e.g. Laravel's `config/devcloud-apm.php`)
 * is not read here — a framework integration package resolves that and passes it through
 * as `$applicationConfig`, keeping this core loader framework-agnostic (SPEC §2).
 */
final class ConfigLoader
{
    /**
     * @param array<string, mixed> $runtimeOverrides   highest precedence, e.g. Agent::start(overrides: [...])
     * @param array<string, mixed> $applicationConfig   from a framework integration's own config source
     */
    public static function load(array $runtimeOverrides = [], array $applicationConfig = [], ?EnvironmentConfig $environment = null): AgentConfig
    {
        $environment ??= EnvironmentConfig::fromEnvironment();
        $defaults = AgentConfig::disabled();
        $pick = static function (string $key) use ($runtimeOverrides, $applicationConfig) {
            if (array_key_exists($key, $runtimeOverrides)) {
                return $runtimeOverrides[$key];
            }
            if (array_key_exists($key, $applicationConfig)) {
                return $applicationConfig[$key];
            }
            return null;
        };
        return new AgentConfig(enabled: $pick('enabled') ?? $environment->getBool('ENABLED') ?? $defaults->enabled, serviceName: $pick('serviceName') ?? $environment->getString('SERVICE_NAME') ?? $defaults->serviceName, environment: $pick('environment') ?? $environment->getString('ENVIRONMENT') ?? $defaults->environment, serviceVersion: $pick('serviceVersion') ?? $environment->getString('SERVICE_VERSION') ?? $defaults->serviceVersion, otlpEndpoint: $pick('otlpEndpoint') ?? $environment->getString('OTLP_ENDPOINT') ?? $environment->getString('ENDPOINT') ?? $defaults->otlpEndpoint, metricsEndpoint: $pick('metricsEndpoint') ?? $environment->getString('METRICS_ENDPOINT') ?? $defaults->metricsEndpoint, apiKey: $pick('apiKey') ?? $environment->getString('API_KEY') ?? $defaults->apiKey, sampleRate: (float) ($pick('sampleRate') ?? $environment->getFloat('SAMPLE_RATE') ?? $defaults->sampleRate), captureExceptions: (bool) ($pick('captureExceptions') ?? $environment->getBool('CAPTURE_EXCEPTIONS') ?? $defaults->captureExceptions), captureDatabase: (bool) ($pick('captureDatabase') ?? $environment->getBool('CAPTURE_DATABASE') ?? $defaults->captureDatabase), captureRedis: (bool) ($pick('captureRedis') ?? $environment->getBool('CAPTURE_REDIS') ?? $defaults->captureRedis), captureHttp: (bool) ($pick('captureHttp') ?? $environment->getBool('CAPTURE_HTTP') ?? $defaults->captureHttp), captureCli: (bool) ($pick('captureCli') ?? $environment->getBool('CAPTURE_CLI') ?? $defaults->captureCli), debug: (bool) ($pick('debug') ?? $environment->getBool('DEBUG') ?? $defaults->debug), maxQueueSize: (int) ($pick('maxQueueSize') ?? $environment->getInt('MAX_QUEUE_SIZE') ?? $defaults->maxQueueSize), batchSize: (int) ($pick('batchSize') ?? $environment->getInt('BATCH_SIZE') ?? $defaults->batchSize), flushIntervalMs: (int) ($pick('flushIntervalMs') ?? $environment->getInt('FLUSH_INTERVAL') ?? $defaults->flushIntervalMs), redactHeaders: $pick('redactHeaders') ?? $environment->getList('REDACT_HEADERS') ?? $defaults->redactHeaders, resourceAttributes: (array) ($pick('resourceAttributes') ?? []));
    }
}
/**
 * Resolves AgentConfig following SPEC §9's precedence:
 * runtime/API overrides > application config > environment variables > defaults.
 *
 * "Application config" (a framework's own config file, e.g. Laravel's `config/devcloud-apm.php`)
 * is not read here — a framework integration package resolves that and passes it through
 * as `$applicationConfig`, keeping this core loader framework-agnostic (SPEC §2).
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config\ConfigLoader', 'Cresenity\DevCloud\APM\Config\ConfigLoader', \false);
