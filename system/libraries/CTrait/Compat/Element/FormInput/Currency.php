<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 24, 2018, 6:08:17 PM
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_FormInput_Currency {
    /**
     * @deprecated since version 1.2
     *
     * @param string $placeholder
     *
     * @return string
     */
    public function set_placeholder($placeholder) {
        /** @var CElement_FormInput_Currency $this */
        return $this->setPlaceholder($placeholder);
    }
}
