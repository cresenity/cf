<?php

defined('SYSPATH') or die('No direct access allowed.');

class CAjax_Engine_Reload extends CAjax_Engine {
    public function execute() {
        $input = $this->input;
        $data = $this->ajaxMethod->getData();
        $json = carr::get($data, 'json');
        $callback = $this->resolveCallable(carr::get($data, 'callback'));
        if ($callback != null) {
            $parameters = [];

            return c::value($callback, ...$parameters);
        } else {
            return $json;
        }
    }
}
