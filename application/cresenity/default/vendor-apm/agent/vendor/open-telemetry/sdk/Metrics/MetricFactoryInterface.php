<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Exemplar\ExemplarFilterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricRegistry\MetricRegistryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfo;
/**
 * @internal
 */
interface MetricFactoryInterface
{
    /**
     * @param iterable<array{ViewProjection, MetricRegistrationInterface}> $views
     */
    public function createAsynchronousObserver(MetricRegistryInterface $registry, ResourceInfo $resource, InstrumentationScopeInterface $instrumentationScope, Instrument $instrument, int $timestamp, iterable $views): array;
    /**
     * @param iterable<array{ViewProjection, MetricRegistrationInterface}> $views
     */
    public function createSynchronousWriter(MetricRegistryInterface $registry, ResourceInfo $resource, InstrumentationScopeInterface $instrumentationScope, Instrument $instrument, int $timestamp, iterable $views, ?ExemplarFilterInterface $exemplarFilter = null): array;
}
