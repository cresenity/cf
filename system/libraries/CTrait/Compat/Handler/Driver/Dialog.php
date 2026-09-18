<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Sep 1, 2018, 3:22:30 PM
 */
// @codingStandardsIgnoreStart
trait CTrait_Compat_Handler_Driver_Dialog {
    protected $js_class;

    protected $js_class_manual;

    /**
     * @param string $js_class
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_js_class($js_class) {
        //set js class manual
        $this->js_class_manual = $js_class;

        return $this;
    }

    /**
     * @param string $title
     *
     * @deprecated, please use setTitle
     *
     * @return $this
     */
    public function set_title($title) {
        return $this->setTitle($title);
    }

    public function add_param_input($inputs) {
        return $this->addParamInput($inputs);
    }

    public function add_param_request($paramRequest) {
        return $this->addparamRequest($paramRequest);
    }

    public function set_method($method) {
        return $this->setMethod($method);
    }
}
