<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Feb 16, 2018, 5:18:23 AM
 */
trait CTrait_Element_Property_Width {
    /**
     * @var int|string
     */
    protected $width;

    /**
     * @param int|string $width
     *
     * @return $this
     */
    public function setWidth($width) {
        $this->width = $width;
        return $this;
    }

    /**
     * @return int|string
     */
    public function getWidth() {
        return $this->width;
    }
}
