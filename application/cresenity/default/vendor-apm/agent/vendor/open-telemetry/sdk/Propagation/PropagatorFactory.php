<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Propagation;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Behavior\LogsMessagesTrait;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\NoopTextMapPropagator;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Configuration;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Variables;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry;
class PropagatorFactory
{
    use LogsMessagesTrait;
    public function create(): TextMapPropagatorInterface
    {
        $propagators = Configuration::getList(Variables::OTEL_PROPAGATORS);
        return match (count($propagators)) {
            0 => new NoopTextMapPropagator(),
            1 => $this->buildPropagator($propagators[0]),
            default => new MultiTextMapPropagator($this->buildPropagators($propagators)),
        };
    }
    /**
     * @return list<TextMapPropagatorInterface>
     */
    private function buildPropagators(array $names): array
    {
        $propagators = [];
        foreach ($names as $name) {
            $propagators[] = $this->buildPropagator($name);
        }
        return $propagators;
    }
    private function buildPropagator(string $name): TextMapPropagatorInterface
    {
        try {
            return Registry::textMapPropagator($name);
        } catch (\RuntimeException $e) {
            self::logWarning($e->getMessage());
        }
        return NoopTextMapPropagator::getInstance();
    }
}
