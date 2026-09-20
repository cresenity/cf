<?php

defined('SYSPATH') or die('No direct access allowed.');

//@codingStandardsIgnoreStart
/**
 * @deprecated since 1.6 use c::request()
 */
class crequest {
    /**
     * Returns the HTTP referrer, or the default if the referrer is not set.
     *
     * @param   mixed   default to return
     * @param mixed $default
     *
     * @return string
     */
    public static function referrer($default = false) {
        CF::deprecated(__METHOD__, 'c::request()', '1.6', null, true);
        if (!empty($_SERVER['HTTP_REFERER'])) {
            // Set referrer
            $ref = $_SERVER['HTTP_REFERER'];

            if (strpos($ref, curl::base(false)) === 0) {
                // Remove the base URL from the referrer
                $ref = substr($ref, strlen(curl::base(false)));
            }
        }

        return isset($ref) ? $ref : $default;
    }

    public static function current_container_id() {
        CF::deprecated(__METHOD__, 'c::request()', '1.6', null, true);
        return carr::get($_GET, 'capp_current_container_id');
    }

    public static function userAgent() {
        CF::deprecated(__METHOD__, 'c::request()', '1.6', null, true);
        return CHTTP::request()->userAgent();
    }

    /**
     * @deprecated 1.2
     *
     * @return string
     */
    public static function user_agent() {
        CF::deprecated(__METHOD__, 'c::request()', '1.2', null, true);
        return static::userAgent();
    }

    public static function browser() {
        CF::deprecated(__METHOD__, 'c::request()', '1.6', null, true);
        return CHTTP::request()->browser()->getBrowser();
    }

    /**
     * @deprecated 1.2
     *
     * @return string
     */
    public static function browser_version() {
        CF::deprecated(__METHOD__, 'c::request()', '1.2', null, true);
        return static::browserVersion();
    }

    public static function browserVersion() {
        CF::deprecated(__METHOD__, 'c::request()', '1.6', null, true);
        return CHTTP::request()->browser()->getVersion();
    }

    public static function platform() {
        CF::deprecated(__METHOD__, 'c::request()', '1.6', null, true);
        return CHTTP::request()->browser()->getPlatform();
    }

    /**
     * @deprecated 1.2
     *
     * @return string
     */
    public static function platform_version() {
        CF::deprecated(__METHOD__, 'c::request()', '1.2', null, true);
        return '';
    }

    /**
     * Fetch the Remote Address.
     *
     * @deprecated 1.2
     *
     * @return string
     */
    public static function remoteAddress() {
        CF::deprecated(__METHOD__, 'c::request()', '1.2', null, true);
        return CHTTP::request()->ip();
    }

    /**
     * Fetch the Remote Address.
     *
     * @return string
     *
     * @deprecated 1.2
     */
    public static function remote_address() {
        CF::deprecated(__METHOD__, 'c::request()', '1.2', null, true);
        return CHTTP::request()->ip();
    }

    /**
     * Returns the current request protocol, based on $_SERVER['https']. In CLI
     * mode, NULL will be returned.
     *
     * @return string
     */
    public static function protocol() {
        CF::deprecated(__METHOD__, 'c::request()', '1.6', null, true);
        if (PHP_SAPI === 'cli') {
            return null;
        } elseif (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] === 'on') {
            return 'https';
        } else {
            return 'http';
        }
    }

    /**
     * Tests if the current request is an AJAX request by checking the X-Requested-With HTTP
     * request header that most popular JS frameworks now set for AJAX calls.
     *
     * @return bool
     */
    public static function is_ajax() {
        CF::deprecated(__METHOD__, 'c::request()', '1.6', null, true);
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Checks to see if the page is being server over SSL or not.
     *
     * @return bool
     *
     * @deprecated 1.2
     * @static
     */
    public static function is_https() {
        CF::deprecated(__METHOD__, 'c::request()', '1.2', null, true);
        return static::isHttps();
    }

    /**
     * Checks to see if the page is being server over SSL or not.
     *
     * @return bool
     *
     * @since   1.0.000
     * @static
     */
    public static function isHttps() {
        CF::deprecated(__METHOD__, 'c::request()', '1.6', null, true);
        return static::protocol() === 'https';
    }

    /**
     * Returns current request method.
     *
     * @return string
     */
    public static function method() {
        CF::deprecated(__METHOD__, 'c::request()', '1.6', null, true);
        $method = strtolower(carr::get($_SERVER, 'REQUEST_METHOD', 'GET'));

        return $method;
    }
}

// End request
