<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\NoopSuppressionStrategy;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SpanSuppression;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SpanSuppressor;
/**
 * @internal
 */
final class NoopSuppressor implements SpanSuppressor
{
    #[\Override]
    public function resolveSuppression(int $spanKind, array $attributes): SpanSuppression
    {
        static $suppression = new NoopSuppression();
        return $suppression;
    }
}
