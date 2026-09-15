<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration;

interface ConfigProviderInterface
{
    public function getInstrumentationConfig(): ConfigProperties;
}
