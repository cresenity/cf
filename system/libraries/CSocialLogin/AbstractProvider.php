<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 16, 2019, 4:26:12 PM
 */
abstract class CSocialLogin_AbstractProvider implements CSocialLogin_AbstractProviderInterface {
    public function input($key = null, $default = null) {
        $request = CApp_Base::getRequestGet() + CApp_Base::getRequestPost();

        return carr::get($request, $key, $default);
    }

    public function session() {
        return CSession::instance();
    }

    public function hasRequest($key) {
        $request = CApp_Base::getRequestGet() + CApp_Base::getRequestPost();

        return isset($request[$key]);
    }
}
