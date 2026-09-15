<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\Configuration\General\ConfigEnv;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoader;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoaderRegistry;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvResolver;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation\GeneralInstrumentationConfiguration;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\Configuration\General\PeerConfig;
/**
 * @implements EnvComponentLoader<GeneralInstrumentationConfiguration>
 */
final class EnvComponentLoaderPeerConfig implements EnvComponentLoader
{
    #[\Override]
    public function load(EnvResolver $env, EnvComponentLoaderRegistry $registry, Context $context): GeneralInstrumentationConfiguration
    {
        return new PeerConfig([]);
    }
    #[\Override]
    public function name(): string
    {
        return PeerConfig::class;
    }
}
