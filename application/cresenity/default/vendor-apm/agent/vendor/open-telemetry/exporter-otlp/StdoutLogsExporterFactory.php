<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Export\Stream\StreamTransportFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\LogRecordExporterFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
class StdoutLogsExporterFactory implements LogRecordExporterFactoryInterface
{
    #[\Override]
    public function create(): LogRecordExporterInterface
    {
        $transport = (new StreamTransportFactory())->create('php://stdout', ContentTypes::NDJSON);
        return new LogsExporter($transport);
    }
}
