<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope;

/**
 * @internal
 */
interface Config
{
    public function setDisabled(bool $disabled): void;
    public function isEnabled(): bool;
}
