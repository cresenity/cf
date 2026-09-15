<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\TracerInterface as OtelTracerInterface;
/**
 * Starts spans via the OTel API's SpanBuilder (SPEC §21). Creation only — the caller
 * decides whether/when to activate and end it (see ContextManager).
 */
final class SpanFactory
{
    /**
     * @param array<string, bool|int|float|string|array<mixed>|null> $attributes
     * @param SpanKind::KIND_* $kind
     */
    public static function create(OtelTracerInterface $tracer, string $name, array $attributes = [], int $kind = SpanKind::KIND_INTERNAL): SpanInterface
    {
        return $tracer->spanBuilder($name)->setSpanKind($kind)->setAttributes($attributes)->startSpan();
    }
}
/**
 * Starts spans via the OTel API's SpanBuilder (SPEC §21). Creation only — the caller
 * decides whether/when to activate and end it (see ContextManager).
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\SpanFactory', 'Cresenity\DevCloud\APM\Tracing\SpanFactory', \false);
