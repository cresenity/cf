<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Sep 1, 2018, 12:48:44 PM
 */
class CDatabase_Event_Schema {
    /**
     * @var bool
     */
    private $preventDefault = false;

    /**
     * @return CDatabase_Event_Schema
     */
    public function preventDefault() {
        $this->preventDefault = true;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDefaultPrevented() {
        return $this->preventDefault;
    }
}
