<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

/**
 * @internal
 */
interface InstrumentHandle
{
    public function getHandle(): Instrument;
}
