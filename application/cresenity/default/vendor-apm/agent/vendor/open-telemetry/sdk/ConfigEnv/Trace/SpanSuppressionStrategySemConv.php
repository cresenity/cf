<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\ConfigEnv\Trace;

use CresenityDevCloudAPMVendor\Nevay\SPI\ServiceLoader;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoader;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoaderRegistry;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvResolver;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanSuppression\SemanticConventionResolver;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SemanticConventionSuppressionStrategy\SemanticConventionSuppressionStrategy;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SpanSuppressionStrategy;
use Override;
/**
 * @implements EnvComponentLoader<SpanSuppressionStrategy>
 */
final class SpanSuppressionStrategySemConv implements EnvComponentLoader
{
    #[Override]
    public function load(EnvResolver $env, EnvComponentLoaderRegistry $registry, Context $context): SpanSuppressionStrategy
    {
        return new SemanticConventionSuppressionStrategy(ServiceLoader::load(SemanticConventionResolver::class));
    }
    #[Override]
    public function name(): string
    {
        return 'semconv';
    }
}
