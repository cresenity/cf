<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace;

interface TraceFlags
{
    public const SAMPLED = 0x1;
    public const RANDOM = 0x2;
    public const DEFAULT = 0x0;
}
