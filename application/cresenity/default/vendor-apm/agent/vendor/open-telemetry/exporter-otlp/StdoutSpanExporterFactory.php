<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Export\Stream\StreamTransportFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporter\SpanExporterFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporterInterface;
class StdoutSpanExporterFactory implements SpanExporterFactoryInterface
{
    #[\Override]
    public function create(): SpanExporterInterface
    {
        $transport = (new StreamTransportFactory())->create('php://stdout', ContentTypes::NDJSON);
        return new SpanExporter($transport);
    }
}
