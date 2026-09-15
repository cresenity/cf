<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\GaugeInterface;
/**
 * @internal
 */
final class Gauge implements GaugeInterface, InstrumentHandle
{
    use SynchronousInstrumentTrait {
        write as record;
    }
}
