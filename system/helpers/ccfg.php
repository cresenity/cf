<?php

//@codingStandardsIgnoreStart
class ccfg {
    /**
     * Undocumented function
     *
     * @param string $name
     * @param string $appCode
     *
     * @return array
     *
     * @deprecated 1.1
     */
    public static function get_data($name, $appCode = null) {
        return CApp_Config::getData($name, $appCode);
    }

    public static function get($key, $domain = '') {
        return CApp_Config::get($key, $domain);
    }
}
//@codingStandardsIgnoreEnd
