<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\Config;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\ConfigTrait;
class LoggerConfig implements Config
{
    use ConfigTrait;
    public static function default(): self
    {
        return new self();
    }
}
