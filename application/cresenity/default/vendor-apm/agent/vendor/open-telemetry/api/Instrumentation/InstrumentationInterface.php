<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\TracerInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\TracerProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\ResponsePropagatorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use CresenityDevCloudAPMVendor\Psr\Log\LoggerInterface;
/**
 * @deprecated
 */
interface InstrumentationInterface
{
    public function getName(): string;
    public function getVersion(): ?string;
    public function getSchemaUrl(): ?string;
    public function init(): bool;
    public function activate(): bool;
    public function setPropagator(TextMapPropagatorInterface $propagator): void;
    public function getPropagator(): TextMapPropagatorInterface;
    public function setTracerProvider(TracerProviderInterface $tracerProvider): void;
    public function getTracerProvider(): TracerProviderInterface;
    public function getTracer(): TracerInterface;
    public function setMeterProvider(MeterProviderInterface $meterProvider): void;
    public function getMeter(): MeterInterface;
    public function setLogger(LoggerInterface $logger): void;
    public function getLogger(): LoggerInterface;
    public function setResponsePropagator(ResponsePropagatorInterface $responsePropagator): void;
    public function getResponsePropagator(): ResponsePropagatorInterface;
}
