<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Common\Time\ClockInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\EventLoggerInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\LoggerInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\LogRecord;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\Severity;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
/**
 * @deprecated
 * @phan-suppress PhanDeprecatedInterface
 */
class EventLogger implements EventLoggerInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly LoggerInterface $logger, private readonly ClockInterface $clock)
    {
    }
    /**
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/v1.32.0/specification/logs/event-sdk.md#emit-event
     */
    #[\Override]
    public function emit(string $name, mixed $body = null, ?int $timestamp = null, ?ContextInterface $context = null, ?Severity $severityNumber = null, iterable $attributes = []): void
    {
        $logRecord = new LogRecord();
        /**
         *  Set event.name twice: first to position it as the initial attribute entry,
         *  then again after setAttributes() to prevent its value from being overwritten.
         *  This ensures event.name won't be dropped by attribute limits.
         * @see https://github.com/open-telemetry/opentelemetry-php/pull/1768#issuecomment-3527425474
         */
        $logRecord->setAttribute('event.name', $name);
        $logRecord->setAttributes($attributes);
        $logRecord->setAttribute('event.name', $name);
        $logRecord->setBody($body);
        $logRecord->setTimestamp($timestamp ?? $this->clock->now());
        $logRecord->setContext($context ?? Context::getCurrent());
        $logRecord->setSeverityNumber($severityNumber ?? Severity::INFO);
        $this->logger->emit($logRecord);
    }
}
