<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @see CElement_Component_Action
 *
 *
 * @since Feb 16, 2018, 5:01:46 AM
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_Action {
    /**
     * @return string
     *
     * @deprecated since version 1.2
     */
    public function get_label() {
        /** @var CElement_Component_Action $this */
        return $this->getLabel();
    }

    /**
     * @param string $label
     * @param bool   $lang
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_label($label, $lang = true) {
        /** @var CElement_Component_Action $this */
        return $this->setLabel($label, $lang);
    }

    /**
     * @param mixed $ic
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_icon($ic) {
        /** @var CElement_Component_Action $this */
        return $this->setIcon($ic);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since version 1.2, please use setSubmit
     */
    public function set_submit($bool) {
        /** @var CElement_Component_Action $this */
        return $this->setSubmit($bool);
    }

    /**
     * @param string $link
     *
     * @return $this
     *
     * @deprecated since version 1.2, please use setLink
     */
    public function set_link($link) {
        /** @var CElement_Component_Action $this */
        return $this->setLink($link);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since version 1.2, please use setConfirm
     */
    public function set_confirm($bool) {
        /** @var CElement_Component_Action $this */
        return $this->setConfirm($bool);
    }

    /**
     * @param string $linkTarget
     *
     * @return $this
     *
     * @deprecated since version 1.2, please use setLinkTarget
     */
    public function set_link_target($linkTarget) {
        /** @var CElement_Component_Action $this */
        return $this->setLinkTarget($linkTarget);
    }

    /**
     * @return $this
     *
     * @deprecated since version 1.2, please use reassignConfirm
     */
    public function reassign_confirm() {
        /** @var CElement_Component_Action $this */
        return $this->reassignConfirm();
    }

    /**
     * @param string $url
     * @param string $target
     *
     * @return $this
     *
     * @deprecated since 1.2
     */
    public function set_submit_to($url, $target = '') {
        /** @var CElement_Component_Action $this */
        return $this->setSubmitTo($url, $target);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since 1.2
     */
    public function set_disabled($bool) {
        /** @var CElement_Component_Action $this */
        return $this->setDisabled($bool);
    }

    /**
     * @return $this
     *
     * @deprecated since 1.2
     */
    public function render_as_input() {
        /** @var CElement_Component_Action $this */
        return $this->renderAsInput();
    }

    /**
     * @param mixed $jsparam
     *
     * @return $this
     *
     * @deprecated since 1.2
     */
    public function set_jsparam($jsparam) {
        /** @var CElement_Component_Action $this */
        return $this->setJsParam($jsparam);
    }

    /**
     * @param string $message
     *
     * @return $this
     *
     * @deprecated since 1.2
     */
    public function set_confirm_message($message) {
        /** @var CElement_Component_Action $this */
        return $this->setConfirmMessage($message);
    }

    /**
     * @param string $type
     *
     * @return $this
     *
     * @deprecated since 1.2
     */
    public function set_type($type) {
        /** @var CElement_Component_Action $this */
        $this->type = $type;

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     *
     * @deprecated since 1.2
     */
    public function set_value($value) {
        /** @var CElement_Component_Action $this */
        $this->value = $value;

        return $this;
    }

    /**
     * @param string $jsfunc
     *
     * @return $this
     *
     * @deprecated since 1.2
     */
    public function set_jsfunc($jsfunc) {
        /** @var CElement_Component_Action $this */
        $this->jsfunc = $jsfunc;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated since 1.2
     */
    public function set_button($bool) {
        /** @var CElement_Component_Action $this */
        $this->button = $bool;

        return $this;
    }
}
//@codingStandardsIgnoreEnd
