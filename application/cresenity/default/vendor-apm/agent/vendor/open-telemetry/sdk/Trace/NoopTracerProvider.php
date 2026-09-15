<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace;

use CresenityDevCloudAPMVendor\OpenTelemetry\API;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Future\CancellationInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\Configurator;
class NoopTracerProvider extends API\Trace\NoopTracerProvider implements TracerProviderInterface
{
    #[\Override]
    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return \true;
    }
    #[\Override]
    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return \true;
    }
    #[\Override]
    public function updateConfigurator(Configurator $configurator): void
    {
    }
}
