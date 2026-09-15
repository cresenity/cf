<?php

namespace CresenityDevCloudAPMVendor\Http\Factory\Guzzle;

use CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\Request;
use CresenityDevCloudAPMVendor\Psr\Http\Message\RequestFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\RequestInterface;
class RequestFactory implements RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }
}
