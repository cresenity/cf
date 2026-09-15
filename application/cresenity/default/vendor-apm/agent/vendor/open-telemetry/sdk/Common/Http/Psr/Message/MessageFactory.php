<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\Psr\Message;

use CresenityDevCloudAPMVendor\Psr\Http\Message\RequestFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\RequestInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ResponseFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ResponseInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ServerRequestFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ServerRequestInterface;
final class MessageFactory implements MessageFactoryInterface
{
    public function __construct(private readonly RequestFactoryInterface $requestFactory, private readonly ResponseFactoryInterface $responseFactory, private readonly ServerRequestFactoryInterface $serverRequestFactory)
    {
    }
    public static function create(RequestFactoryInterface $requestFactory, ResponseFactoryInterface $responseFactory, ServerRequestFactoryInterface $serverRequestFactory): self
    {
        return new self($requestFactory, $responseFactory, $serverRequestFactory);
    }
    #[\Override]
    public function createRequest(string $method, $uri): RequestInterface
    {
        return $this->requestFactory->createRequest($method, $uri);
    }
    #[\Override]
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return $this->responseFactory->createResponse($code, $reasonPhrase);
    }
    #[\Override]
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return $this->serverRequestFactory->createServerRequest($method, $uri, $serverParams);
    }
}
