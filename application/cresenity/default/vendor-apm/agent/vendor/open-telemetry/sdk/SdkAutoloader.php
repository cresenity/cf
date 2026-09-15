<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK;

use function class_exists;
use CresenityDevCloudAPMVendor\Nevay\SPI\ServiceLoader;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Behavior\LogsMessagesTrait;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoader;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\ConfigProperties;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Globals;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation\ConfigurationRegistry;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation\Context as InstrumentationContext;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation\GeneralInstrumentationConfiguration;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation\HookManager;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation\HookManagerInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation\Instrumentation;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation\InstrumentationConfiguration;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\AutoInstrumentation\NoopHookManager;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Instrumentation\Configurator;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\LateBindingLoggerProvider;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Logs\LoggerProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\LateBindingMeterProvider;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Metrics\MeterProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\LateBindingTracerProvider;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\TracerProviderInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Config\SDK\Configuration as SdkConfiguration;
use CresenityDevCloudAPMVendor\OpenTelemetry\Config\SDK\Instrumentation as SdkInstrumentation;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\ResponsePropagatorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Configuration;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\EnvComponentLoaderRegistry;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\EnvResolver;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Variables;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Distribution\DistributionConfiguration;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Distribution\DistributionProperties;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Distribution\DistributionRegistry;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Distribution\SdkDistribution;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\EventLoggerProviderFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\LoggerProviderFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MeterProviderFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Propagation\LateBindingResponsePropagator;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Propagation\LateBindingTextMapPropagator;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Propagation\PropagatorFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Propagation\ResponsePropagatorFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\AutoRootSpan;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\ExporterFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SamplerFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanProcessorFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use RuntimeException;
use Throwable;
/**
 * @psalm-suppress RedundantCast
 */
