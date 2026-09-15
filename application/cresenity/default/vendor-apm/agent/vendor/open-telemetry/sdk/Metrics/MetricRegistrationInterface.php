<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

/**
 * @internal
 */
interface MetricRegistrationInterface
{
    public function register(MetricSourceProviderInterface $provider, MetricMetadataInterface $metadata): void;
}
