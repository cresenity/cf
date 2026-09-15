<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricRegistry;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObserverInterface;
/**
 * @internal
 */
final class NoopObserver implements ObserverInterface
{
    #[\Override]
    public function observe($amount, iterable $attributes = []): void
    {
        // no-op
    }
}
