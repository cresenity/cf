<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Instrumentation;

interface InstrumentationScopeFactoryInterface
{
    public function create(string $name, ?string $version = null, ?string $schemaUrl = null, iterable $attributes = []): InstrumentationScopeInterface;
}
