<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Data\Temporality;
interface MetricSourceProviderInterface
{
    /**
     * @param string|Temporality $temporality
     */
    public function create($temporality): MetricSourceInterface;
}
