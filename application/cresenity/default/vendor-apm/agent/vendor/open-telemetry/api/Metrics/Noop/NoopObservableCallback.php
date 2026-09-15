<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\Noop;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObservableCallbackInterface;
/**
 * @internal
 */
final class NoopObservableCallback implements ObservableCallbackInterface
{
    #[\Override]
    public function detach(): void
    {
        // no-op
    }
}
