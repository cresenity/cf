<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since Aug 24, 2018, 7:54:31 PM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
use DateInterval;
use DateTimeInterface;

trait CTrait_Helper_InteractsWithTime {

    /**
     * Get the number of seconds until the given DateTime.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return int
     */
    protected function secondsUntil($delay) {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof DateTimeInterface ? max(0, $delay->getTimestamp() - $this->currentTime()) : (int) $delay;
    }

    /**
     * Get the "available at" UNIX timestamp.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return int
     */
    protected function availableAt($delay = 0) {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof DateTimeInterface ? $delay->getTimestamp() : Carbon::now()->addSeconds($delay)->getTimestamp();
    }

    /**
     * If the given value is an interval, convert it to a DateTime instance.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return \DateTimeInterface|int
     */
    protected function parseDateInterval($delay) {
        if ($delay instanceof DateInterval) {
            $delay = CCarbon::now()->add($delay);
        }

        return $delay;
    }

    /**
     * Get the current system time as a UNIX timestamp.
     *
     * @return int
     */
    protected function currentTime() {
        return CCarbon::now()->getTimestamp();
    }

}
