<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

interface MetricExporterFactoryInterface
{
    public function create(): MetricExporterInterface;
}
