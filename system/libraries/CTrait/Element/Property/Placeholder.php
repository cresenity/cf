<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 3, 2018, 2:38:26 PM
 */
trait CTrait_Element_Property_Placeholder {
    /**
     * @var string
     */
    public $placeholder;

    /**
     * @var string
     */
    public $rawPlaceholder;

    /**
     * @param string     $placeholder
     * @param bool|array $lang
     *
     * @return $this
     */
    public function setPlaceholder($placeholder, $lang = true) {
        $this->rawPlaceholder = $placeholder;
        if ($lang !== false) {
            $placeholder = c::__($placeholder, is_array($lang) ? $lang : []);
        }
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * @return string
     */
    public function getPlaceholder() {
        return $this->rawPlaceholder;
    }

    /**
     * @return string
     */
    public function getTranslationPlaceholder() {
        return $this->placeholder;
    }
}
