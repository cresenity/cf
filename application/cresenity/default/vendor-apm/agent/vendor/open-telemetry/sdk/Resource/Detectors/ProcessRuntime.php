<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\Detectors;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfo;
/**
 * @deprecated Use Process detector instead.
 */
final class ProcessRuntime implements ResourceDetectorInterface
{
    #[\Override]
    public function getResource(): ResourceInfo
    {
        return ResourceInfo::emptyResource();
    }
}
