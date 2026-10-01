<?php

class CEmail_Builder_GlobalData {
    protected $data = [];

    protected static $instance;

    /**
     * @var CEmail_Builder_GlobalData[]
     */
    protected static $active = [];

    /**
     * Instance milik render yang sedang berjalan, atau instance bawaan di luar render.
     *
     * @return CEmail_Builder_GlobalData
     */
    public static function instance() {
        if (count(static::$active) > 0) {
            return end(static::$active);
        }
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @return CEmail_Builder_GlobalData
     */
    public static function create() {
        return new static();
    }

    /**
     * @param CEmail_Builder_GlobalData $globalData
     *
     * @return void
     */
    public static function activate(CEmail_Builder_GlobalData $globalData) {
        static::$active[] = $globalData;
    }

    /**
     * @return void
     */
    public static function deactivate() {
        array_pop(static::$active);
    }

    public function reset() {
        $this->data = [];
    }

    protected function __construct() {
        $this->reset();
    }

    public function get($key, $defaultValue = null) {
        return carr::get($this->data, $key, $defaultValue);
    }

    public function set($key, $value) {
        return carr::set($this->data, $key, $value);
    }

    public function data() {
        return $this->data;
    }

    public function exists($key) {
        return isset($this->data[$key]);
    }

    public function push($key, $value) {
        if (isset($this->data[$key]) && is_array($this->data[$key])) {
            $this->data[$key] = array_merge($this->data[$key], $value);
        }
    }
}
