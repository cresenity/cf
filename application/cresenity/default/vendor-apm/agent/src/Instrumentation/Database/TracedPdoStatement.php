<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Database;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\Clock;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use PDOStatement;
/**
 * Installed via `PDO::ATTR_STATEMENT_CLASS` by TracedPdo - traces the
 * `PDO::prepare()` -> `execute()` path. `PDO::query()` bypasses this class's
 * `execute()` entirely (verified empirically: PDO's internal query()
 * implementation does not call back into a custom statement subclass's
 * public `execute()`), so `TracedPdo::query()` traces that path separately -
 * this class only ever sees prepared-statement executions.
 */
class TracedPdoStatement extends PDOStatement
{
    protected function __construct(private readonly ?Tracer $tracer, private readonly ?Metrics $metrics, private readonly string $dbSystem, private readonly string $dsn)
    {
    }
    public function execute(?array $params = null): bool
    {
        if ($this->tracer === null) {
            return parent::execute($params);
        }
        // The prepared SQL text is placeholder-based by construction, so
        // including it cannot leak the caller's actual bound values.
        $attributes = PdoAttributes::forQuery($this->queryString, $this->dbSystem, $this->dsn, includeText: \true);
        $name = PdoAttributes::spanName((string) ($attributes['db.operation.name'] ?? ''));
        $start = (new Clock())->nowNanos();
        $result = $this->tracer->trace($name, fn() => parent::execute($params), $attributes, SpanKind::KIND_CLIENT);
        PdoAttributes::recordMetrics($this->metrics, $attributes, ((new Clock())->nowNanos() - $start) / 1000000);
        return $result;
    }
}
/**
 * Installed via `PDO::ATTR_STATEMENT_CLASS` by TracedPdo - traces the
 * `PDO::prepare()` -> `execute()` path. `PDO::query()` bypasses this class's
 * `execute()` entirely (verified empirically: PDO's internal query()
 * implementation does not call back into a custom statement subclass's
 * public `execute()`), so `TracedPdo::query()` traces that path separately -
 * this class only ever sees prepared-statement executions.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Database\TracedPdoStatement', 'Cresenity\DevCloud\APM\Instrumentation\Database\TracedPdoStatement', \false);
