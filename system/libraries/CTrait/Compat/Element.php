<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Feb 17, 2018, 4:18:47 AM
 */

 //@codingStandardsIgnoreStart
trait CTrait_Compat_Element {
    public function valid_tag() {
        return $this->validTag();
    }

    public function set_radio($radio) {
        return $this->setRadio($radio);
    }

    public function set_text($text) {
        return $this->setText($text);
    }

    /**
     * @param string $key
     * @param string $val
     *
     * @deprecated since version 1.2, please use function customCss
     *
     * @return $this
     */
    public function custom_css($key, $val) {
        return $this->customCss($key, $val);
    }

    /**
     * @deprecated since version 1.2, please use function setTag
     *
     * @param string $tag
     *
     * @return $this
     */
    public function set_tag($tag) {
        return $this->setTag($tag);
    }

    /**
     * @param string $c
     *
     * @deprecated since version 1.2, please use function addClass
     *
     * @return $this
     */
    public function add_class($c) {
        return $this->addClass($c);
    }

    public function delete_attr($k) {
        return $this->deleteAttr($k);
    }

    /**
     * @param string $k
     * @param string $v
     *
     * @deprecated since version 1.2, please use function setAttr
     *
     * @return $this
     */
    public function set_attr($k, $v) {
        return $this->setAttr($k, $v);
    }

    /**
     * @param string $k
     * @param string $v
     *
     * @deprecated since version 1.2, please use function addAttr
     *
     * @return $this
     */
    public function add_attr($k, $v) {
        return $this->addAttr($k, $v);
    }

    public function get_attr($k) {
        return $this->getAttr($k);
    }

    public function generate_class() {
        return $this->generateClass();
    }

    /**
     * @param int $indent
     *
     * @return string
     *
     * @deprecated 1.2
     */
    protected function html_child($indent = 0) {
        return $this->htmlChild($indent);
    }

    /**
     * @param int $indent
     *
     * @return string
     *
     * @deprecated 1.2
     */
    protected function js_child($indent = 0) {
        return $this->jsChild($indent);
    }
}
//@codingStandardsIgnoreEnd
