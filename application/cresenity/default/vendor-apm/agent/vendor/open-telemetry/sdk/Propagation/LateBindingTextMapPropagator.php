<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Propagation;

use Closure;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\PropagationGetterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
/**
 * @internal
 */
final class LateBindingTextMapPropagator implements TextMapPropagatorInterface
{
    /**
     * @param TextMapPropagatorInterface|Closure(): TextMapPropagatorInterface $propagator
     */
    public function __construct(private TextMapPropagatorInterface|Closure $propagator)
    {
    }
    #[\Override]
    public function fields(): array
    {
        if (!$this->propagator instanceof TextMapPropagatorInterface) {
            $this->propagator = ($this->propagator)();
        }
        return $this->propagator->fields();
    }
    #[\Override]
    public function inject(mixed &$carrier, ?PropagationSetterInterface $setter = null, ?ContextInterface $context = null): void
    {
        if (!$this->propagator instanceof TextMapPropagatorInterface) {
            $this->propagator = ($this->propagator)();
        }
        $this->propagator->inject($carrier, $setter, $context);
    }
    #[\Override]
    public function extract($carrier, ?PropagationGetterInterface $getter = null, ?ContextInterface $context = null): ContextInterface
    {
        if (!$this->propagator instanceof TextMapPropagatorInterface) {
            $this->propagator = ($this->propagator)();
        }
        return $this->propagator->extract($carrier, $getter, $context);
    }
}
