<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Aug 18, 2018, 11:36:23 PM
 */
class CJob_EventManager_Args extends CEventManager_Args {
    protected $args;

    public function addArg($key, $value) {
        $this->args[$key] = $value;
        return $this;
    }

    public function getArg($key) {
        return carr::get($this->args, $key);
    }

    public function args() {
        return $this->args;
    }
}
