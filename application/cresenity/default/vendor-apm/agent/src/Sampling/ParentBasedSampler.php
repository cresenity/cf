<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Sampling;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SamplerInterface as OtelSamplerInterface;
/**
 * Respects the parent span's sampling decision, falling back to $root only for a
 * genuine root span (SPEC §24) — the recommended default so a whole distributed
 * trace is kept or dropped consistently instead of each service sampling on its own.
 */
final class ParentBasedSampler implements SamplerInterface
{
    public function __construct(private readonly SamplerInterface $root)
    {
    }
    public function toOtelSampler(): OtelSamplerInterface
    {
        return new ParentBased($this->root->toOtelSampler());
    }
}
/**
 * Respects the parent span's sampling decision, falling back to $root only for a
 * genuine root span (SPEC §24) — the recommended default so a whole distributed
 * trace is kept or dropped consistently instead of each service sampling on its own.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Sampling\ParentBasedSampler', 'Cresenity\DevCloud\APM\Sampling\ParentBasedSampler', \false);
