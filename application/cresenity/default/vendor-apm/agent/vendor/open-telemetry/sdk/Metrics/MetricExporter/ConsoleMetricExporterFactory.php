<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporter;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporterFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporterInterface;
class ConsoleMetricExporterFactory implements MetricExporterFactoryInterface
{
    #[\Override]
    public function create(): MetricExporterInterface
    {
        return new ConsoleMetricExporter();
    }
}
