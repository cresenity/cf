<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config\AgentConfig;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Common\Time\Clock;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanProcessorInterface;
/**
 * Builds the batching span processor sitting between the tracer and the exporter
 * (SPEC §25/§50): telemetry is queued and flushed on a timer/size threshold, never
 * exported synchronously on the request thread, and dropped rather than blocking
 * once the queue is full.
 */
final class BatchProcessor
{
    public static function build(ExporterInterface $exporter, AgentConfig $config): SpanProcessorInterface
    {
        return new BatchSpanProcessor(exporter: $exporter->toOtelExporter(), clock: Clock::getDefault(), maxQueueSize: $config->maxQueueSize, scheduledDelayMillis: $config->flushIntervalMs, exportTimeoutMillis: BatchSpanProcessor::DEFAULT_EXPORT_TIMEOUT, maxExportBatchSize: min($config->batchSize, $config->maxQueueSize));
    }
}
/**
 * Builds the batching span processor sitting between the tracer and the exporter
 * (SPEC §25/§50): telemetry is queued and flushed on a timer/size threshold, never
 * exported synchronously on the request thread, and dropped rather than blocking
 * once the queue is full.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export\BatchProcessor', 'Cresenity\DevCloud\APM\Export\BatchProcessor', \false);
