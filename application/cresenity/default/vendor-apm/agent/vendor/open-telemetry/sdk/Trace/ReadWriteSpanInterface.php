<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace;

use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace as API;
interface ReadWriteSpanInterface extends API\SpanInterface, ReadableSpanInterface
{
}
