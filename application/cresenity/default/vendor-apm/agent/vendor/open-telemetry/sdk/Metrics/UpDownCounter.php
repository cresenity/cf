<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\UpDownCounterInterface;
/**
 * @internal
 */
final class UpDownCounter implements UpDownCounterInterface, InstrumentHandle
{
    use SynchronousInstrumentTrait {
        write as add;
    }
}
