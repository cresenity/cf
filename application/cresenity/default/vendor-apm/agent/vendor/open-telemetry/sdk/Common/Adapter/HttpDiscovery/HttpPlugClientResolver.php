<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Adapter\HttpDiscovery;

use CresenityDevCloudAPMVendor\Http\Client\HttpAsyncClient;
use CresenityDevCloudAPMVendor\Http\Discovery\HttpAsyncClientDiscovery;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\HttpPlug\Client\ResolverInterface;
final class HttpPlugClientResolver implements ResolverInterface
{
    public function __construct(private ?HttpAsyncClient $httpAsyncClient = null)
    {
    }
    public static function create(?HttpAsyncClient $httpAsyncClient = null): self
    {
        return new self($httpAsyncClient);
    }
    #[\Override]
    public function resolveHttpPlugAsyncClient(): HttpAsyncClient
    {
        return $this->httpAsyncClient ??= HttpAsyncClientDiscovery::find();
    }
}
