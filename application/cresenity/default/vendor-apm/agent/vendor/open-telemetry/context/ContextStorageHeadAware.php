<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\Context;

/**
 * @internal
 */
interface ContextStorageHeadAware
{
    public function head(): ?ContextStorageHead;
}
