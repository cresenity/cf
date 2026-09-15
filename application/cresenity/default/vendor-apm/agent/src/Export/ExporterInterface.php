<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporterInterface as OtelSpanExporterInterface;
/**
 * A configured span destination. Kept as our own interface (rather than exposing
 * `OpenTelemetry\SDK\Trace\SpanExporterInterface` directly everywhere) mainly so callers
 * depend on this package's own type. Span-specific by return type - `OtlpMetricExporter`
 * (SPEC §51) is a separate, non-implementing class rather than forced into this same
 * interface, since its OTel return type is necessarily different.
 */
interface ExporterInterface
{
    public function toOtelExporter(): OtelSpanExporterInterface;
}
/**
 * A configured span destination. Kept as our own interface (rather than exposing
 * `OpenTelemetry\SDK\Trace\SpanExporterInterface` directly everywhere) mainly so callers
 * depend on this package's own type. Span-specific by return type - `OtlpMetricExporter`
 * (SPEC §51) is a separate, non-implementing class rather than forced into this same
 * interface, since its OTel return type is necessarily different.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export\ExporterInterface', 'Cresenity\DevCloud\APM\Export\ExporterInterface', \false);
