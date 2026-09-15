<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObservableUpDownCounterInterface;
/**
 * @internal
 */
final class ObservableUpDownCounter implements ObservableUpDownCounterInterface, InstrumentHandle
{
    use ObservableInstrumentTrait;
}
