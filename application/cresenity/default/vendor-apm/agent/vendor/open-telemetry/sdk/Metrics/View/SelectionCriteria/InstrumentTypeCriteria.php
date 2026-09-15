<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\View\SelectionCriteria;

use function in_array;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Instrument;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\InstrumentType;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\View\SelectionCriteriaInterface;
final class InstrumentTypeCriteria implements SelectionCriteriaInterface
{
    private readonly array $instrumentTypes;
    /**
     * @param string|InstrumentType|string[]|InstrumentType[] $instrumentType
     */
    public function __construct($instrumentType)
    {
        $this->instrumentTypes = (array) $instrumentType;
    }
    #[\Override]
    public function accepts(Instrument $instrument, InstrumentationScopeInterface $instrumentationScope): bool
    {
        return in_array($instrument->type, $this->instrumentTypes, \true);
    }
}
