<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Sep 1, 2018, 4:07:59 PM
 */
// @codingStandardsIgnoreStart
trait CTrait_Compat_Handler_Driver_Append {
    /**
     * @param string $method
     *
     * @return $this
     * @deprecated, please use setMethod
     */
    public function set_method($method) {
        return $this->setMethod($method);
    }

    /**
     * @param type $inputs
     *
     * @return type
     * @deprecated, please use addParamInput
     */
    public function add_param_input($inputs) {
        return $this->addParamInput($inputs);
    }

    /**
     * @param string|array $inputs
     *
     * @return $this
     * @deprecated, please use addParamInputByName
     */
    public function add_param_input_by_name($inputs) {
        return $this->addParamInputByName($inputs);
    }

    /**
     * @param string $selector
     *
     * @return $this
     *
     * @deprecated 1.2
     */
    public function set_check_duplicate_selector($selector) {
        return $this->setCheckDuplicateSelector($selector);
    }
}
