<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor;

\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerSpanExporterFactory('otlp', \CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\SpanExporterFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerSpanExporterFactory('otlp/stdout', \CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\StdoutSpanExporterFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerMetricExporterFactory('otlp', \CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\MetricExporterFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerMetricExporterFactory('otlp/stdout', \CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\StdoutMetricExporterFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerTransportFactory('http', \CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerLogRecordExporterFactory('otlp', \CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\LogsExporterFactory::class);
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerLogRecordExporterFactory('otlp/stdout', \CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\StdoutLogsExporterFactory::class);
