<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\Detectors;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Attribute\Attributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Configuration;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Variables;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfo;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Version;
/**
 * @see https://github.com/open-telemetry/semantic-conventions/tree/main/docs/resource#service-experimental
 */
final class Service implements ResourceDetectorInterface
{
    #[\Override]
    public function getResource(): ResourceInfo
    {
        $serviceName = Configuration::has(Variables::OTEL_SERVICE_NAME) ? Configuration::getString(Variables::OTEL_SERVICE_NAME) : null;
        $attributes = [ServiceAttributes::SERVICE_NAME => $serviceName];
        return ResourceInfo::create(Attributes::create($attributes), Version::VERSION_1_38_0->url());
    }
}
