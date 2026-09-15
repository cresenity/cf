<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace;

use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextKeys;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ScopeInterface;
abstract class Span implements SpanInterface
{
    private static ?self $invalidSpan = null;
    /** @inheritDoc */
    #[\Override]
    final public static function fromContext(ContextInterface $context): SpanInterface
    {
        return $context->get(ContextKeys::span()) ?? self::getInvalid();
    }
    /** @inheritDoc */
    #[\Override]
    final public static function getCurrent(): SpanInterface
    {
        return self::fromContext(Context::getCurrent());
    }
    /** @inheritDoc */
    #[\Override]
    final public static function getInvalid(): SpanInterface
    {
        if (null === self::$invalidSpan) {
            self::$invalidSpan = new NonRecordingSpan(SpanContext::getInvalid());
        }
        return self::$invalidSpan;
    }
    /** @inheritDoc */
    #[\Override]
    final public static function wrap(SpanContextInterface $spanContext): SpanInterface
    {
        if (!$spanContext->isValid()) {
            return self::getInvalid();
        }
        return new NonRecordingSpan($spanContext);
    }
    /** @inheritDoc */
    #[\Override]
    final public function activate(): ScopeInterface
    {
        return Context::getCurrent()->withContextValue($this)->activate();
    }
    /** @inheritDoc */
    #[\Override]
    public function storeInContext(ContextInterface $context): ContextInterface
    {
        if (LocalRootSpan::isLocalRoot($context)) {
            $context = LocalRootSpan::store($context, $this);
        }
        return $context->with(ContextKeys::span(), $this);
    }
}
