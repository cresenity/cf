<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Bootstrap;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
/**
 * What `Bootstrap::boot()` produces - tracing and metrics are configured
 * independently (SPEC §51: a consumer may have a traces endpoint but no
 * metrics endpoint yet, or vice versa), so either may be null on its own
 * without the other being affected.
 */
final class BootResult
{
    public function __construct(public readonly ?Tracer $tracer, public readonly ?Metrics $metrics)
    {
    }
    public static function empty(): self
    {
        return new self(null, null);
    }
}
/**
 * What `Bootstrap::boot()` produces - tracing and metrics are configured
 * independently (SPEC §51: a consumer may have a traces endpoint but no
 * metrics endpoint yet, or vice versa), so either may be null on its own
 * without the other being affected.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Bootstrap\BootResult', 'Cresenity\DevCloud\APM\Bootstrap\BootResult', \false);
