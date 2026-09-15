<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Common\Time;

interface ClockInterface
{
    public const NANOS_PER_SECOND = 1000000000;
    public const NANOS_PER_MILLISECOND = 1000000;
    public const NANOS_PER_MICROSECOND = 1000;
    public const MICROS_PER_MILLISECOND = 1000;
    public const MILLIS_PER_SECOND = 1000;
    /**
     * Returns the current epoch wall-clock timestamp in nanoseconds
     */
    public function now(): int;
}
