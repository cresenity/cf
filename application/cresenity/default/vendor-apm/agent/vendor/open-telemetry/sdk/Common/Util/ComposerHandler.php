<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Util;

use function class_exists;
use function getenv;
final class ComposerHandler
{
    public static function isRunning(): bool
    {
        /**
         * This is set when composer is running a script; eg:
         * composer run-script test-psr3
         *
         * However, it is not set in the following case:
         * composer test-psr3
         */
        if (getenv('COMPOSER_DEV_MODE')) {
            return \true;
        }
        return class_exists(\CresenityDevCloudAPMVendor\Composer\Console\Application::class, \false);
    }
}
