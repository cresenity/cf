<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricRegistry;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Instrument;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Stream\MetricAggregatorFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Stream\MetricAggregatorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Stream\MetricStreamInterface;
/**
 * @internal
 */
interface MetricRegistryInterface extends MetricCollectorInterface
{
    public function registerSynchronousStream(Instrument $instrument, MetricStreamInterface $stream, MetricAggregatorInterface $aggregator): int;
    public function registerAsynchronousStream(Instrument $instrument, MetricStreamInterface $stream, MetricAggregatorFactoryInterface $aggregatorFactory): int;
    public function unregisterStreams(Instrument $instrument): array;
}
