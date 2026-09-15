<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Framework;

/**
 * A framework-specific integration (Laravel, Symfony, ...) that enriches the
 * generic HTTP span with framework context — route, controller, ORM, queue
 * (SPEC §13-§15, §44). Must never duplicate the HTTP server span the core
 * agent already creates.
 */
interface FrameworkIntegrationInterface
{
    /**
     * Short, stable identifier (e.g. "laravel", "symfony").
     */
    public function name(): string;
    /**
     * Whether this framework is actually present in the running application
     * (e.g. a marker class exists) — checked once at boot (SPEC §45).
     */
    public function isAvailable(): bool;
    /**
     * Attach this integration's hooks. Called at most once per process,
     * only when isAvailable() is true.
     */
    public function register(): void;
}
/**
 * A framework-specific integration (Laravel, Symfony, ...) that enriches the
 * generic HTTP span with framework context — route, controller, ORM, queue
 * (SPEC §13-§15, §44). Must never duplicate the HTTP server span the core
 * agent already creates.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Framework\FrameworkIntegrationInterface', 'Cresenity\DevCloud\APM\Framework\FrameworkIntegrationInterface', \false);
