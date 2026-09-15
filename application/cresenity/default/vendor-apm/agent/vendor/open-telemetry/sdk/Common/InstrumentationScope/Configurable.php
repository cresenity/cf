<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope;

interface Configurable
{
    public function updateConfigurator(Configurator $configurator): void;
}
