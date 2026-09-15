<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\Processor;

use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Future\CancellationInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\LogRecordProcessorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Logs\ReadWriteLogRecord;
class MultiLogRecordProcessor implements LogRecordProcessorInterface
{
    /** @var list<LogRecordProcessorInterface> */
    private array $processors = [];
    public function __construct(array $processors)
    {
        foreach ($processors as $processor) {
            assert($processor instanceof LogRecordProcessorInterface);
            $this->processors[] = $processor;
        }
    }
    #[\Override]
    public function onEmit(ReadWriteLogRecord $record, ?ContextInterface $context = null): void
    {
        foreach ($this->processors as $processor) {
            $processor->onEmit($record, $context);
        }
    }
    /**
     * Returns `true` if all processors shut down successfully, else `false`
     * Subsequent calls to `shutdown` are a no-op.
     */
    #[\Override]
    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        $result = \true;
        foreach ($this->processors as $processor) {
            if (!$processor->shutdown($cancellation)) {
                $result = \false;
            }
        }
        return $result;
    }
    /**
     * Returns `true` if all processors flush successfully, else `false`.
     */
    #[\Override]
    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        $result = \true;
        foreach ($this->processors as $processor) {
            if (!$processor->forceFlush($cancellation)) {
                $result = \false;
            }
        }
        return $result;
    }
}
