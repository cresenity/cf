<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporter;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Export\InMemoryStorageManager;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporterFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporterInterface;
class InMemoryExporterFactory implements MetricExporterFactoryInterface
{
    #[\Override]
    public function create(): MetricExporterInterface
    {
        return new InMemoryExporter(InMemoryStorageManager::metrics());
    }
}
