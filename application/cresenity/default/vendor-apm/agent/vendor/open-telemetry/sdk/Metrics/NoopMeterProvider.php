<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\Noop\NoopMeter;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\Configurator;
class NoopMeterProvider implements MeterProviderInterface
{
    #[\Override]
    public function shutdown(): bool
    {
        return \true;
    }
    #[\Override]
    public function forceFlush(): bool
    {
        return \true;
    }
    #[\Override]
    public function getMeter(string $name, ?string $version = null, ?string $schemaUrl = null, iterable $attributes = []): MeterInterface
    {
        return new NoopMeter();
    }
    #[\Override]
    public function updateConfigurator(Configurator $configurator): void
    {
        // no-op
    }
}
