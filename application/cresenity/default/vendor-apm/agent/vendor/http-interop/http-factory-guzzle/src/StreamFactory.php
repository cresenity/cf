<?php

namespace CresenityDevCloudAPMVendor\Http\Factory\Guzzle;

use CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\Stream;
use CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\Utils;
use CresenityDevCloudAPMVendor\Psr\Http\Message\StreamFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\StreamInterface;
class StreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        return Utils::streamFor($content);
    }
    public function createStreamFromFile(string $file, string $mode = 'r'): StreamInterface
    {
        return $this->createStreamFromResource(Utils::tryFopen($file, $mode));
    }
    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }
}
