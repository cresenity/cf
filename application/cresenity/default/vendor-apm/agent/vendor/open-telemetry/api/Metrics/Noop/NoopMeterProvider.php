<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\Noop;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterProviderInterface;
final class NoopMeterProvider implements MeterProviderInterface
{
    #[\Override]
    public function getMeter(string $name, ?string $version = null, ?string $schemaUrl = null, iterable $attributes = []): MeterInterface
    {
        return new NoopMeter();
    }
}
