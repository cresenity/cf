<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporter;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanConverterInterface;
class NullSpanConverter implements SpanConverterInterface
{
    #[\Override]
    public function convert(iterable $spans): array
    {
        return [[]];
    }
}
