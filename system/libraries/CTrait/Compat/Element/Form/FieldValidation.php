<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @see CElement_Component_Form_FieldValidation
 *
 *
 * @since Feb 17, 2018, 2:29:43 AM
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_Form_FieldValidation {
    /**
     * @param string $name
     * @param mixed  $param
     *
     * @deprecated 1.2 use addValidation
     *
     * @return $this
     */
    public function add_validation($name, $param) {
        /** @var CElement_Component_Form_FieldValidation $this */
        return $this->addValidation($name, $param);
    }

    /**
     * @param mixed $input
     *
     * @return $this
     *
     * @deprecated 1.2 dont use this anymore
     */
    public function condrequired($input) {
        /** @var CElement_Component_Form_FieldValidation $this */
        return $this->addValidation('condrequired', $input);
    }

    /**
     * @return string
     *
     * @deprecated 1.2
     */
    public function validation_class() {
        /** @var CElement_Component_Form_FieldValidation $this */
        return $this->validationClass();
    }
}
