<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\Psr\Client\Discovery;

use CresenityDevCloudAPMVendor\Http\Client\Curl\Client;
use CresenityDevCloudAPMVendor\Psr\Http\Client\ClientInterface;
class CurlClient implements DiscoveryInterface
{
    /**
     * @phan-suppress PhanUndeclaredClassReference
     */
    #[\Override]
    public function available(): bool
    {
        return extension_loaded('curl') && class_exists(Client::class);
    }
    /**
     * @phan-suppress PhanUndeclaredClassReference,PhanTypeMismatchReturn,PhanUndeclaredClassMethod
     * @psalm-suppress UndefinedClass,InvalidReturnType,InvalidReturnStatement
     */
    #[\Override]
    public function create(mixed $options): ClientInterface
    {
        $options = [\CURLOPT_TIMEOUT => $options['timeout'] ?? null];
        /** @phpstan-ignore-next-line  */
        return new Client(options: array_filter($options));
    }
}
