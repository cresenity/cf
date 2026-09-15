<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\Noop;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigProperties;
class NoopConfigProperties implements ConfigProperties
{
    #[\Override]
    public function get(string $id): mixed
    {
        return null;
    }
}
