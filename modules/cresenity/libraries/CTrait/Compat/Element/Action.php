<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Feb 16, 2018, 5:01:46 AM
 */
//@codingStandardsIgnoreStart

trait CTrait_Compat_Element_Action {
    /**
     * @deprecated since version 1.2
     */
    public function get_label() {
        return $this->getLabel();
    }

    /**
     * @param string $label
     * @param bool   $lang
     *
     * @deprecated since version 1.2
     *
     * @return $this
     */
    public function set_label($label, $lang = true) {
        return $this->setLabel($label, $lang);
    }

    /**
     * @deprecated since version 1.2
     *
     * @param mixed $ic
     */
    public function set_icon($ic) {
        return $this->setIcon($ic);
    }

    /**
     * @deprecated since version 1.2, please use setSubmit
     *
     * @param $this
     * @param mixed $bool
     */
    public function set_submit($bool) {
        return $this->setSubmit($bool);
    }

    /**
     * @deprecated since version 1.2, please use setLink
     *
     * @param $this
     * @param mixed $link
     */
    public function set_link($link) {
        return $this->setLink($link);
    }

    /**
     * @deprecated since version 1.2, please use setConfirm
     *
     * @param $this
     * @param mixed $bool
     */
    public function set_confirm($bool) {
        return $this->setConfirm($bool);
    }

    /**
     * @deprecated since version 1.2, please use setLinkTarget
     *
     * @param string $linkTarget
     *
     * @return $this
     */
    public function set_link_target($linkTarget) {
        return $this->setLinkTarget($linkTarget);
    }

    /**
     * @deprecated since version 1.2, please use reassignConfirm
     *
     * @return $this
     */
    public function reassign_confirm() {
        return $this->reassignConfirm();
    }

    public function set_submit_to($url, $target = '') {
        return $this->setSubmitTo($url, $target);
    }

    public function set_disabled($bool) {
        return $this->setDisabled($bool);
    }

    public function render_as_input() {
        return $this->renderAsInput();
    }

    public function set_jsparam($jsparam) {
        return $this->setJsParam($jsparam);
    }

    public function set_confirm_message($message) {
        $this->confirm_message = $message;
        return $this;
    }

    public function set_type($type) {
        $this->type = $type;
        return $this;
    }

    public function set_value($value) {
        $this->value = $value;
        return $this;
    }

    public function set_jsfunc($jsfunc) {
        $this->jsfunc = $jsfunc;
        return $this;
    }

    public function set_button($bool) {
        $this->button = $bool;
        return $this;
    }
}
//@codingStandardsIgnoreEnd
