<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Sampling;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler as OtelAlwaysOnSampler;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SamplerInterface as OtelSamplerInterface;
final class AlwaysOnSampler implements SamplerInterface
{
    public function toOtelSampler(): OtelSamplerInterface
    {
        return new OtelAlwaysOnSampler();
    }
}
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Sampling\AlwaysOnSampler', 'Cresenity\DevCloud\APM\Sampling\AlwaysOnSampler', \false);
