<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\ConfigEnv\Trace;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoader;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoaderRegistry;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvResolver;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SpanKindSuppressionStrategy\SpanKindSuppressionStrategy;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SpanSuppressionStrategy;
use Override;
/**
 * @implements EnvComponentLoader<SpanSuppressionStrategy>
 */
final class SpanSuppressionStrategySpanKind implements EnvComponentLoader
{
    #[Override]
    public function load(EnvResolver $env, EnvComponentLoaderRegistry $registry, Context $context): SpanSuppressionStrategy
    {
        return new SpanKindSuppressionStrategy();
    }
    #[Override]
    public function name(): string
    {
        return 'spankind';
    }
}
