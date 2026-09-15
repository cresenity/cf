<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\Detectors;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfo;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfoFactory;
final class Composite implements ResourceDetectorInterface
{
    /**
     * @param iterable<ResourceDetectorInterface> $resourceDetectors
     */
    public function __construct(private readonly iterable $resourceDetectors)
    {
    }
    #[\Override]
    public function getResource(): ResourceInfo
    {
        $resource = ResourceInfoFactory::mandatoryResource();
        foreach ($this->resourceDetectors as $resourceDetector) {
            $resource = $resource->merge($resourceDetector->getResource());
        }
        return $resource;
    }
}
