<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\Configuration\General;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation\GeneralInstrumentationConfiguration;
class HttpConfig implements GeneralInstrumentationConfiguration
{
    public function __construct(public readonly array $config)
    {
    }
}
