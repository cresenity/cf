<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor;

\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerSpanExporterFactory('console', \CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporterFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerSpanExporterFactory('memory', \CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporter\InMemorySpanExporterFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerTransportFactory('stream', \CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Export\Stream\StreamTransportFactory::class);
