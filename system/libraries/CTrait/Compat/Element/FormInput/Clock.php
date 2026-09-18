<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 24, 2018, 6:46:52 PM
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_FormInput_Clock {
    /**
     * @deprecated since version 1.2
     *
     * @param type $placeholder
     *
     * @return type
     */
    public function set_placeholder($placeholder) {
        /** @var CElement_FormInput_Clock $this */
        return $this->setPlaceholder($placeholder);
    }
}
