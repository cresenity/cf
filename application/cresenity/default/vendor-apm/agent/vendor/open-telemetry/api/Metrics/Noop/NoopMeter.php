<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\Noop;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\AsynchronousInstrument;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\CounterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\GaugeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\HistogramInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObservableCallbackInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObservableCounterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObservableUpDownCounterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\UpDownCounterInterface;
final class NoopMeter implements MeterInterface
{
    #[\Override]
    public function batchObserve(callable $callback, AsynchronousInstrument $instrument, AsynchronousInstrument ...$instruments): ObservableCallbackInterface
    {
        return new NoopObservableCallback();
    }
    #[\Override]
    public function createCounter(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): CounterInterface
    {
        return new NoopCounter();
    }
    #[\Override]
    public function createObservableCounter(string $name, ?string $unit = null, ?string $description = null, $advisory = [], callable ...$callbacks): ObservableCounterInterface
    {
        return new NoopObservableCounter();
    }
    #[\Override]
    public function createHistogram(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): HistogramInterface
    {
        return new NoopHistogram();
    }
    #[\Override]
    public function createGauge(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): GaugeInterface
    {
        return new NoopGauge();
    }
    #[\Override]
    public function createObservableGauge(string $name, ?string $unit = null, ?string $description = null, $advisory = [], callable ...$callbacks): ObservableGaugeInterface
    {
        return new NoopObservableGauge();
    }
    #[\Override]
    public function createUpDownCounter(string $name, ?string $unit = null, ?string $description = null, array $advisory = []): UpDownCounterInterface
    {
        return new NoopUpDownCounter();
    }
    #[\Override]
    public function createObservableUpDownCounter(string $name, ?string $unit = null, ?string $description = null, $advisory = [], callable ...$callbacks): ObservableUpDownCounterInterface
    {
        return new NoopObservableUpDownCounter();
    }
}
