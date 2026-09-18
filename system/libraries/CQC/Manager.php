<?php

defined('SYSPATH') or die('No direct access allowed.');

class CQC_Manager {
    use CQC_Manager_DatabaseCheckerTrait,
        CQC_Manager_UnitTestTrait;

    protected static $instance;

    protected $testing;

    /**
     * @return CQC_Manager
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct() {
    }

    /**
     * @return CQC_Testing
     */
    public function testing() {
        if ($this->testing == null) {
            $this->testing = new CQC_Testing();
        }

        return $this->testing;
    }
}
