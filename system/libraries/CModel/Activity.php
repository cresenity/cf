<?php

defined('SYSPATH') or die('No direct access allowed.');

final class CModel_Activity {
    use CEvent_Trait_Eventable;

    private $isStarted;

    private $message;

    private static $instance;

    private $data;

    /**
     * @return CModel_Activity
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->data = [];
    }

    public function setMessage($message) {
        $this->message = $message;
    }

    /**
     * Set the listener for this activity, replacing any previously set.
     *
     * @param callable $callback
     *
     * @return void
     */
    public function setListener($callback) {
        static::getDispatcher()->forget('OnActivity');
        $this->listen('OnActivity', $callback);
    }

    public function start() {
        $this->isStarted = true;
        $this->data = [];
    }

    public function stop() {
        $this->dispatch('OnActivity', [$this->message, $this->data]);
        $this->isStarted = false;
    }

    public function cancel() {
        $this->isStarted = false;
        $this->data = [];
    }

    public function addData($table, $key, $type, $before, $after, $changes) {
        $d['table'] = $table;
        $d['key'] = $key;
        $d['type'] = $type;
        $d['before'] = $before;
        $d['after'] = $after;
        $d['changes'] = $changes;
        $this->data[] = $d;
    }

    public function isStarted() {
        return $this->isStarted;
    }
}
