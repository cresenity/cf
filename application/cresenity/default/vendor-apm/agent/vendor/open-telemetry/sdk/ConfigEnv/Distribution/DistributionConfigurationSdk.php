<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\ConfigEnv\Distribution;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoader;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoaderRegistry;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvResolver;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Variables;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Distribution\DistributionConfiguration;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Distribution\SdkDistribution;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\NoopSuppressionStrategy\NoopSuppressionStrategy;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SpanSuppressionStrategy;
use Override;
/**
 * @implements EnvComponentLoader<DistributionConfiguration>
 */
final class DistributionConfigurationSdk implements EnvComponentLoader
{
    #[Override]
    public function load(EnvResolver $env, EnvComponentLoaderRegistry $registry, Context $context): DistributionConfiguration
    {
        $spanSuppressionStrategyName = $env->string(Variables::OTEL_EXPERIMENTAL_SPAN_SUPPRESSION_STRATEGY) ?? 'none';
        return new SdkDistribution(spanSuppressionStrategy: match ($spanSuppressionStrategyName) {
            'none' => new NoopSuppressionStrategy(),
            default => $registry->load(SpanSuppressionStrategy::class, $spanSuppressionStrategyName, $env, $context),
        });
    }
    #[Override]
    public function name(): string
    {
        return self::class;
    }
}
