<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\Configurable;
interface MeterProviderInterface extends \CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterProviderInterface, Configurable
{
    public function shutdown(): bool;
    public function forceFlush(): bool;
}
