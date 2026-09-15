<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporter;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Export\InMemoryStorageManager;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporterInterface;
class InMemorySpanExporterFactory implements SpanExporterFactoryInterface
{
    #[\Override]
    public function create(): SpanExporterInterface
    {
        return new InMemoryExporter(InMemoryStorageManager::spans());
    }
}
