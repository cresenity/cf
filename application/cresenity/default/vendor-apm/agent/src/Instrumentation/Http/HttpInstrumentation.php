<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Http;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\InstrumentationInterface;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
/**
 * Generic (framework-independent) incoming-HTTP-request instrumentation
 * (SPEC §11, Phase 2). Not applicable under CLI/phpdbg SAPIs - there is no
 * incoming request to trace there (CLI instrumentation is a separate,
 * later phase, SPEC §30).
 */
final class HttpInstrumentation implements InstrumentationInterface
{
    public function __construct(private readonly Tracer $tracer, private readonly ?Metrics $metrics = null)
    {
    }
    public function name(): string
    {
        return 'http';
    }
    public function isAvailable(): bool
    {
        return !in_array(\PHP_SAPI, ['cli', 'phpdbg'], \true);
    }
    public function register(): void
    {
        HttpServerSpan::start($this->tracer, $this->metrics);
    }
}
/**
 * Generic (framework-independent) incoming-HTTP-request instrumentation
 * (SPEC §11, Phase 2). Not applicable under CLI/phpdbg SAPIs - there is no
 * incoming request to trace there (CLI instrumentation is a separate,
 * later phase, SPEC §30).
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Http\HttpInstrumentation', 'Cresenity\DevCloud\APM\Instrumentation\Http\HttpInstrumentation', \false);
