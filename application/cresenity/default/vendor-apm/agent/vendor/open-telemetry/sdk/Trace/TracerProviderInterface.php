<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace as API;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Future\CancellationInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\Configurable;
interface TracerProviderInterface extends API\TracerProviderInterface, Configurable
{
    public function forceFlush(?CancellationInterface $cancellation = null): bool;
    public function shutdown(?CancellationInterface $cancellation = null): bool;
}
