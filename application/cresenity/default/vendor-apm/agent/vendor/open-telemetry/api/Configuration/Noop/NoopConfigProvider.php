<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\Noop;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigProperties;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigProviderInterface;
class NoopConfigProvider implements ConfigProviderInterface
{
    #[\Override]
    public function getInstrumentationConfig(): ConfigProperties
    {
        return new NoopConfigProperties();
    }
}
