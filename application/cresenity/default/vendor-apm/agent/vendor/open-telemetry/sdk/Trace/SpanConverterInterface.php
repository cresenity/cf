<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace;

interface SpanConverterInterface
{
    public function convert(iterable $spans): array;
}
