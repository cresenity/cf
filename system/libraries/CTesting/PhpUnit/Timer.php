<?php

/**
 * Description of Timer
 *
 *
 * @internal
 */
final class CTesting_PhpUnit_Timer {
    /**
     * @var float
     */
    private $start;

    /**
     * Timer constructor.
     *
     * @param mixed $start
     */
    private function __construct($start) {
        $this->start = $start;
    }

    /**
     * Starts the timer.
     */
    public static function start() {
        return new self(microtime(true));
    }

    /**
     * Returns the elapsed time in microseconds.
     */
    public function result() {
        return microtime(true) - $this->start;
    }
}
