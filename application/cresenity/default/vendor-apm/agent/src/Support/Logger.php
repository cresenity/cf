<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support;

use CresenityDevCloudAPMVendor\Psr\Log\AbstractLogger;
use CresenityDevCloudAPMVendor\Psr\Log\LogLevel;
use Stringable;
/**
 * The agent's own internal logger — never the application's logger (SPEC §32:
 * the agent must not replace application logging). Writes to `error_log()` by
 * default so it works with zero configuration in any SAPI; an application may
 * inject its own PSR-3 logger through AgentConfig/Bootstrap instead.
 *
 * Defaults to WARNING in production per SPEC §36 — DEBUG is opt-in via
 * `DEVCLOUD_APM_DEBUG=true`, never logged by default, so the agent itself
 * never becomes a source of noisy production logs.
 */
final class Logger extends AbstractLogger
{
    private const LEVEL_ORDER = [LogLevel::DEBUG => 0, LogLevel::INFO => 1, LogLevel::NOTICE => 1, LogLevel::WARNING => 2, LogLevel::ERROR => 3, LogLevel::CRITICAL => 3, LogLevel::ALERT => 3, LogLevel::EMERGENCY => 3];
    public function __construct(private readonly string $minLevel = LogLevel::WARNING)
    {
    }
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $rank = self::LEVEL_ORDER[$level] ?? self::LEVEL_ORDER[LogLevel::WARNING];
        $minRank = self::LEVEL_ORDER[$this->minLevel] ?? self::LEVEL_ORDER[LogLevel::WARNING];
        if ($rank < $minRank) {
            return;
        }
        $suffix = $context === [] ? '' : ' ' . json_encode($context, \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR);
        error_log(sprintf('[devcloud-apm] [%s] %s%s', strtoupper((string) $level), $message, $suffix));
    }
}
/**
 * The agent's own internal logger — never the application's logger (SPEC §32:
 * the agent must not replace application logging). Writes to `error_log()` by
 * default so it works with zero configuration in any SAPI; an application may
 * inject its own PSR-3 logger through AgentConfig/Bootstrap instead.
 *
 * Defaults to WARNING in production per SPEC §36 — DEBUG is opt-in via
 * `DEVCLOUD_APM_DEBUG=true`, never logged by default, so the agent itself
 * never becomes a source of noisy production logs.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\Logger', 'Cresenity\DevCloud\APM\Support\Logger', \false);
