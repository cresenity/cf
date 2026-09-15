<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics;

use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
/**
 * @internal
 */
interface AttributeProcessorInterface
{
    public function process(AttributesInterface $attributes, ContextInterface $context): AttributesInterface;
}
