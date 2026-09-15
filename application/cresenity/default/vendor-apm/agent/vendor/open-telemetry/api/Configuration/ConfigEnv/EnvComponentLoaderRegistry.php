<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\Context;
interface EnvComponentLoaderRegistry
{
    /**
     * @template T
     * @param class-string<T> $type
     * @return T
     */
    public function load(string $type, string $name, EnvResolver $env, Context $context): mixed;
}
