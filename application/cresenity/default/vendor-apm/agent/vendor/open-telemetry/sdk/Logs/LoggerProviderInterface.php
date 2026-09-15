<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs as API;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\Configurable;
interface LoggerProviderInterface extends API\LoggerProviderInterface, Configurable
{
    public function shutdown(): bool;
    public function forceFlush(): bool;
}
