<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Data;

interface DataInterface
{
    public function dataPointCount(): int;
}
