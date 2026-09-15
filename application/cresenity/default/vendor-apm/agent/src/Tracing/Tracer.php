<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\TracerInterface as OtelTracerInterface;
/**
 * The agent's tracing façade — everything else in this package (and later,
 * instrumentation/framework integrations) creates spans through this, never by reaching
 * for the OTel API's TracerProvider directly, so the core stays the one seam that knows
 * about OpenTelemetry types (SPEC §2).
 */
final class Tracer
{
    public function __construct(private readonly OtelTracerInterface $otelTracer)
    {
    }
    /**
     * @param array<string, bool|int|float|string|array<mixed>|null> $attributes
     * @param SpanKind::KIND_* $kind
     */
    public function startSpan(string $name, array $attributes = [], int $kind = SpanKind::KIND_INTERNAL): SpanInterface
    {
        return SpanFactory::create($this->otelTracer, $name, $attributes, $kind);
    }
    /**
     * @template T
     *
     * @param callable(SpanInterface): T                              $callback
     * @param array<string, bool|int|float|string|array<mixed>|null> $attributes
     * @param SpanKind::KIND_* $kind
     *
     * @return T
     */
    public function trace(string $name, callable $callback, array $attributes = [], int $kind = SpanKind::KIND_INTERNAL): mixed
    {
        return ContextManager::runInSpan($this->startSpan($name, $attributes, $kind), $callback);
    }
}
/**
 * The agent's tracing façade — everything else in this package (and later,
 * instrumentation/framework integrations) creates spans through this, never by reaching
 * for the OTel API's TracerProvider directly, so the core stays the one seam that knows
 * about OpenTelemetry types (SPEC §2).
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer', 'Cresenity\DevCloud\APM\Tracing\Tracer', \false);
