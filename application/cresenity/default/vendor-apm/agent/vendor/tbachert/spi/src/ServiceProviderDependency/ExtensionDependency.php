<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Nevay\SPI\ServiceProviderDependency;

use Attribute;
use CresenityDevCloudAPMVendor\Composer\Semver\VersionParser;
use CresenityDevCloudAPMVendor\Nevay\SPI\ServiceProviderRequirementRuntimeValidated;
use function phpversion;
/**
 * Specifies extensions required by a service provider.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class ExtensionDependency implements ServiceProviderRequirementRuntimeValidated
{
    public function __construct(private readonly string $extension, private readonly string $version)
    {
    }
    public function isSatisfied(): bool
    {
        if (($version = phpversion($this->extension)) === \false) {
            return \false;
        }
        $parser = new VersionParser();
        $constraint = $parser->parseConstraints($this->version);
        $provided = $parser->parseConstraints($version);
        return $provided->matches($constraint);
    }
    public function hash(): string|false
    {
        return phpversion($this->extension);
    }
}
