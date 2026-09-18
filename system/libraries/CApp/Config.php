<?php

defined('SYSPATH') or die('No direct access allowed.');

use CHelper_File as File;

class CApp_Config {
    /**
     * @var array
     */
    protected static $configData = [];

    /**
     * @param string      $name
     * @param null|string $appCode
     *
     * @return mixed
     */
    public static function getData($name, $appCode = null) {
        if ($appCode == null) {
            $appCode = CF::appCode();
        }
        $name = str_replace('.', '/', $name);

        $file = DOCROOT . 'config/data/' . $appCode . '/' . $name . EXT;
        $data = null;
        if (File::exists($file)) {
            $data = include $file;
        }

        return $data;
    }

    /**
     * @param string $key
     * @param string $domain
     *
     * @return mixed
     */
    public static function get($key, $domain = '') {
        if (strlen((string) $domain) == 0) {
            $domain = CF::domain();
        }
        $camelKey = cstr::camel($key);
        $data = CFData::get($domain, 'domain');
        $configFiles = ['app', 'app_setting'];
        if (!isset(self::$configData[$domain])) {
            $config = [];
            foreach ($configFiles as $f) {
                $appFiles = array_reverse(CF::getFiles('config', $f, $domain, true));

                foreach ($appFiles as $file) {
                    $appConfig = include $file;
                    if (!is_array($appConfig)) {
                        throw new CApp_Exception(c::__('Invalid config format on :file', [':file' => $file]));
                    }
                    $config = array_merge($config, $appConfig);
                }
            }
            self::$configData[$domain] = $config;
        }
        $domainData = carr::get(self::$configData, $domain);

        return carr::get($domainData, $camelKey, carr::get($domainData, $key));
    }
}
