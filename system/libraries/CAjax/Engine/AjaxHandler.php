<?php

defined('SYSPATH') or die('No direct access allowed.');

class CAjax_Engine_AjaxHandler extends CAjax_Engine {
    public function execute() {
        $input = $this->input;
        $data = $this->ajaxMethod->getData();
        $callback = carr::get($data, 'callback');
        if (is_string($callback) && strncmp($callback, 'O:', 2) === 0) {
            $callback = unserialize($callback);
        }
        $json = carr::get($data, 'json');

        if ($callback != null) {
            $app = c::app();
            $parameters = [$app];

            return c::value($callback, ...$parameters);
        } else {
            return $json;
        }
    }
}
