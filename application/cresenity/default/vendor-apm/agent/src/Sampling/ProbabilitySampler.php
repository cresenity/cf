<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Sampling;

use InvalidArgumentException;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SamplerInterface as OtelSamplerInterface;
/**
 * Retains an approximate fraction of traces (SPEC §24), e.g. `new ProbabilitySampler(0.10)`
 * keeps ~10%. Decision is based on the trace id, so it is consistent across a distributed
 * trace without any coordination between services.
 */
final class ProbabilitySampler implements SamplerInterface
{
    public function __construct(private readonly float $ratio)
    {
        if ($ratio < 0.0 || $ratio > 1.0) {
            throw new InvalidArgumentException(sprintf('Sample ratio must be between 0.0 and 1.0, got %s', $ratio));
        }
    }
    public function toOtelSampler(): OtelSamplerInterface
    {
        return new TraceIdRatioBasedSampler($this->ratio);
    }
}
/**
 * Retains an approximate fraction of traces (SPEC §24), e.g. `new ProbabilitySampler(0.10)`
 * keeps ~10%. Decision is based on the trace id, so it is consistent across a distributed
 * trace without any coordination between services.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Sampling\ProbabilitySampler', 'Cresenity\DevCloud\APM\Sampling\ProbabilitySampler', \false);
