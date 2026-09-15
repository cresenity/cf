<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\HttpPlug\Client\ResolverInterface as HttpPlugClientResolverInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\Psr\Client\ResolverInterface as PsrClientResolverInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\Psr\Message\FactoryResolverInterface;
interface DependencyResolverInterface extends FactoryResolverInterface, PsrClientResolverInterface, HttpPlugClientResolverInterface
{
}
