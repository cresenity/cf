<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Cli;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\InstrumentationInterface;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
/**
 * Generic (framework-independent) CLI-process instrumentation (SPEC §30).
 * The mirror image of HttpInstrumentation: available only under the CLI/phpdbg
 * SAPIs, never both at once for the same process.
 */
final class CliInstrumentation implements InstrumentationInterface
{
    public function __construct(private readonly Tracer $tracer)
    {
    }
    public function name(): string
    {
        return 'cli';
    }
    public function isAvailable(): bool
    {
        return in_array(\PHP_SAPI, ['cli', 'phpdbg'], \true);
    }
    public function register(): void
    {
        CliCommandSpan::start($this->tracer);
    }
}
/**
 * Generic (framework-independent) CLI-process instrumentation (SPEC §30).
 * The mirror image of HttpInstrumentation: available only under the CLI/phpdbg
 * SAPIs, never both at once for the same process.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Cli\CliInstrumentation', 'Cresenity\DevCloud\APM\Instrumentation\Cli\CliInstrumentation', \false);
