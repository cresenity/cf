<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\Exporter;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Export\InMemoryStorageManager;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\LogRecordExporterFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
class InMemoryExporterFactory implements LogRecordExporterFactoryInterface
{
    #[\Override]
    public function create(): LogRecordExporterInterface
    {
        return new InMemoryExporter(InMemoryStorageManager::logs());
    }
}
