<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Data;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfo;
final class Metric
{
    public function __construct(public readonly InstrumentationScopeInterface $instrumentationScope, public readonly ResourceInfo $resource, public readonly string $name, public readonly ?string $unit, public readonly ?string $description, public readonly DataInterface $data)
    {
    }
}
