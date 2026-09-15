<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\HistogramInterface;
/**
 * @internal
 */
final class Histogram implements HistogramInterface, InstrumentHandle
{
    use SynchronousInstrumentTrait {
        write as record;
    }
}
