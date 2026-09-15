<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Http\HttpPlug\Client;

use CresenityDevCloudAPMVendor\Http\Client\HttpAsyncClient;
interface ResolverInterface
{
    public function resolveHttpPlugAsyncClient(): HttpAsyncClient;
}
