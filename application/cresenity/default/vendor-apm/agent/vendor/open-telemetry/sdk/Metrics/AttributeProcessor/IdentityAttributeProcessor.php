<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\AttributeProcessor;

use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\AttributeProcessorInterface;
/**
 * @internal
 */
final class IdentityAttributeProcessor implements AttributeProcessorInterface
{
    #[\Override]
    public function process(AttributesInterface $attributes, ContextInterface $context): AttributesInterface
    {
        return $attributes;
    }
}
