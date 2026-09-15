<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\View;

use Generator;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Instrument;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\ViewRegistryInterface;
final class CriteriaViewRegistry implements ViewRegistryInterface
{
    /** @var list<SelectionCriteriaInterface> */
    private array $criteria = [];
    /** @var list<ViewTemplate> */
    private array $views = [];
    public function register(SelectionCriteriaInterface $criteria, ViewTemplate $view): void
    {
        $this->criteria[] = $criteria;
        $this->views[] = $view;
    }
    /**
     * @todo is null the best return type here? what about empty array or exception?
     */
    #[\Override]
    public function find(Instrument $instrument, InstrumentationScopeInterface $instrumentationScope): ?iterable
    {
        $views = $this->generateViews($instrument, $instrumentationScope);
        return $views->valid() ? $views : null;
    }
    private function generateViews(Instrument $instrument, InstrumentationScopeInterface $instrumentationScope): Generator
    {
        foreach ($this->criteria as $i => $criteria) {
            if ($criteria->accepts($instrument, $instrumentationScope)) {
                yield $this->views[$i]->project($instrument);
            }
        }
    }
}
