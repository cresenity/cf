<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\CounterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\HistogramInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterInterface;
/**
 * The agent's metrics façade (SPEC §51) - the metrics counterpart to
 * `Tracing\Tracer`: everything else in this package records metrics through
 * this, never by reaching for the OTel API's MeterProvider directly.
 *
 * Instruments are created once and cached by name (creating the same named
 * instrument twice is wasteful, and the OTel spec expects a stable identity
 * per name/unit/description anyway).
 */
final class Metrics
{
    /** @var array<string, CounterInterface> */
    private array $counters = [];
    /** @var array<string, HistogramInterface> */
    private array $histograms = [];
    public function __construct(private readonly MeterInterface $meter)
    {
    }
    /**
     * @param array<string, bool|int|float|string|null> $attributes
     */
    public function count(string $name, int|float $amount = 1, array $attributes = [], ?string $unit = null, ?string $description = null): void
    {
        $this->counter($name, $unit, $description)->add($amount, $attributes);
    }
    /**
     * @param array<string, bool|int|float|string|null> $attributes
     */
    public function recordDuration(string $name, float $milliseconds, array $attributes = [], ?string $description = null): void
    {
        $this->histogram($name, 'ms', $description)->record($milliseconds, $attributes);
    }
    private function counter(string $name, ?string $unit, ?string $description): CounterInterface
    {
        return $this->counters[$name] ??= $this->meter->createCounter($name, $unit, $description);
    }
    private function histogram(string $name, ?string $unit, ?string $description): HistogramInterface
    {
        return $this->histograms[$name] ??= $this->meter->createHistogram($name, $unit, $description);
    }
}
/**
 * The agent's metrics façade (SPEC §51) - the metrics counterpart to
 * `Tracing\Tracer`: everything else in this package records metrics through
 * this, never by reaching for the OTel API's MeterProvider directly.
 *
 * Instruments are created once and cached by name (creating the same named
 * instrument twice is wasteful, and the OTel spec expects a stable identity
 * per name/unit/description anyway).
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics', 'Cresenity\DevCloud\APM\Metrics\Metrics', \false);
