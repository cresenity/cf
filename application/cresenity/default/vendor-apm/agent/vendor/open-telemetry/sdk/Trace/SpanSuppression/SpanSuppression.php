<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression;

use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
/**
 * @experimental
 */
interface SpanSuppression
{
    public function isSuppressed(ContextInterface $context): bool;
    public function suppress(ContextInterface $context): ContextInterface;
}
