<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Mar 24, 2019, 1:55:42 AM
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_FormInput_Radio {
    /**
     * @param bool $applyjs
     *
     * @return $this
     *
     * @deprecated use setApplyJs
     */
    public function set_applyjs($applyjs) {
        /** @var CElement_FormInput_Radio $this */
        return $this->setApplyJs($applyjs);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated use setChecked
     */
    public function set_checked($bool) {
        /** @var CElement_FormInput_Radio $this */
        return $this->setChecked($bool);
    }

    /**
     * @param string     $label
     * @param bool|array $lang
     *
     * @return $this
     *
     * @deprecated use setLabel
     */
    public function set_label($label, $lang = true) {
        /** @var CElement_FormInput_Radio $this */
        return $this->setLabel($label, $lang);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated 1.2 use setLabelWrap
     */
    public function set_label_wrap($bool) {
        /** @var CElement_FormInput_Radio $this */
        return $this->setLabelWrap($bool);
    }

    /**
     * @return bool
     *
     * @deprecated use getInline
     */
    public function get_inline() {
        /** @var CElement_FormInput_Radio $this */
        return $this->getInline();
    }

    /**
     * @param bool $inline
     *
     * @return $this
     *
     * @deprecated use setInline
     */
    public function set_inline($inline) {
        /** @var CElement_FormInput_Radio $this */
        return $this->setInline($inline);
    }
}
