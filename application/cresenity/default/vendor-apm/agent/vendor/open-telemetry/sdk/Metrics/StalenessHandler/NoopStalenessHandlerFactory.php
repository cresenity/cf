<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\StalenessHandler;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\ReferenceCounterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\StalenessHandlerFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\StalenessHandlerInterface;
final class NoopStalenessHandlerFactory implements StalenessHandlerFactoryInterface
{
    #[\Override]
    public function create(): ReferenceCounterInterface&StalenessHandlerInterface
    {
        static $instance;
        return $instance ??= new NoopStalenessHandler();
    }
}
