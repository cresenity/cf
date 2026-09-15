<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\CounterInterface;
/**
 * @internal
 */
final class Counter implements CounterInterface, InstrumentHandle
{
    use SynchronousInstrumentTrait {
        write as add;
    }
}
