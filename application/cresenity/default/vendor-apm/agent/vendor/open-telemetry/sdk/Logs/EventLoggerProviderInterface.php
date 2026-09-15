<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs as API;
/**
 * @phan-suppress PhanDeprecatedInterface
 */
interface EventLoggerProviderInterface extends API\EventLoggerProviderInterface
{
    public function forceFlush(): bool;
}
