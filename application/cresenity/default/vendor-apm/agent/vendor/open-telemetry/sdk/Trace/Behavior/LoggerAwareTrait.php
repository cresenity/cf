<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\Behavior;

use CresenityDevCloudAPMVendor\Psr\Log\LoggerAwareTrait as PsrTrait;
use CresenityDevCloudAPMVendor\Psr\Log\LoggerInterface;
use CresenityDevCloudAPMVendor\Psr\Log\LogLevel;
use CresenityDevCloudAPMVendor\Psr\Log\NullLogger;
trait LoggerAwareTrait
{
    use PsrTrait;
    private string $defaultLogLevel = LogLevel::INFO;
    public function setDefaultLogLevel(string $logLevel): void
    {
        $this->defaultLogLevel = $logLevel;
    }
    protected function log(string $message, array $context = [], ?string $level = null): void
    {
        $this->getLogger()->log($level ?? $this->defaultLogLevel, $message, $context);
    }
    protected function getLogger(): LoggerInterface
    {
        if ($this->logger !== null) {
            return $this->logger;
        }
        return new NullLogger();
    }
}
