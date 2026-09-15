<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Sdk;
/**
 * @deprecated
 */
class EventLoggerProviderFactory
{
    public function create(LoggerProviderInterface $loggerProvider): EventLoggerProviderInterface
    {
        if (Sdk::isDisabled()) {
            return NoopEventLoggerProvider::getInstance();
        }
        return new EventLoggerProvider($loggerProvider);
    }
}
