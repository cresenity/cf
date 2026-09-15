<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export;

use InvalidArgumentException;
use CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\ContentTypes;
use CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp\SpanExporter as OtlpSpanExporter;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporterInterface as OtelSpanExporterInterface;
/**
 * OTLP/HTTP span exporter (SPEC §26). Never builds a proprietary wire format —
 * this is a thin configuration wrapper over the official OTel SDK exporter.
 */
final class OtlpExporter implements ExporterInterface
{
    /**
     * @param string                $endpoint full OTLP/HTTP traces endpoint, e.g. "https://apm.example/v1/traces"
     * @param array<string, string> $headers  extra headers merged in, in addition to auth
     */
    public function __construct(private readonly string $endpoint, private readonly ?string $apiKey = null, private readonly array $headers = [], private readonly float $timeoutSeconds = 10.0)
    {
        if (!filter_var($endpoint, \FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('Invalid OTLP endpoint "%s"', $endpoint));
        }
    }
    public function toOtelExporter(): OtelSpanExporterInterface
    {
        $headers = $this->headers;
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        $transport = (new OtlpHttpTransportFactory())->create(endpoint: $this->endpoint, contentType: ContentTypes::PROTOBUF, headers: $headers, timeout: $this->timeoutSeconds);
        return new OtlpSpanExporter($transport);
    }
}
/**
 * OTLP/HTTP span exporter (SPEC §26). Never builds a proprietary wire format —
 * this is a thin configuration wrapper over the official OTel SDK exporter.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export\OtlpExporter', 'Cresenity\DevCloud\APM\Export\OtlpExporter', \false);
