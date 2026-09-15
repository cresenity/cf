<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\API;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\Span as OtelSpan;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanInterface as OtelSpanInterface;
use Throwable;
/**
 * Small, stable public span handle (SPEC §28) — deliberately does not expose the
 * underlying OTel SpanInterface, so this package's OpenTelemetry dependency stays
 * an implementation detail an application never has to import itself.
 */
final class Span
{
    private function __construct(private readonly OtelSpanInterface $otelSpan)
    {
    }
    /**
     * @internal use Apm::startSpan()
     */
    public static function wrap(OtelSpanInterface $otelSpan): self
    {
        return new self($otelSpan);
    }
    /**
     * A span that does nothing — returned when the agent is disabled/uninitialized so
     * calling code never needs an `if (Apm::enabled())` guard around span usage.
     */
    public static function noop(): self
    {
        return new self(OtelSpan::getInvalid());
    }
    public function setAttribute(string $key, bool|int|float|string|null $value): self
    {
        $this->otelSpan->setAttribute($key, $value);
        return $this;
    }
    /**
     * @param array<string, bool|int|float|string|null> $attributes
     */
    public function addEvent(string $name, array $attributes = []): self
    {
        $this->otelSpan->addEvent($name, $attributes);
        return $this;
    }
    public function recordException(Throwable $exception): self
    {
        $this->otelSpan->recordException($exception);
        return $this;
    }
    public function end(): void
    {
        $this->otelSpan->end();
    }
}
/**
 * Small, stable public span handle (SPEC §28) — deliberately does not expose the
 * underlying OTel SpanInterface, so this package's OpenTelemetry dependency stays
 * an implementation detail an application never has to import itself.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\API\Span', 'Cresenity\DevCloud\APM\API\Span', \false);
