<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\Noop;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObservableGaugeInterface;
/**
 * @internal
 */
final class NoopObservableGauge implements ObservableGaugeInterface
{
    #[\Override]
    public function observe(callable $callback, bool $weaken = \false): ObservableCallbackInterface
    {
        return new NoopObservableCallback();
    }
    #[\Override]
    public function isEnabled(): bool
    {
        return \false;
    }
}
