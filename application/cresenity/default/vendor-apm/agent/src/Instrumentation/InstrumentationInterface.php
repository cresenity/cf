<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation;

/**
 * A generic (framework-independent) instrumentation target — HTTP, PDO, Redis,
 * curl, Guzzle, etc. (SPEC §42-§43). Concrete implementations land in later
 * phases; only the contract is defined for now.
 */
interface InstrumentationInterface
{
    /**
     * Short, stable identifier (e.g. "pdo", "redis") — used in config/logs, not shown to end users.
     */
    public function name(): string;
    /**
     * Whether this instrumentation's dependencies (extension, class, etc.) are present.
     * The InstrumentationRegistry only registers instrumentation that reports true here,
     * so an agent never fails to boot just because one optional extension is missing.
     */
    public function isAvailable(): bool;
    /**
     * Attach this instrumentation's hooks. Called at most once per process.
     */
    public function register(): void;
}
/**
 * A generic (framework-independent) instrumentation target — HTTP, PDO, Redis,
 * curl, Guzzle, etc. (SPEC §42-§43). Concrete implementations land in later
 * phases; only the contract is defined for now.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\InstrumentationInterface', 'Cresenity\DevCloud\APM\Instrumentation\InstrumentationInterface', \false);
