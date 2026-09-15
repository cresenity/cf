<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\Contrib\Otlp;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Export\TransportInterface;
class OtlpHttpTransportFactory implements TransportFactoryInterface
{
    private const DEFAULT_COMPRESSION = 'none';
    #[\Override]
    public function create(string $endpoint, string $contentType, array $headers = [], $compression = null, float $timeout = 10.0, int $retryDelay = 100, int $maxRetries = 3, ?string $cacert = null, ?string $cert = null, ?string $key = null): TransportInterface
    {
        if ($compression === self::DEFAULT_COMPRESSION) {
            $compression = null;
        }
        return (new PsrTransportFactory())->create($endpoint, $contentType, $headers, $compression, $timeout, $retryDelay, $maxRetries, $cacert, $cert, $key);
    }
}
