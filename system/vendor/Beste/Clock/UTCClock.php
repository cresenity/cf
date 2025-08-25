<?php

declare(strict_types=1);

namespace Beste\Clock;

use Beste\Clock;
use DateTimeZone;
use DateTimeImmutable;

final class UTCClock implements Clock {
    private DateTimeZone $timeZone;

    private function __construct() {
        $this->timeZone = new DateTimeZone('UTC');
    }

    public static function create(): self {
        return new self();
    }

    public function now(): DateTimeImmutable {
        return new DateTimeImmutable('now', $this->timeZone);
    }
}
