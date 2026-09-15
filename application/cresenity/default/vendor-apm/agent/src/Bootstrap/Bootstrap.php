<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Bootstrap;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config\AgentConfig;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export\BatchProcessor;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export\OtlpExporter;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Export\OtlpMetricExporter;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Cli\CliInstrumentation;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Exception\ExceptionInstrumentation;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Http\HttpInstrumentation;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\InstrumentationRegistry;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Native\NativeBridge;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Resource\ServiceResource;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Sampling\ParentBasedSampler;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Sampling\ProbabilitySampler;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\Logger;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfo;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use CresenityDevCloudAPMVendor\Psr\Log\LoggerInterface;
use Throwable;
/**
 * Wires configuration into a working Tracer/Metrics pair (SPEC §7's lifecycle, up through
 * "Register instrumentation" — instrumentation itself is a later phase).
 *
 * Never throws: any failure here is caught and logged, and the corresponding half of the
 * result stays null so the caller runs with that capability off rather than the application
 * failing to start (SPEC §7, §35). Tracing and metrics fail independently of each other -
 * a broken metrics endpoint must not also disable tracing, and vice versa.
 */
final class Bootstrap
{
    public static function boot(AgentConfig $config, ?LoggerInterface $logger = null): BootResult
    {
        $logger ??= new Logger();
        if (!$config->enabled) {
            return BootResult::empty();
        }
        $resource = null;
        try {
            $resource = ServiceResource::fromConfig($config);
        } catch (Throwable $exception) {
            $logger->error('devcloud-apm: failed to build the service resource, tracing and metrics stay off', ['exception' => $exception->getMessage()]);
            return BootResult::empty();
        }
        // Metrics first: instrumentation registered while booting the tracer (below)
        // records into both, so it needs $metrics already built, even though it may
        // turn out to be null (metrics are opt-in - see bootMetrics()).
        $meterProvider = null;
        $metrics = self::bootMetrics($config, $resource, $logger, $meterProvider);
        $tracerProvider = null;
        $tracer = self::bootTracer($config, $resource, $metrics, $logger, $tracerProvider);
        // Registered last, on purpose: a stateless PHP web/CLI process has no other
        // moment to export a BatchSpanProcessor's/ExportingReader's queue - there is
        // no background thread and no next request to "catch up" on this one's
        // buffered telemetry. Shutdown functions run in registration order, and
        // HttpServerSpan/CliCommandSpan/ExceptionInstrumentation already registered
        // theirs above (inside bootTracer()'s registerAll()), so by the time this
        // one runs every span is ended and every metric recorded - flushing any
        // earlier would export an incomplete request.
        if ($tracerProvider !== null || $meterProvider !== null) {
            register_shutdown_function(static function () use ($tracerProvider, $meterProvider, $logger): void {
                if ($tracerProvider !== null) {
                    try {
                        $tracerProvider->shutdown();
                    } catch (Throwable $exception) {
                        $logger->warning('devcloud-apm: failed to flush spans on shutdown', ['exception' => $exception->getMessage()]);
                    }
                }
                if ($meterProvider !== null) {
                    try {
                        $meterProvider->shutdown();
                    } catch (Throwable $exception) {
                        $logger->warning('devcloud-apm: failed to flush metrics on shutdown', ['exception' => $exception->getMessage()]);
                    }
                }
            });
        }
        return new BootResult($tracer, $metrics);
    }
    private static function bootTracer(AgentConfig $config, ResourceInfo $resource, ?Metrics $metrics, LoggerInterface $logger, mixed &$tracerProvider): ?Tracer
    {
        if ($config->otlpEndpoint === null || $config->otlpEndpoint === '') {
            $logger->warning('devcloud-apm: enabled but no OTLP endpoint configured, tracing stays off');
            return null;
        }
        try {
            $sampler = new ParentBasedSampler(new ProbabilitySampler($config->sampleRate));
            $exporter = new OtlpExporter($config->otlpEndpoint, $config->apiKey);
            $processor = BatchProcessor::build($exporter, $config);
            $tracerProvider = (new TracerProviderBuilder())->addSpanProcessor($processor)->setResource($resource)->setSampler($sampler->toOtelSampler())->build();
            $tracer = new Tracer($tracerProvider->getTracer('devcloud/apm-agent'));
            $registry = new InstrumentationRegistry();
            if ($config->captureExceptions) {
                // Registered before HttpInstrumentation/CliInstrumentation: its shutdown
                // function must run first so it can still record onto a span that
                // HttpServerSpan/CliCommandSpan has not closed yet (see their docblocks).
                $registry->add(new ExceptionInstrumentation());
            }
            if ($config->captureHttp) {
                $registry->add(new HttpInstrumentation($tracer, $metrics));
            }
            if ($config->captureCli) {
                $registry->add(new CliInstrumentation($tracer));
            }
            $registry->registerAll($logger);
            // Automatic (engine-level) instrumentation, when the devcloud_apm
            // extension is loaded - additive, and a no-op without it, so the
            // manual TracedPdo/TracedRedis path stays the fallback rather than
            // being replaced (SPEC §4/§67).
            NativeBridge::register($config, $tracer, $metrics);
            return $tracer;
        } catch (Throwable $exception) {
            $logger->error('devcloud-apm: failed to initialize tracing, tracing stays off', ['exception' => $exception->getMessage()]);
            $tracerProvider = null;
            return null;
        }
    }
    private static function bootMetrics(AgentConfig $config, ResourceInfo $resource, LoggerInterface $logger, mixed &$meterProvider): ?Metrics
    {
        if ($config->metricsEndpoint === null || $config->metricsEndpoint === '') {
            // Metrics are opt-in on top of tracing (SPEC §51: "after traces are stable") -
            // silently staying off with no endpoint configured is the expected default,
            // not a warning-worthy misconfiguration the way a missing trace endpoint is.
            return null;
        }
        try {
            $exporter = new OtlpMetricExporter($config->metricsEndpoint, $config->apiKey);
            $reader = new ExportingReader($exporter->toOtelExporter());
            $meterProvider = (new MeterProviderBuilder())->setResource($resource)->addReader($reader)->build();
            return new Metrics($meterProvider->getMeter('devcloud/apm-agent'));
        } catch (Throwable $exception) {
            $logger->error('devcloud-apm: failed to initialize metrics, metrics stay off', ['exception' => $exception->getMessage()]);
            $meterProvider = null;
            return null;
        }
    }
}
/**
 * Wires configuration into a working Tracer/Metrics pair (SPEC §7's lifecycle, up through
 * "Register instrumentation" — instrumentation itself is a later phase).
 *
 * Never throws: any failure here is caught and logged, and the corresponding half of the
 * result stays null so the caller runs with that capability off rather than the application
 * failing to start (SPEC §7, §35). Tracing and metrics fail independently of each other -
 * a broken metrics endpoint must not also disable tracing, and vice versa.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Bootstrap\Bootstrap', 'Cresenity\DevCloud\APM\Bootstrap\Bootstrap', \false);
