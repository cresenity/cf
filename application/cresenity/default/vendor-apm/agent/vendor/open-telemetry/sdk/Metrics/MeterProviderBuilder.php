<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Common\Time\Clock;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Common\Time\ClockInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Attribute\Attributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\InstrumentationScope\Configurator;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Exemplar\ExemplarFilter\WithSampledTraceExemplarFilter;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Exemplar\ExemplarFilterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\StalenessHandler\NoopStalenessHandlerFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\View\CriteriaViewRegistry;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfo;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfoFactory;
class MeterProviderBuilder
{
    // @var array<MetricReaderInterface>
    private array $metricReaders = [];
    private ?ResourceInfo $resource = null;
    private ?ExemplarFilterInterface $exemplarFilter = null;
    private ?Configurator $configurator = null;
    private ?ClockInterface $clock = null;
    public function setResource(ResourceInfo $resource): self
    {
        $this->resource = $resource;
        return $this;
    }
    public function setExemplarFilter(ExemplarFilterInterface $exemplarFilter): self
    {
        $this->exemplarFilter = $exemplarFilter;
        return $this;
    }
    public function addReader(MetricReaderInterface $reader): self
    {
        $this->metricReaders[] = $reader;
        return $this;
    }
    public function setConfigurator(Configurator $configurator): self
    {
        $this->configurator = $configurator;
        return $this;
    }
    public function setClock(ClockInterface $clock): self
    {
        $this->clock = $clock;
        return $this;
    }
    /**
     * @psalm-suppress PossiblyInvalidArgument
     */
    public function build(): MeterProviderInterface
    {
        return new MeterProvider(null, $this->resource ?? ResourceInfoFactory::emptyResource(), $this->clock ?? Clock::getDefault(), Attributes::factory(), new InstrumentationScopeFactory(Attributes::factory()), $this->metricReaders, new CriteriaViewRegistry(), $this->exemplarFilter ?? new WithSampledTraceExemplarFilter(), new NoopStalenessHandlerFactory(), configurator: $this->configurator ?? Configurator::meter());
    }
}
