<?php

defined('SYSPATH') or die('No direct access allowed.');

abstract class CQC_RunnerAbstract {
    protected $className;

    public function __construct($className) {
        $this->className = $className;
    }

    abstract public function run();
}
