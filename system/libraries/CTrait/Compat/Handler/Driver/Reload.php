<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Sep 1, 2018, 3:22:30 PM
 */

// @codingStandardsIgnoreStart

trait CTrait_Compat_Handler_Driver_Reload {
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
     * @param string|array $inputs
     *
     * @return $this
     * @deprecated, please use addParamInput
     */
    public function add_param_input($inputs) {
        return $this->addParamInput($inputs);
    }

    /**
     * @param type $inputs
     *
     * @return $this
     * @deprecated, please use addParamInputByName
     */
    public function add_param_input_by_name($inputs) {
        return $this->addParamInputByName($inputs);
    }
}
