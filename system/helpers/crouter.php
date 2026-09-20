<?php
defined('SYSPATH') or die('No direct access allowed.');

//@codingStandardsIgnoreStart
/**
 * @deprecated 1.8 use CRouting_Router|c::router()
 */
class crouter {
    /**
     * @return string
     *
     * @deprecated since 1.2, use CF::domain
     */
    public static function domain() {
        CF::deprecated(__METHOD__, 'CF::domain', '1.2', null, true);
        return CF::domain();
    }

    /**
     * @return string
     *
     * @deprecated since 1.3, use c::router()->current()->getController();
     */
    public static function controller() {
        CF::deprecated(__METHOD__, 'c::router()->current()->getController()', '1.3', null, true);
        return c::router()->current()->getController();
    }

    public static function controller_dir() {
        CF::deprecated(__METHOD__, 'c::router()', '1.8', null, true);
        return c::router()->current()->getRouteData()->getControllerDir();
    }

    /**
     * @return string
     *
     * @deprecated since 1.3, dont use anymore, build from c::request
     */
    public static function method() {
        CF::deprecated(__METHOD__, 'c::request()', '1.3', null, true);
        return c::router()->current()->getRouteData()->getMethod();
    }

    /**
     * @return string
     *
     * @deprecated since 1.3, dont use anymore, build from c::request
     */
    public static function routed_uri() {
        CF::deprecated(__METHOD__, 'c::request()', '1.3', null, true);
        return c::router()->current()->getRouteData()->getRoutedUri();
    }

    /**
     * @return string
     *
     * @deprecated since 1.3, dont use anymore, build from c::request
     */
    public static function complete_uri() {
        CF::deprecated(__METHOD__, 'c::request()', '1.3', null, true);
        return c::router()->current()->getRouteData()->getCompleteUri();
    }

    /**
     * @return string
     *
     * @deprecated since 1.3, dont use anymore, build from c::request
     */
    public static function query_string() {
        CF::deprecated(__METHOD__, 'c::request()', '1.3', null, true);
        return c::router()->current()->getRouteData()->getQueryString();
    }

    /**
     * @return string
     *
     * @deprecated since 1.3, dont use anymore, build from c::request
     */
    public static function current_uri() {
        CF::deprecated(__METHOD__, 'c::request()', '1.3', null, true);
        return c::router()->current()->getRouteData()->getUri();
    }

    /**
     * @return string
     *
     * @deprecated since 1.3, dont use anymore, build from c::request
     */
    public static function urlSuffix() {
        CF::deprecated(__METHOD__, 'c::request()', '1.3', null, true);
        return c::router()->current()->getRouteData()->getUrlSuffix();
    }

    /**
     * @return string
     *
     * @deprecated since 1.3, dont use anymore, build from c::request
     */
    public static function segments() {
        CF::deprecated(__METHOD__, 'c::request()', '1.3', null, true);
        return c::router()->current()->getRouteData()->getSegments();
    }

    /**
     * @return string
     *
     * @deprecated since 1.3, dont use anymore, build from c::request
     */
    public static function controller_path() {
        CF::deprecated(__METHOD__, 'c::request()', '1.3', null, true);
        return c::router()->current()->getRouteData()->getControllerPath();
    }

    /**
     * @return string
     *
     * @deprecated since 1.3, dont use anymore, build from c::request
     */
    public static function arguments() {
        CF::deprecated(__METHOD__, 'c::request()', '1.3', null, true);
        return c::router()->current()->getRouteData()->getArguments();
    }
}
