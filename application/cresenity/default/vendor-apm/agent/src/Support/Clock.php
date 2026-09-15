<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support;

/**
 * Wall-clock access, kept behind one class so it stays swappable in tests
 * instead of every caller reaching for `hrtime()`/`microtime()` directly.
 */
class Clock
{
    public function nowNanos(): int
    {
        return hrtime(\true);
    }
    public function nowMillis(): int
    {
        return (int) round(microtime(\true) * 1000);
    }
}
/**
 * Wall-clock access, kept behind one class so it stays swappable in tests
 * instead of every caller reaching for `hrtime()`/`microtime()` directly.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Support\Clock', 'Cresenity\DevCloud\APM\Support\Clock', \false);
