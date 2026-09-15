<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricRegistry\MetricCollectorInterface;
/**
 * To be replaced by MetricProducer abstraction.
 *
 * @internal
 */
interface MetricSourceRegistryUnregisterInterface
{
    public function unregisterStream(MetricCollectorInterface $collector, int $streamId): void;
}
