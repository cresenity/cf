<?php

use Symfony\Component\Console\Output\OutputInterface;

class CCron {
    /**
     * @var CCron_Event
     */
    private static $runningEvent;

    /**
     * @var CCron_Schedule
     */
    private static $schedule;

    /**
     * Get CConsole Schedule Object.
     *
     * @return CCron_Schedule
     */
    public static function schedule() {
        if (static::$schedule == null) {
            static::$schedule = c::tap(new CCron_Schedule(static::scheduleTimezone()), function (CCron_Schedule $schedule) {
                return $schedule->useCache(static::scheduleCache());
            });
        }

        return static::$schedule;
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return null|\DateTimeZone|string
     */
    protected static function scheduleTimezone() {
        return CF::config('app.cron_timezone', CF::config('app.timezone'));
    }

    /**
     * Get the name of the cache store that should manage scheduling mutexes.
     *
     * @return string
     */
    protected static function scheduleCache() {
        return CF::config('cron.cache.store', CEnv::get('CRON_CACHE_DRIVER'));
    }

    /**
     * Get the name of the cache store that should manage scheduling mutexes.
     *
     * @return string
     */
    public static function run(?OutputInterface $output = null) {
        return (new CCron_Runner())->run($output);
    }

    /**
     * Set current running event to enable log using CCron::log().
     *
     * @param CCron_Event $event
     *
     * @return void
     */
    public static function setEvent(CCron_Event $event) {
        static::$runningEvent = $event;
    }

    /**
     * Remove current running event to prevent missplaced log file.
     *
     * @return void
     */
    public static function unsetEvent() {
        static::$runningEvent = null;
    }

    /**
     * Log cron event.
     *
     * @param string $message
     *
     * @return void
     */
    public static function log($message) {
        if (static::$runningEvent) {
            static::$runningEvent->log($message);
        }
    }

    /**
     * Whether the current request is executing inside a scheduled cron event
     * (set by {@see setEvent()}) - lets shared code (e.g. a CHouseKeeping_*
     * class) decide whether CCron::log() will actually go anywhere before
     * calling it, the same way CDaemon::isDaemon() guards CDaemon::log().
     *
     * @return bool
     */
    public static function isCron() {
        return static::$runningEvent !== null;
    }
}
