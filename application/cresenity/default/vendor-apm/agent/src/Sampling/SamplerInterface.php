<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Sampling;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SamplerInterface as OtelSamplerInterface;
/**
 * DevCloud's own sampling vocabulary (SPEC §24), kept small and configuration-facing.
 * The actual sampling algorithm always comes from the OpenTelemetry SDK itself
 * (SPEC §58) — implementations here only select and configure it.
 */
interface SamplerInterface
{
    public function toOtelSampler(): OtelSamplerInterface;
}
/**
 * DevCloud's own sampling vocabulary (SPEC §24), kept small and configuration-facing.
 * The actual sampling algorithm always comes from the OpenTelemetry SDK itself
 * (SPEC §58) — implementations here only select and configure it.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Sampling\SamplerInterface', 'Cresenity\DevCloud\APM\Sampling\SamplerInterface', \false);
