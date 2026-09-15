<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporter;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\Behavior\SpanExporterDecoratorTrait;
abstract class AbstractDecorator
{
    use SpanExporterDecoratorTrait;
}
