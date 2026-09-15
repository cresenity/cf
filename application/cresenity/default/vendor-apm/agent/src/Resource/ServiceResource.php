<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Resource;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Config\AgentConfig;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\IdGenerator;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Attribute\Attributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfo;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\DeploymentAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Attributes\ServiceAttributes;
/**
 * Builds the OTel ResourceInfo identifying this process (SPEC §10).
 *
 * `service.instance.id` falls back to a random id per process — stable for the lifetime of
 * one PHP process, which is enough to distinguish concurrent instances of the same service
 * without depending on any specific deployment platform.
 */
final class ServiceResource
{
    public static function fromConfig(AgentConfig $config): ResourceInfo
    {
        $explicit = ResourceInfo::create(Attributes::create(array_filter([ServiceAttributes::SERVICE_NAME => $config->serviceName, ServiceAttributes::SERVICE_VERSION => $config->serviceVersion, ServiceAttributes::SERVICE_INSTANCE_ID => IdGenerator::hex(8), DeploymentAttributes::DEPLOYMENT_ENVIRONMENT_NAME => $config->environment, ...$config->resourceAttributes], static fn($value) => $value !== null)));
        // Host/process/SDK attributes come from the SDK's own detectors so we never
        // reinvent `host.name`/`process.pid`/`telemetry.sdk.*` (SPEC §58).
        return ResourceInfoFactory::defaultResource()->merge($explicit);
    }
}
/**
 * Builds the OTel ResourceInfo identifying this process (SPEC §10).
 *
 * `service.instance.id` falls back to a random id per process — stable for the lifetime of
 * one PHP process, which is enough to distinguish concurrent instances of the same service
 * without depending on any specific deployment platform.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Resource\ServiceResource', 'Cresenity\DevCloud\APM\Resource\ServiceResource', \false);
