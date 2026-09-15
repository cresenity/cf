<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Database;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Metrics\Metrics;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\DbAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\ServerAttributes;
/**
 * Builds span name/attributes for a SQL statement (SPEC §16). Never includes
 * the full query text for a statement that might carry inlined literal
 * values (`PDO::query()`/`PDO::exec()`) - only `PDO::prepare()`-originated
 * statements do, because a prepared statement's SQL text is placeholder-based
 * by construction and does not carry the caller's actual values.
 */
final class PdoAttributes
{
    /** @var array<string, string> DSN driver prefix -> db.system.name value */
    private const SYSTEM_MAP = ['mysql' => DbAttributes::DB_SYSTEM_NAME_VALUE_MYSQL, 'pgsql' => DbAttributes::DB_SYSTEM_NAME_VALUE_POSTGRESQL, 'sqlsrv' => DbAttributes::DB_SYSTEM_NAME_VALUE_MICROSOFT_SQL_SERVER, 'dblib' => DbAttributes::DB_SYSTEM_NAME_VALUE_MICROSOFT_SQL_SERVER];
    public static function systemFromDsn(string $dsn): string
    {
        $driver = explode(':', $dsn, 2)[0];
        // No semconv-defined value for this driver yet (e.g. sqlite, oci) -
        // the plain driver name is still a reasonable, forward-compatible
        // fallback (SPEC §58: use a standard value where one exists).
        return self::SYSTEM_MAP[$driver] ?? $driver;
    }
    /**
     * @return array{0: string|null, 1: int|null} [server.address, server.port]
     */
    public static function serverFromDsn(string $dsn): array
    {
        $params = explode(':', $dsn, 2)[1] ?? '';
        $host = null;
        $port = null;
        foreach (explode(';', $params) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);
            if ($key === 'host') {
                $host = $value;
            } elseif ($key === 'port' && $value !== null && is_numeric($value)) {
                $port = (int) $value;
            }
        }
        return [$host, $port];
    }
    /**
     * @return array<string, bool|int|string|null>
     */
    public static function forQuery(string $query, string $dbSystem, string $dsn, bool $includeText): array
    {
        [$host, $port] = self::serverFromDsn($dsn);
        $operation = self::operationName($query);
        return array_filter([DbAttributes::DB_SYSTEM_NAME => $dbSystem, DbAttributes::DB_OPERATION_NAME => $operation, DbAttributes::DB_QUERY_TEXT => $includeText ? $query : null, ServerAttributes::SERVER_ADDRESS => $host, ServerAttributes::SERVER_PORT => $port], static fn($value) => $value !== null);
    }
    public static function spanName(string $operation): string
    {
        return $operation === '' ? 'DB' : 'DB ' . $operation;
    }
    /**
     * Records `database.query.count`/`database.query.duration` (SPEC §51). Deliberately
     * strips `db.query.text` even when the caller included it for the span - a metric
     * label carrying full SQL text would explode cardinality on the metrics backend
     * (SPEC §57), which a span attribute does not do the same way.
     *
     * @param array<string, bool|int|string|null> $spanAttributes
     */
    public static function recordMetrics(?Metrics $metrics, array $spanAttributes, float $durationMs): void
    {
        if ($metrics === null) {
            return;
        }
        unset($spanAttributes[DbAttributes::DB_QUERY_TEXT]);
        $metrics->count('database.query.count', 1, $spanAttributes);
        $metrics->recordDuration('database.query.duration', $durationMs, $spanAttributes);
    }
    /**
     * First keyword of the statement, uppercased (SELECT/INSERT/UPDATE/...) -
     * never the full text, so this alone cannot leak an inlined literal.
     */
    private static function operationName(string $query): string
    {
        if (preg_match('/^\s*([a-zA-Z]+)/', $query, $match) !== 1) {
            return '';
        }
        return strtoupper($match[1]);
    }
}
/**
 * Builds span name/attributes for a SQL statement (SPEC §16). Never includes
 * the full query text for a statement that might carry inlined literal
 * values (`PDO::query()`/`PDO::exec()`) - only `PDO::prepare()`-originated
 * statements do, because a prepared statement's SQL text is placeholder-based
 * by construction and does not carry the caller's actual values.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Database\PdoAttributes', 'Cresenity\DevCloud\APM\Instrumentation\Database\PdoAttributes', \false);
