<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\Configurator;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\EventLoggerProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\NoopEventLoggerProvider;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\NoopResponsePropagator;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\NoopTextMapPropagator;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\ResponsePropagatorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ScopeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\NoopLoggerProvider;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\NoopTracerProvider;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\TracerProviderInterface;
class SdkBuilder
{
    private ?TracerProviderInterface $tracerProvider = null;
    private ?MeterProviderInterface $meterProvider = null;
    private ?LoggerProviderInterface $loggerProvider = null;
    private ?EventLoggerProviderInterface $eventLoggerProvider = null;
    private ?TextMapPropagatorInterface $propagator = null;
    private ?ResponsePropagatorInterface $responsePropagator = null;
    private bool $autoShutdown = \false;
    /**
     * Automatically shut down providers on process completion. If not set, the user is responsible for calling `shutdown`.
     */
    public function setAutoShutdown(bool $shutdown): self
    {
        $this->autoShutdown = $shutdown;
        return $this;
    }
    public function setTracerProvider(TracerProviderInterface $provider): self
    {
        $this->tracerProvider = $provider;
        return $this;
    }
    public function setMeterProvider(MeterProviderInterface $meterProvider): self
    {
        $this->meterProvider = $meterProvider;
        return $this;
    }
    public function setLoggerProvider(LoggerProviderInterface $loggerProvider): self
    {
        $this->loggerProvider = $loggerProvider;
        return $this;
    }
    /**
     * @deprecated
     */
    public function setEventLoggerProvider(EventLoggerProviderInterface $eventLoggerProvider): self
    {
        $this->eventLoggerProvider = $eventLoggerProvider;
        return $this;
    }
    public function setPropagator(TextMapPropagatorInterface $propagator): self
    {
        $this->propagator = $propagator;
        return $this;
    }
    // @experimental
    public function setResponsePropagator(ResponsePropagatorInterface $responsePropagator): self
    {
        $this->responsePropagator = $responsePropagator;
        return $this;
    }
    public function build(): Sdk
    {
        $tracerProvider = $this->tracerProvider ?? new NoopTracerProvider();
        $meterProvider = $this->meterProvider ?? new NoopMeterProvider();
        $loggerProvider = $this->loggerProvider ?? new NoopLoggerProvider();
        $eventLoggerProvider = $this->eventLoggerProvider ?? new NoopEventLoggerProvider();
        if ($this->autoShutdown) {
            // rector rule disabled in config, because ShutdownHandler::register() does not keep a strong reference to $this
            ShutdownHandler::register($tracerProvider->shutdown(...));
            ShutdownHandler::register($meterProvider->shutdown(...));
            ShutdownHandler::register($loggerProvider->shutdown(...));
        }
        return new Sdk($tracerProvider, $meterProvider, $loggerProvider, $eventLoggerProvider, $this->propagator ?? NoopTextMapPropagator::getInstance(), $this->responsePropagator ?? NoopResponsePropagator::getInstance());
    }
    /**
     * @phan-suppress PhanDeprecatedFunction
     */
    public function buildAndRegisterGlobal(): ScopeInterface
    {
        $sdk = $this->build();
        $context = Configurator::create()->withPropagator($sdk->getPropagator())->withTracerProvider($sdk->getTracerProvider())->withMeterProvider($sdk->getMeterProvider())->withLoggerProvider($sdk->getLoggerProvider())->withEventLoggerProvider($sdk->getEventLoggerProvider())->withResponsePropagator($sdk->getResponsePropagator())->storeInContext();
        return Context::storage()->attach($context);
    }
}
