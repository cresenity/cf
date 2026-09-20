<?php

//@codingStandardsIgnoreStart
/**
 * @deprecated since 1.6 use CF::config
 */
class ccfg {
    /**
     * Undocumented function.
     *
     * @param string $name
     * @param string $appCode
     *
     * @return array
     *
     * @deprecated 1.1
     */
    public static function get_data($name, $appCode = null) {
        CF::deprecated(__METHOD__, 'CApp_Config::getData()', '1.1', null, true);
        return CApp_Config::getData($name, $appCode);
    }

    public static function get($key, $domain = '') {
        CF::deprecated(__METHOD__, 'CApp_Config::get()', '1.6', null, true);
        return CApp_Config::get($key, $domain);
    }
}
//@codingStandardsIgnoreEnd
