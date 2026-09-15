<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Adapter\HttpDiscovery;

use CresenityDevCloudAPMVendor\Http\Client\HttpAsyncClient;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\DependencyResolverInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\HttpPlug\Client\ResolverInterface as HttpPlugClientResolverInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\Psr\Client\ResolverInterface as PsrClientResolverInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\Psr\Message\FactoryResolverInterface as MessageFactoryResolverInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Client\ClientInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\RequestFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ResponseFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ServerRequestFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\StreamFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\UploadedFileFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\UriFactoryInterface;
final class DependencyResolver implements DependencyResolverInterface
{
    private readonly MessageFactoryResolverInterface $messageFactoryResolver;
    private readonly PsrClientResolverInterface $psrClientResolver;
    private readonly HttpPlugClientResolverInterface $httpPlugClientResolver;
    public function __construct(?MessageFactoryResolverInterface $messageFactoryResolver = null, ?PsrClientResolverInterface $psrClientResolver = null, ?HttpPlugClientResolverInterface $httpPlugClientResolver = null)
    {
        $this->messageFactoryResolver = $messageFactoryResolver ?? MessageFactoryResolver::create();
        $this->psrClientResolver = $psrClientResolver ?? PsrClientResolver::create();
        $this->httpPlugClientResolver = $httpPlugClientResolver ?? HttpPlugClientResolver::create();
    }
    public static function create(?MessageFactoryResolverInterface $messageFactoryResolver = null, ?PsrClientResolverInterface $psrClientResolver = null, ?HttpPlugClientResolverInterface $httpPlugClientResolver = null): self
    {
        return new self($messageFactoryResolver, $psrClientResolver, $httpPlugClientResolver);
    }
    #[\Override]
    public function resolveRequestFactory(): RequestFactoryInterface
    {
        return $this->messageFactoryResolver->resolveRequestFactory();
    }
    #[\Override]
    public function resolveResponseFactory(): ResponseFactoryInterface
    {
        return $this->messageFactoryResolver->resolveResponseFactory();
    }
    #[\Override]
    public function resolveServerRequestFactory(): ServerRequestFactoryInterface
    {
        return $this->messageFactoryResolver->resolveServerRequestFactory();
    }
    #[\Override]
    public function resolveStreamFactory(): StreamFactoryInterface
    {
        return $this->messageFactoryResolver->resolveStreamFactory();
    }
    #[\Override]
    public function resolveUploadedFileFactory(): UploadedFileFactoryInterface
    {
        return $this->messageFactoryResolver->resolveUploadedFileFactory();
    }
    #[\Override]
    public function resolveUriFactory(): UriFactoryInterface
    {
        return $this->messageFactoryResolver->resolveUriFactory();
    }
    #[\Override]
    public function resolveHttpPlugAsyncClient(): HttpAsyncClient
    {
        return $this->httpPlugClientResolver->resolveHttpPlugAsyncClient();
    }
    #[\Override]
    public function resolvePsrClient(): ClientInterface
    {
        return $this->psrClientResolver->resolvePsrClient();
    }
}
