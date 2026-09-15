<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObservableCounterInterface;
/**
 * @internal
 */
final class ObservableCounter implements ObservableCounterInterface, InstrumentHandle
{
    use ObservableInstrumentTrait;
}
