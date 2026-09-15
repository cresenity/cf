<?php

namespace CresenityDevCloudAPMVendor\Http\Factory\Guzzle;

use CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\Response;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ResponseFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ResponseInterface;
class ResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, [], null, '1.1', $reasonPhrase);
    }
}
