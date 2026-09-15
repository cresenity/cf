<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanProcessor;

use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Future\CancellationInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\ReadableSpanInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\ReadWriteSpanInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanProcessorInterface;
class NoopSpanProcessor implements SpanProcessorInterface
{
    private static ?SpanProcessorInterface $instance = null;
    public static function getInstance(): SpanProcessorInterface
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /** @inheritDoc */
    #[\Override]
    public function onStart(ReadWriteSpanInterface $span, ContextInterface $parentContext): void
    {
    }
    //@codeCoverageIgnore
    /** @inheritDoc */
    #[\Override]
    public function onEnd(ReadableSpanInterface $span): void
    {
    }
    //@codeCoverageIgnore
    /** @inheritDoc */
    #[\Override]
    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return \true;
    }
    /** @inheritDoc */
    #[\Override]
    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return $this->forceFlush();
    }
}
