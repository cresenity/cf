<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;
interface PushMetricExporterInterface extends Metrics\MetricExporterInterface
{
    public function forceFlush(): bool;
}
