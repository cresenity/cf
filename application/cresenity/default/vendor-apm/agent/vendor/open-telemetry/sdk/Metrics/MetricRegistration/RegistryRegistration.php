<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricRegistration;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricMetadataInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricRegistrationInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricSourceProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricSourceRegistryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\StalenessHandlerInterface;
/**
 * @internal
 */
final class RegistryRegistration implements MetricRegistrationInterface
{
    public function __construct(private readonly MetricSourceRegistryInterface $registry, private readonly StalenessHandlerInterface $stalenessHandler)
    {
    }
    #[\Override]
    public function register(MetricSourceProviderInterface $provider, MetricMetadataInterface $metadata): void
    {
        $this->registry->add($provider, $metadata, $this->stalenessHandler);
    }
}
