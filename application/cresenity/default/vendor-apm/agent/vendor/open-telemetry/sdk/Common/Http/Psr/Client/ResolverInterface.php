<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\Psr\Client;

use CresenityDevCloudAPMVendor\Psr\Http\Client\ClientInterface;
interface ResolverInterface
{
    public function resolvePsrClient(): ClientInterface;
}
