<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporter;

use ArrayObject;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\AggregationTemporalitySelectorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Data\Metric;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Data\Temporality;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricMetadataInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\PushMetricExporterInterface;
/**
 * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/sdk_exporters/in-memory.md
 */
final class InMemoryExporter implements MetricExporterInterface, AggregationTemporalitySelectorInterface, PushMetricExporterInterface
{
    private bool $closed = \false;
    /**
     * @template-implements ArrayObject<Metric> $storage
     * @param ArrayObject $storage
     * @param string|Temporality|null $temporality
     */
    public function __construct(private ArrayObject $storage = new ArrayObject(), private readonly string|Temporality|null $temporality = null)
    {
    }
    #[\Override]
    public function temporality(MetricMetadataInterface $metric): string|Temporality|null
    {
        return $this->temporality ?? $metric->temporality();
    }
    /**
     * @return Metric[]
     */
    public function collect(bool $reset = \false): array
    {
        $metrics = $this->storage->getArrayCopy();
        if ($reset) {
            $this->storage = new ArrayObject();
        }
        return $metrics;
    }
    #[\Override]
    public function export(iterable $batch): bool
    {
        if ($this->closed) {
            return \false;
        }
        foreach ($batch as $metric) {
            $this->storage->append($metric);
        }
        return \true;
    }
    #[\Override]
    public function shutdown(): bool
    {
        if ($this->closed) {
            return \false;
        }
        $this->closed = \true;
        return \true;
    }
    #[\Override]
    public function forceFlush(): bool
    {
        return \true;
    }
}
