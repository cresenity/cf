<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObservableGaugeInterface;
/**
 * @internal
 */
final class ObservableGauge implements ObservableGaugeInterface, InstrumentHandle
{
    use ObservableInstrumentTrait;
}
