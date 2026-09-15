<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\View\SelectionCriteria;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Instrument;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\View\SelectionCriteriaInterface;
final class InstrumentationScopeSchemaUrlCriteria implements SelectionCriteriaInterface
{
    public function __construct(private readonly ?string $schemaUrl)
    {
    }
    #[\Override]
    public function accepts(Instrument $instrument, InstrumentationScopeInterface $instrumentationScope): bool
    {
        return $this->schemaUrl === $instrumentationScope->getSchemaUrl();
    }
}
