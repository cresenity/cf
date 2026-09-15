<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Attribute\Attributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\Configurator;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\Processor\MultiLogRecordProcessor;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\Processor\NoopLogRecordProcessor;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfo;
class LoggerProviderBuilder
{
    /** @var array<LogRecordProcessorInterface> */
    private array $processors = [];
    private ?ResourceInfo $resource = null;
    private ?Configurator $configurator = null;
    private ?MeterProviderInterface $meterProvider = null;
    public function addLogRecordProcessor(LogRecordProcessorInterface $processor): self
    {
        $this->processors[] = $processor;
        return $this;
    }
    public function setResource(ResourceInfo $resource): self
    {
        $this->resource = $resource;
        return $this;
    }
    public function build(): LoggerProviderInterface
    {
        return new LoggerProvider($this->buildProcessor(), new InstrumentationScopeFactory(Attributes::factory()), $this->resource, configurator: $this->configurator ?? Configurator::logger(), meterProvider: $this->meterProvider);
    }
    public function setConfigurator(Configurator $configurator): self
    {
        $this->configurator = $configurator;
        return $this;
    }
    public function setMeterProvider(MeterProviderInterface $meterProvider): self
    {
        $this->meterProvider = $meterProvider;
        return $this;
    }
    private function buildProcessor(): LogRecordProcessorInterface
    {
        return match (count($this->processors)) {
            0 => NoopLogRecordProcessor::getInstance(),
            1 => $this->processors[0],
            default => new MultiLogRecordProcessor($this->processors),
        };
    }
}
