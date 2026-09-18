<?php

/**
 * Description of Runner.
 */
class CApi_Runner {
    private static $instance;

    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new CApi_Runner();
        }

        return static::$instance;
    }

    public function __construct() {
    }

    public function runMethod(CApi_MethodAbstract $method) {
        if (!$method->hasError()) {
            $method->execute();
        }

        return $method->toArray();
    }
}
