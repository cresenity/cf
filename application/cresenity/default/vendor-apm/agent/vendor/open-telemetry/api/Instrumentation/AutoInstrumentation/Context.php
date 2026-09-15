<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\LoggerProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\NoopLoggerProvider;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\Noop\NoopMeterProvider;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\NoopTracerProvider;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\TracerProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\NoopResponsePropagator;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\NoopTextMapPropagator;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\ResponsePropagatorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
/**
 * Context used for component creation.
 */
final class Context
{
    public function __construct(public readonly TracerProviderInterface $tracerProvider = new NoopTracerProvider(), public readonly MeterProviderInterface $meterProvider = new NoopMeterProvider(), public readonly LoggerProviderInterface $loggerProvider = new NoopLoggerProvider(), public readonly TextMapPropagatorInterface $propagator = new NoopTextMapPropagator(), public readonly ResponsePropagatorInterface $responsePropagator = new NoopResponsePropagator())
    {
    }
}
