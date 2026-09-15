<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor;

\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerTextMapPropagator(\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\KnownValues::VALUE_BAGGAGE, \CresenityDevCloudAPMVendor\OpenTelemetry\API\Baggage\Propagation\BaggagePropagator::getInstance());
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerTextMapPropagator(\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\KnownValues::VALUE_TRACECONTEXT, \CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\Propagation\TraceContextPropagator::getInstance());
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerTextMapPropagator(\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\KnownValues::VALUE_NONE, \CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\NoopTextMapPropagator::getInstance());
\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Registry::registerResponsePropagator(\CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\KnownValues::VALUE_NONE, \CresenityDevCloudAPMVendor\OpenTelemetry\Context\Propagation\NoopResponsePropagator::getInstance());
