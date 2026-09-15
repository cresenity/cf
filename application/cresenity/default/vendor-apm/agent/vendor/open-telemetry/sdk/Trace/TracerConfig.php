<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\Config;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\ConfigTrait;
class TracerConfig implements Config
{
    use ConfigTrait;
    public static function default(): self
    {
        return new self();
    }
}
