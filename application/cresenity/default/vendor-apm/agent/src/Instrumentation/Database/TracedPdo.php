<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Database;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Agent;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\Clock;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use PDO;
use PDOStatement;
/**
 * Manual PDO integration (SPEC §16) - swap `new PDO(...)` for
 * `new TracedPdo(...)` at the connection site; every query afterwards is
 * traced with no further code changes.
 *
 * This is the fallback path, not the primary one from SPEC §4/§67: the
 * ideal is automatic instrumentation via `ext-opentelemetry` +
 * `open-telemetry/opentelemetry-auto-pdo`, which needs no connection-site
 * change at all. That extension is not something this package can assume is
 * installed (SPEC §4 - "do not assume every application has every extension"),
 * so this class exists for the common case where it is not. The two are not
 * mutually exclusive; a future release can prefer the extension when present
 * and fall back to this class otherwise.
 */
class TracedPdo extends PDO
{
    private readonly ?Tracer $tracer;
    private readonly ?Metrics $metrics;
    private readonly string $dbSystem;
    private readonly string $dsn;
    /**
     * @param array<int, mixed>|null $options
     */
    public function __construct(string $dsn, ?string $username = null, #[\SensitiveParameter] ?string $password = null, ?array $options = null, ?Tracer $tracer = null, ?Metrics $metrics = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->tracer = $tracer ?? Agent::tracer();
        $this->metrics = $metrics ?? Agent::metrics();
        $this->dbSystem = PdoAttributes::systemFromDsn($dsn);
        $this->dsn = $dsn;
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [TracedPdoStatement::class, [$this->tracer, $this->metrics, $this->dbSystem, $this->dsn]]);
    }
    /**
     * @param mixed ...$fetchModeArgs
     */
    public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs): PDOStatement|false
    {
        if ($this->tracer === null) {
            return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
        }
        // query()'s own SQL text may carry inlined literal values (there is
        // no separate bind step), so it is never included (SPEC §16, §33).
        $attributes = PdoAttributes::forQuery($query, $this->dbSystem, $this->dsn, includeText: \false);
        $name = PdoAttributes::spanName((string) ($attributes['db.operation.name'] ?? ''));
        $start = (new Clock())->nowNanos();
        $result = $this->tracer->trace($name, fn() => $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs), $attributes, SpanKind::KIND_CLIENT);
        PdoAttributes::recordMetrics($this->metrics, $attributes, ((new Clock())->nowNanos() - $start) / 1000000);
        return $result;
    }
    public function exec(string $statement): int|false
    {
        if ($this->tracer === null) {
            return parent::exec($statement);
        }
        $attributes = PdoAttributes::forQuery($statement, $this->dbSystem, $this->dsn, includeText: \false);
        $name = PdoAttributes::spanName((string) ($attributes['db.operation.name'] ?? ''));
        $start = (new Clock())->nowNanos();
        $result = $this->tracer->trace($name, fn() => parent::exec($statement), $attributes, SpanKind::KIND_CLIENT);
        PdoAttributes::recordMetrics($this->metrics, $attributes, ((new Clock())->nowNanos() - $start) / 1000000);
        return $result;
    }
}
/**
 * Manual PDO integration (SPEC §16) - swap `new PDO(...)` for
 * `new TracedPdo(...)` at the connection site; every query afterwards is
 * traced with no further code changes.
 *
 * This is the fallback path, not the primary one from SPEC §4/§67: the
 * ideal is automatic instrumentation via `ext-opentelemetry` +
 * `open-telemetry/opentelemetry-auto-pdo`, which needs no connection-site
 * change at all. That extension is not something this package can assume is
 * installed (SPEC §4 - "do not assume every application has every extension"),
 * so this class exists for the common case where it is not. The two are not
 * mutually exclusive; a future release can prefer the extension when present
 * and fall back to this class otherwise.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Database\TracedPdo', 'Cresenity\DevCloud\APM\Instrumentation\Database\TracedPdo', \false);
