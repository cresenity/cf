<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Adapter\HttpDiscovery;

use CresenityDevCloudAPMVendor\Http\Discovery\Psr18ClientDiscovery;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\Psr\Client\ResolverInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Client\ClientInterface;
final class PsrClientResolver implements ResolverInterface
{
    public function __construct(private ?ClientInterface $client = null)
    {
    }
    public static function create(?ClientInterface $client = null): self
    {
        return new self($client);
    }
    #[\Override]
    public function resolvePsrClient(): ClientInterface
    {
        return $this->client ??= Psr18ClientDiscovery::find();
    }
}
