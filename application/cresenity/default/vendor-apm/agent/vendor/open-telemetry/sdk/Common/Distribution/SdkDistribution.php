<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Distribution;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\NoopSuppressionStrategy\NoopSuppressionStrategy;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SpanSuppressionStrategy;
final class SdkDistribution implements DistributionConfiguration
{
    public function __construct(public readonly SpanSuppressionStrategy $spanSuppressionStrategy = new NoopSuppressionStrategy())
    {
    }
}
