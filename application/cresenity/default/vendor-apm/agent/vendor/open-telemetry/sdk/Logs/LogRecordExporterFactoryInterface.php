<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs;

interface LogRecordExporterFactoryInterface
{
    public function create(): LogRecordExporterInterface;
}
