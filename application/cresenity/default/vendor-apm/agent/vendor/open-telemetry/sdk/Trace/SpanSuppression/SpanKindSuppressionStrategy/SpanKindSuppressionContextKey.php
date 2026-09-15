<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanSuppression\SpanKindSuppressionStrategy;

use CresenityDevCloudAPMVendor\OpenTelemetry\Context\ContextKeyInterface;
/**
 * @implements ContextKeyInterface<true>
 *
 * @internal
 */
enum SpanKindSuppressionContextKey implements \OpenTelemetry\Context\ContextKeyInterface
{
    case Client;
    case Server;
    case Producer;
    case Consumer;
}
