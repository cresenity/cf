<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\Context;

/**
 * @internal
 */
final class ContextStorageHead
{
    public ?ContextStorageNode $node = null;
    public function __construct(public ContextStorageHeadAware $storage)
    {
    }
}
