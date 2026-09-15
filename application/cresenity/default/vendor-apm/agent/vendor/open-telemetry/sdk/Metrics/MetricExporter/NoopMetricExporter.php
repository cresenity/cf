<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporter;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporterInterface;
class NoopMetricExporter implements MetricExporterInterface
{
    /**
     * @inheritDoc
     */
    #[\Override]
    public function export(iterable $batch): bool
    {
        return \true;
    }
    #[\Override]
    public function shutdown(): bool
    {
        return \true;
    }
}
