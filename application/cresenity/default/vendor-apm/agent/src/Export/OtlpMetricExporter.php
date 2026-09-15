<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export;

use InvalidArgumentException;
use CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\ContentTypes;
use CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\MetricExporter as OtlpMetricExporterImpl;
use CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporterInterface as OtelMetricExporterInterface;
/**
 * OTLP/HTTP metric exporter (SPEC §51). Separate from `OtlpExporter` (spans) rather than
 * sharing `ExporterInterface` - its OTel return type is a different interface family
 * entirely, and the two are configured independently since a consumer may have a real
 * traces endpoint but no metrics endpoint yet (or vice versa).
 */
final class OtlpMetricExporter
{
    public function __construct(private readonly string $endpoint, private readonly ?string $apiKey = null, private readonly array $headers = [], private readonly float $timeoutSeconds = 10.0)
    {
        if (!filter_var($endpoint, \FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('Invalid OTLP metrics endpoint "%s"', $endpoint));
        }
    }
    public function toOtelExporter(): OtelMetricExporterInterface
    {
        $headers = $this->headers;
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        $transport = (new OtlpHttpTransportFactory())->create(endpoint: $this->endpoint, contentType: ContentTypes::PROTOBUF, headers: $headers, timeout: $this->timeoutSeconds);
        return new OtlpMetricExporterImpl($transport);
    }
}
/**
 * OTLP/HTTP metric exporter (SPEC §51). Separate from `OtlpExporter` (spans) rather than
 * sharing `ExporterInterface` - its OTel return type is a different interface family
 * entirely, and the two are configured independently since a consumer may have a real
 * traces endpoint but no metrics endpoint yet (or vice versa).
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export\OtlpMetricExporter', 'Cresenity\DevCloud\APM\Export\OtlpMetricExporter', \false);
