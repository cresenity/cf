<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource;

interface ResourceDetectorInterface
{
    public function getResource(): ResourceInfo;
}
