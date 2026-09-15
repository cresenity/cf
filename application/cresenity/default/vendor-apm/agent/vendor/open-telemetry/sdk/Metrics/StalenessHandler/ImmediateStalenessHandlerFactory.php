<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\StalenessHandler;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\ReferenceCounterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\StalenessHandlerFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\StalenessHandlerInterface;
final class ImmediateStalenessHandlerFactory implements StalenessHandlerFactoryInterface
{
    #[\Override]
    public function create(): ReferenceCounterInterface&StalenessHandlerInterface
    {
        return new ImmediateStalenessHandler();
    }
}
