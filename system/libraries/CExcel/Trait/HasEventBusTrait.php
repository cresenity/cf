<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
trait CExcel_Trait_HasEventBusTrait {
    /**
     * @var array
     */
    protected static $globalEvents = [];

    /**
     * @var array
     */
    protected $events = [];

    /**
     * Register local event listeners.
     *
     * @param array $listeners
     */
    public function registerListeners(array $listeners) {
        foreach ($listeners as $event => $listener) {
            $this->events[$event][] = $listener;
        }
    }

    /**
     * Register a global event listener.
     *
     * @param string   $event
     * @param callable $listener
     */
    public static function listen($event, $listener) {
        static::$globalEvents[$event][] = $listener;
    }

    /**
     * @param object $event
     */
    public function raise($event) {
        foreach ($this->listeners($event) as $listener) {
            $listener($event);
        }
    }

    /**
     * @param object $event
     *
     * @return callable[]
     */
    public function listeners($event) {
        $name = get_class($event);
        $localListeners = isset($this->events[$name]) ? $this->events[$name] : [];
        $globalListeners = isset(static::$globalEvents[$name]) ? static::$globalEvents[$name] : [];

        return array_merge($globalListeners, $localListeners);
    }
}
