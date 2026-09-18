<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 3, 2018, 10:51:38 AM
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_FormInput_Textarea {
    /**
     * @param mixed $col
     *
     * @deprecated since version 1.2, use setCol
     */
    public function set_col($col) {
        /** @var CElement_FormInput_Textarea $this */
        return $this->setCol($col);
    }

    /**
     * @param mixed $row
     *
     * @deprecated since version 1.2 , use setRow
     */
    public function set_row($row) {
        /** @var CElement_FormInput_Textarea $this */
        return $this->setRow($row);
    }

    /**
     * @param mixed $placeholder
     *
     * @deprecated since version 1.2, use setPlaceholder
     */
    public function set_placeholder($placeholder) {
        /** @var CElement_FormInput_Textarea $this */
        return $this->setPlaceholder($placeholder);
    }
}
