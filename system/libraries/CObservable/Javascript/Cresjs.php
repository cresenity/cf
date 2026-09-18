<?php

defined('SYSPATH') or die('No direct access allowed.');

class CObservable_Javascript_Cresjs {
    /**
     * @var CObservable_Javascript
     */
    protected $javascript;

    public function __construct($javascript) {
        $this->javascript = $javascript;
    }

    public function toast($info, $message) {
        $js = 'cresenity.toast(' . CJavascript_Helper_Javascript::prepValue($info) . ',' . CJavascript_Helper_Javascript::prepValue($message) . ')';
        $this->javascript->raw($js);
    }
}
