<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Feb 17, 2018, 1:36:51 AM
 */
trait CTrait_Element_Property_Visible {
    /**
     * @var bool
     */
    protected $visible = true;

    public function setVisible($visible) {
        $this->visible = $visible;
        return $this;
    }

    public function getVisible() {
        return $this->visible;
    }

    public function isVisible() {
        return $this->visible == true;
    }
}
