<?php

namespace CresenityDevCloudAPMVendor\Http\Factory\Guzzle;

use CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\Uri;
use CresenityDevCloudAPMVendor\Psr\Http\Message\UriFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\UriInterface;
class UriFactory implements UriFactoryInterface
{
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
