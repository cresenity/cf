<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\Exporter;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\LogRecordExporterFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry;
class ConsoleExporterFactory implements LogRecordExporterFactoryInterface
{
    #[\Override]
    public function create(): LogRecordExporterInterface
    {
        $transport = Registry::transportFactory('stream')->create('php://stdout', 'application/json');
        return new ConsoleExporter($transport);
    }
}
