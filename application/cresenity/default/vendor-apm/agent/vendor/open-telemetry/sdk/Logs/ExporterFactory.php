<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs;

use InvalidArgumentException;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Configuration;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Variables;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\Exporter\NoopExporter;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry;
class ExporterFactory
{
    public function create(): LogRecordExporterInterface
    {
        $exporters = Configuration::getList(Variables::OTEL_LOGS_EXPORTER);
        if (1 !== count($exporters)) {
            throw new InvalidArgumentException(sprintf('Configuration %s requires exactly 1 exporter', Variables::OTEL_LOGS_EXPORTER));
        }
        $exporter = $exporters[0];
        if ($exporter === 'none') {
            return new NoopExporter();
        }
        $factory = Registry::logRecordExporterFactory($exporter);
        return $factory->create();
    }
}
