<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Export\Stream\StreamTransportFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporterFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporterInterface;
class StdoutMetricExporterFactory implements MetricExporterFactoryInterface
{
    #[\Override]
    public function create(): MetricExporterInterface
    {
        $transport = (new StreamTransportFactory())->create('php://stdout', ContentTypes::NDJSON);
        return new MetricExporter($transport);
    }
}
