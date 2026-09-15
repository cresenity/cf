<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\Psr\Message;

use CresenityDevCloudAPMVendor\Psr\Http\Message\RequestFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ResponseFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ServerRequestFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\StreamFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\UploadedFileFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\UriFactoryInterface;
interface FactoryResolverInterface
{
    public function resolveRequestFactory(): RequestFactoryInterface;
    public function resolveResponseFactory(): ResponseFactoryInterface;
    public function resolveServerRequestFactory(): ServerRequestFactoryInterface;
    public function resolveStreamFactory(): StreamFactoryInterface;
    public function resolveUploadedFileFactory(): UploadedFileFactoryInterface;
    public function resolveUriFactory(): UriFactoryInterface;
}
