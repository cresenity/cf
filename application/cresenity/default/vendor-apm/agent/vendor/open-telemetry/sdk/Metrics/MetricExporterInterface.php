<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Data\Metric;
interface MetricExporterInterface
{
    /**
     * @param iterable<int, Metric> $batch
     */
    public function export(iterable $batch): bool;
    public function shutdown(): bool;
}
