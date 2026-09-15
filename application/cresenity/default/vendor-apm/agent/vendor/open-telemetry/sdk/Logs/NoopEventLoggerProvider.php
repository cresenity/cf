<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs as API;
/**
 * @phan-suppress PhanDeprecatedInterface
 */
class NoopEventLoggerProvider extends API\NoopEventLoggerProvider implements EventLoggerProviderInterface
{
    #[\Override]
    public static function getInstance(): self
    {
        static $instance;
        return $instance ??= new self();
    }
    #[\Override]
    public function forceFlush(): bool
    {
        return \true;
    }
}
