<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigProperties;
interface Instrumentation
{
    public function register(HookManagerInterface $hookManager, ConfigProperties $configuration, Context $context): void;
}
