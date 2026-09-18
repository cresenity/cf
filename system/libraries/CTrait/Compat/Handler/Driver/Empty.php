<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Sep 1, 2018, 4:29:43 PM
 */
// @codingStandardsIgnoreStart
trait CTrait_Compat_Handler_Driver_Empty {
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
     * @deprecated, please use addParamInput
     *
     * @param type $inputs
     *
     * @return type
     */
    public function add_param_input($inputs) {
        return $this->addParamInput($inputs);
    }

    /**
     * @deprecated, please use addParamInputByName
     *
     * @param type $inputs
     *
     * @return type
     */
    public function add_param_input_by_name($inputs) {
        return $this->addParamInputByName($inputs);
    }
}
