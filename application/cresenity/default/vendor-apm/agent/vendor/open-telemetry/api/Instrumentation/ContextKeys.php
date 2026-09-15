<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\EventLoggerProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\LoggerProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\TracerProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextKeyInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\ResponsePropagatorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
/**
 * @internal
 */
final class ContextKeys
{
    /**
     * @return ContextKeyInterface<TracerProviderInterface>
     */
    public static function tracerProvider(): ContextKeyInterface
    {
        static $instance;
        return $instance ??= Context::createKey(TracerProviderInterface::class);
    }
    /**
     * @return ContextKeyInterface<MeterProviderInterface>
     */
    public static function meterProvider(): ContextKeyInterface
    {
        static $instance;
        return $instance ??= Context::createKey(MeterProviderInterface::class);
    }
    /**
     * @return ContextKeyInterface<TextMapPropagatorInterface>
     */
    public static function propagator(): ContextKeyInterface
    {
        static $instance;
        return $instance ??= Context::createKey(TextMapPropagatorInterface::class);
    }
    /**
     * @return ContextKeyInterface<ResponsePropagatorInterface>
     */
    public static function responsePropagator(): ContextKeyInterface
    {
        static $instance;
        return $instance ??= Context::createKey(ResponsePropagatorInterface::class);
    }
    /**
     * @return ContextKeyInterface<LoggerProviderInterface>
     */
    public static function loggerProvider(): ContextKeyInterface
    {
        static $instance;
        return $instance ??= Context::createKey(LoggerProviderInterface::class);
    }
    /**
     * @deprecated
     * @return ContextKeyInterface<EventLoggerProviderInterface>
     */
    public static function eventLoggerProvider(): ContextKeyInterface
    {
        static $instance;
        return $instance ??= Context::createKey(EventLoggerProviderInterface::class);
    }
}
