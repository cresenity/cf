<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Mar 19, 2019, 5:31:34 PM
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Resources_Engine_Image {
    /**
     * @param string $size_name
     * @param array  $options
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function add_size($size_name, $options) {
        return $this->addSize($size_name, $options);
    }
}
