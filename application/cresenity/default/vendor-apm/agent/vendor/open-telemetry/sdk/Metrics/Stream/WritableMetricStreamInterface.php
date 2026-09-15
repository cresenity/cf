<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\Stream;

use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
/**
 * @internal
 */
interface WritableMetricStreamInterface
{
    public function record(float|int $value, AttributesInterface $attributes, ContextInterface $context, int $timestamp): void;
}
