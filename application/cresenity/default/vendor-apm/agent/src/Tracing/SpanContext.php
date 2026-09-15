<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanContextInterface;
/**
 * Plain snapshot of a span's identity — used to correlate application logs with a trace
 * (SPEC §32: `trace_id`/`span_id` alongside a log line), without handing callers the full
 * OTel `SpanContextInterface` and its context-propagation surface.
 */
final class SpanContext
{
    public function __construct(public readonly string $traceId, public readonly string $spanId, public readonly bool $sampled)
    {
    }
    public static function fromOtel(SpanContextInterface $context): self
    {
        return new self(traceId: $context->getTraceId(), spanId: $context->getSpanId(), sampled: $context->isSampled());
    }
}
/**
 * Plain snapshot of a span's identity — used to correlate application logs with a trace
 * (SPEC §32: `trace_id`/`span_id` alongside a log line), without handing callers the full
 * OTel `SpanContextInterface` and its context-propagation surface.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\SpanContext', 'Cresenity\DevCloud\APM\Tracing\SpanContext', \false);
