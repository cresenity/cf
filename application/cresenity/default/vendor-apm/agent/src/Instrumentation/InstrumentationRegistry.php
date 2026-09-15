<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation;

use CresenityDevCloudAPMVendor\Psr\Log\LoggerInterface;
use CresenityDevCloudAPMVendor\Psr\Log\NullLogger;
use Throwable;
/**
 * Holds instrumentation instances and activates only the ones whose
 * dependencies are actually present (SPEC §42-§43). A single instrumentation
 * throwing during registration must never take down the others or the
 * application - each is isolated and logged, not propagated (SPEC §7, §35).
 */
final class InstrumentationRegistry
{
    /** @var list<InstrumentationInterface> */
    private array $instrumentations = [];
    public function add(InstrumentationInterface $instrumentation): self
    {
        $this->instrumentations[] = $instrumentation;
        return $this;
    }
    public function registerAll(?LoggerInterface $logger = null): void
    {
        $logger ??= new NullLogger();
        foreach ($this->instrumentations as $instrumentation) {
            if (!$instrumentation->isAvailable()) {
                continue;
            }
            try {
                $instrumentation->register();
            } catch (Throwable $exception) {
                $logger->warning(sprintf('devcloud-apm: instrumentation "%s" failed to register: %s', $instrumentation->name(), $exception->getMessage()));
            }
        }
    }
}
/**
 * Holds instrumentation instances and activates only the ones whose
 * dependencies are actually present (SPEC §42-§43). A single instrumentation
 * throwing during registration must never take down the others or the
 * application - each is isolated and logged, not propagated (SPEC §7, §35).
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\InstrumentationRegistry', 'Cresenity\DevCloud\APM\Instrumentation\InstrumentationRegistry', \false);
