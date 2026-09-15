<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor;

\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerMetricExporterFactory('memory', \CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporterFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerMetricExporterFactory('console', \CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporter\ConsoleMetricExporterFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerMetricExporterFactory('none', \CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporter\NoopMetricExporterFactory::class);
