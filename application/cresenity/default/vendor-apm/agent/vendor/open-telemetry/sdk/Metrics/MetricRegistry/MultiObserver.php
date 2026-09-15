<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricRegistry;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\ObserverInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Attribute\AttributesFactoryInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Stream\WritableMetricStreamInterface;
/**
 * @internal
 */
final class MultiObserver implements ObserverInterface
{
    /** @var list<WritableMetricStreamInterface>  */
    public array $writers = [];
    public function __construct(private readonly AttributesFactoryInterface $attributesFactory, private readonly int $timestamp)
    {
    }
    #[\Override]
    public function observe($amount, iterable $attributes = []): void
    {
        $context = Context::getRoot();
        $attributes = $this->attributesFactory->builder($attributes)->build();
        foreach ($this->writers as $writer) {
            $writer->record($amount, $attributes, $context, $this->timestamp);
        }
    }
}
