<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace;

interface IdGeneratorInterface
{
    public function generateTraceId(): string;
    public function generateSpanId(): string;
}
