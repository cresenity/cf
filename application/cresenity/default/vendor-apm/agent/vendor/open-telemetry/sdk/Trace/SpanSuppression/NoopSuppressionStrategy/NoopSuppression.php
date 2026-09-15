<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\NoopSuppressionStrategy;

use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SpanSuppression;
/**
 * @internal
 */
final class NoopSuppression implements SpanSuppression
{
    #[\Override]
    public function isSuppressed(ContextInterface $context): bool
    {
        return \false;
    }
    #[\Override]
    public function suppress(ContextInterface $context): ContextInterface
    {
        return $context;
    }
}
