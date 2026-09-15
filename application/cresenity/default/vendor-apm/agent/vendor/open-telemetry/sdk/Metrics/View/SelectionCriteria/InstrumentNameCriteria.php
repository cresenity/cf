<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\View\SelectionCriteria;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Instrument;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\View\SelectionCriteriaInterface;
use function preg_match;
use function preg_quote;
use function sprintf;
use function strtr;
final class InstrumentNameCriteria implements SelectionCriteriaInterface
{
    private readonly string $pattern;
    /**
     * @param non-empty-string $name
     */
    public function __construct(string $name)
    {
        $this->pattern = sprintf('/^%s$/', strtr(preg_quote($name, '/'), ['\?' => '.', '\*' => '.*']));
    }
    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    #[\Override]
    public function accepts(Instrument $instrument, InstrumentationScopeInterface $instrumentationScope): bool
    {
        return (bool) preg_match($this->pattern, $instrument->name);
    }
}
