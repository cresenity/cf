<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2018, 4:15:32 AM
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_FormInput_Checkbox {
    /**
     * @param string $label
     * @param string $lang
     *
     * @deprecated
     *
     * @return $this
     */
    public function set_label($label, $lang = true) {
        /** @var CElement_FormInput_Checkbox $this */
        return $this->setLabel($label, $lang);
    }

    /**
     * @param string $label
     * @param string $lang
     * @param mixed  $bool
     *
     * @deprecated
     *
     * @return $this
     */
    public function set_checked($bool) {
        /** @var CElement_FormInput_Checkbox $this */
        return $this->setChecked($bool);
    }

    /**
     * Sets an undefined dynamic property -- CElement_FormInput_Checkbox has no
     * $applyjs property, so this has no observable effect.
     *
     * @param mixed $applyjs
     *
     * @return $this
     *
     * @deprecated
     */
    public function set_applyjs($applyjs) {
        /** @var CElement_FormInput_Checkbox $this */
        $this->applyjs = $applyjs;

        return $this;
    }

    /**
     * Sets an undefined dynamic property -- CElement_FormInput_Checkbox has no
     * $label_wrap property, so this has no observable effect.
     *
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated
     */
    public function set_label_wrap($bool) {
        /** @var CElement_FormInput_Checkbox $this */
        $this->label_wrap = $bool;

        return $this;
    }

    /**
     * Sets an undefined dynamic property -- CElement_FormInput_Checkbox has no
     * $display_inline property, so this has no observable effect.
     *
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated
     */
    public function set_display_inline($bool) {
        /** @var CElement_FormInput_Checkbox $this */
        $this->display_inline = $bool;

        return $this;
    }
}
