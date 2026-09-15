<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanContextInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SpanSuppression;
use Override;
use Throwable;
/**
 * @internal
 */
final class NonRecordingSpan extends \CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\Span
{
    public function __construct(private readonly SpanContextInterface $spanContext, private readonly SpanSuppression $spanSuppression)
    {
    }
    #[Override]
    public function storeInContext(ContextInterface $context): ContextInterface
    {
        return $this->spanSuppression->suppress(parent::storeInContext($context));
    }
    #[Override]
    public function getContext(): SpanContextInterface
    {
        return $this->spanContext;
    }
    #[Override]
    public function isRecording(): bool
    {
        return \false;
    }
    #[Override]
    public function setAttribute(string $key, float|array|bool|int|string|null $value): SpanInterface
    {
        return $this;
    }
    #[Override]
    public function setAttributes(iterable $attributes): SpanInterface
    {
        return $this;
    }
    #[Override]
    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanInterface
    {
        return $this;
    }
    #[Override]
    public function addEvent(string $name, iterable $attributes = [], ?int $timestamp = null): SpanInterface
    {
        return $this;
    }
    #[Override]
    public function recordException(Throwable $exception, iterable $attributes = []): SpanInterface
    {
        return $this;
    }
    #[Override]
    public function updateName(string $name): SpanInterface
    {
        return $this;
    }
    #[Override]
    public function setStatus(string $code, ?string $description = null): SpanInterface
    {
        return $this;
    }
    #[Override]
    public function end(?int $endEpochNanos = null): void
    {
        // no-op
    }
}
