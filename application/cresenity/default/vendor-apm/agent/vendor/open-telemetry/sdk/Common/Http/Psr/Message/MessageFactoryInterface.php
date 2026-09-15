<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\Psr\Message;

use CresenityDevCloudAPMVendor\Psr\Http\Message\RequestFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ResponseFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ServerRequestFactoryInterface;
interface MessageFactoryInterface extends RequestFactoryInterface, ServerRequestFactoryInterface, ResponseFactoryInterface
{
}
