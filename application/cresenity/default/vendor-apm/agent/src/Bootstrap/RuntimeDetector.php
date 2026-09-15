<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Bootstrap;

/**
 * Cheap, memoized presence checks used by instrumentation/framework integrations to decide
 * `isAvailable()` (SPEC §42-§45) — never assume every application has every extension or
 * framework class loaded.
 */
final class RuntimeDetector
{
    /** @var array<string, bool> */
    private static array $extensionCache = [];
    /** @var array<string, bool> */
    private static array $classCache = [];
    public static function hasExtension(string $name): bool
    {
        return self::$extensionCache[$name] ??= extension_loaded($name);
    }
    public static function hasClass(string $class): bool
    {
        return self::$classCache[$class] ??= class_exists($class) || interface_exists($class);
    }
    public static function sapi(): string
    {
        return \PHP_SAPI;
    }
    public static function isCli(): bool
    {
        return \PHP_SAPI === 'cli';
    }
    /**
     * Test-only: clears the memoized detection cache.
     */
    public static function reset(): void
    {
        self::$extensionCache = [];
        self::$classCache = [];
    }
}
/**
 * Cheap, memoized presence checks used by instrumentation/framework integrations to decide
 * `isAvailable()` (SPEC §42-§45) — never assume every application has every extension or
 * framework class loaded.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Bootstrap\RuntimeDetector', 'Cresenity\DevCloud\APM\Bootstrap\RuntimeDetector', \false);
