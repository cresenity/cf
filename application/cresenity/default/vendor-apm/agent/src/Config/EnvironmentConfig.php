<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config;

/**
 * Reads raw `DEVCLOUD_APM_*` environment variables with typed accessors.
 * Holds no defaults and no precedence logic — see ConfigLoader for that.
 */
final class EnvironmentConfig
{
    private const PREFIX = 'DEVCLOUD_APM_';
    /**
     * @param array<string, string> $source Injectable for tests; defaults to $_ENV + getenv().
     */
    public function __construct(private readonly array $source = [])
    {
    }
    public static function fromEnvironment(): self
    {
        $source = [];
        foreach ($_ENV as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $source[$key] = (string) $value;
            }
        }
        foreach ((array) getenv() as $key => $value) {
            if (is_string($value)) {
                $source[$key] = $value;
            }
        }
        return new self($source);
    }
    public function getString(string $name): ?string
    {
        $value = $this->raw($name);
        return $value === null ? null : $value;
    }
    public function getBool(string $name): ?bool
    {
        $value = $this->raw($name);
        if ($value === null) {
            return null;
        }
        return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], \true);
    }
    public function getFloat(string $name): ?float
    {
        $value = $this->raw($name);
        return $value === null || !is_numeric($value) ? null : (float) $value;
    }
    public function getInt(string $name): ?int
    {
        $value = $this->raw($name);
        return $value === null || !is_numeric($value) ? null : (int) $value;
    }
    /**
     * @return null|list<string> comma-separated list, trimmed, empty entries dropped
     */
    public function getList(string $name): ?array
    {
        $value = $this->raw($name);
        if ($value === null) {
            return null;
        }
        $items = array_filter(array_map('trim', explode(',', $value)), static fn($v) => $v !== '');
        return array_values($items);
    }
    private function raw(string $name): ?string
    {
        $key = self::PREFIX . $name;
        return $this->source[$key] ?? null;
    }
}
/**
 * Reads raw `DEVCLOUD_APM_*` environment variables with typed accessors.
 * Holds no defaults and no precedence logic — see ConfigLoader for that.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config\EnvironmentConfig', 'Cresenity\DevCloud\APM\Config\EnvironmentConfig', \false);
