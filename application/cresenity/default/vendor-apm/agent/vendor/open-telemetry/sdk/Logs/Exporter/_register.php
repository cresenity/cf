<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor;

\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerLogRecordExporterFactory('console', \CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\Exporter\ConsoleExporterFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerLogRecordExporterFactory('memory', \CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\Exporter\InMemoryExporterFactory::class);
