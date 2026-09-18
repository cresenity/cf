<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Dec 30, 2017, 8:28:30 PM
 */
trait CTrait_Creational_Singleton {
    protected static $instance;

    final public static function instance() {
        return isset(static::$instance) ? static::$instance : static::$instance = new static();
    }

    final public static function clean() {
        return static::$instance = new static();
    }

    final private function __construct() {
        static::init();
    }

    protected static function init() {
    }

    final private function __wakeup() {
    }

    final private function __clone() {
    }
}