class SdkAutoloader
{
    use LogsMessagesTrait;
    public static function autoload(): bool
    {
        if (!self::isEnabled() || self::isExcludedUrl()) {
            return \false;
        }
        if (Configuration::has(Variables::OTEL_CONFIG_FILE)) {
            if (!class_exists(SdkConfiguration::class)) {
                throw new RuntimeException('File-based configuration requires open-telemetry/sdk-configuration');
            }
            Globals::registerInitializer(fn($configurator) => self::fileBasedInitializer($configurator));
        } else {
            Globals::registerInitializer(fn($configurator) => self::environmentBasedInitializer($configurator));
        }
        self::registerInstrumentations();
        if (AutoRootSpan::isEnabled()) {
            $request = AutoRootSpan::createRequest();
            if ($request) {
                AutoRootSpan::create($request);
                AutoRootSpan::registerShutdownHandler();
            }
        }
        return \true;
    }
    /**
     * @phan-suppress PhanDeprecatedClass,PhanDeprecatedFunction
     */
    private static function environmentBasedInitializer(Configurator $configurator): Configurator
    {
        $propagator = (new PropagatorFactory())->create();
        $responsePropagator = (new ResponsePropagatorFactory())->create();
        if (Sdk::isDisabled()) {
            //@see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/configuration/sdk-environment-variables.md#general-sdk-configuration
            return $configurator->withPropagator($propagator)->withResponsePropagator($responsePropagator);
        }
        $emitMetrics = Configuration::getBoolean(Variables::OTEL_PHP_INTERNAL_METRICS_ENABLED);
        $distributionProperties = self::loadDistributionPropertiesFromEnv();
        $distributionConfiguration = $distributionProperties->getDistributionConfiguration(SdkDistribution::class) ?? new SdkDistribution();
        $resource = ResourceInfoFactory::defaultResource();
        $exporter = (new ExporterFactory())->create();
        $meterProvider = (new MeterProviderFactory())->create($resource);
        $spanProcessor = (new SpanProcessorFactory())->create($exporter, $emitMetrics ? $meterProvider : null);
        $builder = (new TracerProviderBuilder())->addSpanProcessor($spanProcessor)->setResource($resource)->setSampler((new SamplerFactory())->create())->setSpanSuppressionStrategy($distributionConfiguration->spanSuppressionStrategy);
        if ($emitMetrics) {
            $builder->setMeterProvider($meterProvider);
        }
        $tracerProvider = $builder->build();
        $loggerProvider = (new LoggerProviderFactory())->create($emitMetrics ? $meterProvider : null, $resource);
        $eventLoggerProvider = (new EventLoggerProviderFactory())->create($loggerProvider);
        ShutdownHandler::register($tracerProvider->shutdown(...));
        ShutdownHandler::register($meterProvider->shutdown(...));
        ShutdownHandler::register($loggerProvider->shutdown(...));
        return $configurator->withTracerProvider($tracerProvider)->withMeterProvider($meterProvider)->withLoggerProvider($loggerProvider)->withEventLoggerProvider($eventLoggerProvider)->withPropagator($propagator)->withResponsePropagator($responsePropagator);
    }
    /**
     * @phan-suppress PhanPossiblyUndeclaredVariable,PhanDeprecatedFunction
     */
    private static function fileBasedInitializer(Configurator $configurator): Configurator
    {
        $file = Configuration::getString(Variables::OTEL_CONFIG_FILE);
        $config = SdkConfiguration::parseFile($file);
        //disable hook manager during SDK to avoid autoinstrumenting SDK exporters.
        $scope = HookManager::disable(Context::getCurrent())->activate();
        try {
            $sdk = $config->create()->setAutoShutdown(\true)->build();
        } finally {
            $scope->detach();
        }
        return $configurator->withTracerProvider($sdk->getTracerProvider())->withMeterProvider($sdk->getMeterProvider())->withLoggerProvider($sdk->getLoggerProvider())->withPropagator($sdk->getPropagator())->withEventLoggerProvider($sdk->getEventLoggerProvider())->withResponsePropagator($sdk->getResponsePropagator());
    }
    /**
     * Register all {@link Instrumentation} configured through SPI
     * @psalm-suppress ArgumentTypeCoercion
     */
    private static function registerInstrumentations(): void
    {
        $files = Configuration::has(Variables::OTEL_CONFIG_FILE) ? Configuration::getList(Variables::OTEL_CONFIG_FILE) : [];
        if (class_exists(SdkInstrumentation::class) && $files) {
            $configuration = SdkInstrumentation::parseFile($files)->create();
        } else {
            $configuration = self::loadConfigPropertiesFromEnv();
        }
        $hookManager = self::getHookManager();
        $tracerProvider = self::createLateBindingTracerProvider();
        $meterProvider = self::createLateBindingMeterProvider();
        $loggerProvider = self::createLateBindingLoggerProvider();
        $propagator = self::createLateBindingTextMapPropagator();
        $responsePropagator = self::createLateBindingResponsePropagator();
        $context = new InstrumentationContext($tracerProvider, $meterProvider, $loggerProvider, $propagator, $responsePropagator);
        foreach (ServiceLoader::load(Instrumentation::class) as $instrumentation) {
            /** @var Instrumentation $instrumentation */
            try {
                $instrumentation->register($hookManager, $configuration, $context);
            } catch (Throwable $t) {
                self::logError(sprintf('Unable to load instrumentation: %s', $instrumentation::class), ['exception' => $t]);
            }
        }
    }
    private static function loadDistributionPropertiesFromEnv(): DistributionProperties
    {
        $loaderRegistry = new EnvComponentLoaderRegistry();
        foreach (ServiceLoader::load(EnvComponentLoader::class) as $loader) {
            $loaderRegistry->register($loader);
        }
        $env = new EnvResolver();
        $context = new \CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\Context();
        $configuration = new DistributionRegistry();
        foreach ($loaderRegistry->loadAll(DistributionConfiguration::class, $env, $context) as $distribution) {
            $configuration->add($distribution);
        }
        return $configuration;
    }
    private static function loadConfigPropertiesFromEnv(): ConfigProperties
    {
        $loaderRegistry = new EnvComponentLoaderRegistry();
        foreach (ServiceLoader::load(EnvComponentLoader::class) as $loader) {
            $loaderRegistry->register($loader);
        }
        $env = new EnvResolver();
        $context = new \CresenityDevCloudAPMVendor\OpenTelemetry\API\Configuration\Context();
        $configuration = new ConfigurationRegistry();
        foreach ($loaderRegistry->loadAll(GeneralInstrumentationConfiguration::class, $env, $context) as $instrumentation) {
            $configuration->add($instrumentation);
        }
        foreach ($loaderRegistry->loadAll(InstrumentationConfiguration::class, $env, $context) as $instrumentation) {
            $configuration->add($instrumentation);
        }
        return $configuration;
    }
    private static function createLateBindingTracerProvider(): TracerProviderInterface
    {
        return new LateBindingTracerProvider(static function (): TracerProviderInterface {
            $scope = Context::getRoot()->activate();
            try {
                return Globals::tracerProvider();
            } finally {
                $scope->detach();
            }
        });
    }
    private static function createLateBindingMeterProvider(): MeterProviderInterface
    {
        return new LateBindingMeterProvider(static function (): MeterProviderInterface {
            $scope = Context::getRoot()->activate();
            try {
                return Globals::meterProvider();
            } finally {
                $scope->detach();
            }
        });
    }
    private static function createLateBindingLoggerProvider(): LoggerProviderInterface
    {
        return new LateBindingLoggerProvider(static function (): LoggerProviderInterface {
            $scope = Context::getRoot()->activate();
            try {
                return Globals::loggerProvider();
            } finally {
                $scope->detach();
            }
        });
    }
    private static function createLateBindingTextMapPropagator(): TextMapPropagatorInterface
    {
        return new LateBindingTextMapPropagator(static function (): TextMapPropagatorInterface {
            $scope = Context::getRoot()->activate();
            try {
                return Globals::propagator();
            } finally {
                $scope->detach();
            }
        });
    }
    private static function createLateBindingResponsePropagator(): ResponsePropagatorInterface
    {
        return new LateBindingResponsePropagator(static function (): ResponsePropagatorInterface {
            $scope = Context::getRoot()->activate();
            try {
                return Globals::responsePropagator();
            } finally {
                $scope->detach();
            }
        });
    }
    private static function getHookManager(): HookManagerInterface
    {
        /** @var HookManagerInterface $hookManager */
        foreach (ServiceLoader::load(HookManagerInterface::class) as $hookManager) {
            return $hookManager;
        }
        return new NoopHookManager();
    }
    /**
     * @internal
     */
    public static function isEnabled(): bool
    {
        return Configuration::getBoolean(Variables::OTEL_PHP_AUTOLOAD_ENABLED);
    }
    /**
     * Test whether a request URI is set, and if it matches the excluded urls configuration option
     *
     * @internal
     */
    public static function isExcludedUrl(): bool
    {
        $excludedUrls = Configuration::getList(Variables::OTEL_PHP_EXCLUDED_URLS, []);
        if ($excludedUrls === []) {
            return \false;
        }
        $url = $_SERVER['REQUEST_URI'] ?? null;
        if (!$url) {
            return \false;
        }
        foreach ($excludedUrls as $excludedUrl) {
            if (preg_match(sprintf('|%s|', $excludedUrl), (string) $url) === 1) {
                return \true;
            }
        }
        return \false;
    }
}
