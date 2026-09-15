<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
interface ViewRegistryInterface
{
    /**
     * @return iterable<ViewProjection>|null
     */
    public function find(Instrument $instrument, InstrumentationScopeInterface $instrumentationScope): ?iterable;
}
