<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\Config;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\ConfigTrait;
class MeterConfig implements Config
{
    use ConfigTrait;
    public static function default(): self
    {
        return new self();
    }
}
