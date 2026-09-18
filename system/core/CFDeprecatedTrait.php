<?php

/**
 * Description of CFDeprecatedTrait.
 *
 *
 * @see CF
 */
// @codingStandardsIgnoreStart
trait CFDeprecatedTrait {
    /**
     * @deprecated
     *
     * @param string $directory
     * @param string $domain
     *
     * @return array array of directory
     */
    public static function get_dirs($directory, $domain = null) {
        return self::getDirs($directory, $domain);
    }

    /**
     * @deprecated
     *
     * @param string $directory
     * @param string $domain
     *
     * @return null|string directory
     */
    public static function get_dir($directory = '', $domain = null) {
        return static::getDir($directory, $domain);
    }

    /**
     * @deprecated Please use getFiles
     *
     * @param string $directory
     * @param string $filename
     * @param string $domain
     *
     * @return string[]
     */
    public static function get_files($directory, $filename, $domain = null) {
        return self::getFiles($directory, $filename, $domain);
    }

    /**
     * @deprecated
     *
     * @param string $directory
     * @param string $filename
     * @param string $domain
     *
     * @return string
     */
    public static function get_file($directory, $filename, $domain = null) {
        return self::getFile($directory, $filename, $domain);
    }

    /**
     * @param type $filename
     * @param type $domain
     *
     * @return type
     *
     * @deprecated
     */
    public static function get_config($filename, $domain = null) {
        $files = self::getFiles('config', $filename, $domain);
        $files = array_reverse($files);
        $ret = [];
        foreach ($files as $file) {
            $cfg = include $file;
            $ret = array_merge($ret, $cfg);
        }

        return $ret;
    }

    /**
     * Get all include paths. APPPATH is the first path, followed by module
     * paths in the order they are configured, follow by the SYSPATH.
     *
     * @deprecated
     *
     * @param   bool  re-process the include paths
     * @param mixed $process
     * @param mixed $with_theme
     *
     * @return array
     *
     * @see CF::paths
     */
    public static function include_paths($process = false, $with_theme = false) {
        return self::paths();
    }

    /**
     * Add Shared App in runtime
     * This function is deprecated, use CF::addSharedApp.
     *
     * @deprecated
     *
     * @param string $app_code
     */
    public static function add_shared_app_code($app_code) {
        return self::addSharedApp($app_code);
    }

    /**
     * Get application id for domain
     * This function is deprecated, use CF::appId.
     *
     * @deprecated
     *
     * @param null|mixed $domain
     *
     * @return string
     */
    public static function app_id($domain = null) {
        return self::appId($domain);
    }

    /**
     * Get application code for domain
     * This function is deprecated, use CF::appCode.
     *
     * @deprecated
     *
     * @param null|mixed $domain
     *
     * @return string
     */
    public static function app_code($domain = null) {
        return self::appCode($domain);
    }

    /**
     * Get org id for domain
     * This function is deprecated, use CF::orgId.
     *
     * @deprecated
     *
     * @param string $domain
     *
     * @return int
     */
    public static function org_id($domain = null) {
        return self::orgId($domain);
    }

    /**
     * Get org code for this domain
     * This function is deprecated, use CF::orgCode.
     *
     * @param string $domain
     *
     * @return string
     *
     * @deprecated
     */
    public static function org_code($domain = null) {
        return self::orgCode($domain);
    }

    /**
     * Returns all traits used by a class, its subclasses and trait of their traits.
     *
     * @param object|string $class
     *
     * @return array
     *
     * @deprecated
     */
    public static function class_uses_recursive($class) {
        return static::classUsesRecursive($class);
    }

    /**
     * Returns all traits used by a trait and its traits.
     *
     * @param string $trait
     *
     * @return array
     *
     * @deprecated
     */
    public static function trait_uses_recursive($trait) {
        return static::traitUsesRecursive($trait);
    }

    /**
     * Get the class "basename" of the given object / class.
     *
     * @param string|object $class
     *
     * @return string
     *
     * @deprecated
     */
    public static function class_basename($class) {
        return static::classBasename($class);
    }

    /**
     * Displays a 404 page.
     *
     * @param   string  URI of page
     * @param   string  custom template
     * @param mixed $page
     * @param mixed $template
     *
     * @throws C_404_Exception
     *
     * @return void
     *
     * @deprecated
     */
    public static function show_404($page = false, $template = false) {
        return self::show404($page, $template);
    }

    /**
     * @deprecated
     *
     * @param string $directory
     * @param string $filename
     * @param bool   $required
     * @param bool   $ext
     * @param bool   $reload
     * @param bool   $withShared
     *
     * @return string|bool
     */
    public static function find_file($directory, $filename, $required = false, $ext = false, $reload = false, $withShared = true) {
        /** @var CF $this */
        return static::findFile($directory, $filename, $required, $ext, $reload, $withShared);
    }

    /**
     * Retrieves current user agent information:
     * keys:  browser, version, platform, mobile, robot, referrer, languages, charsets
     * tests: is_browser, is_mobile, is_robot, accept_lang, accept_charset.
     *
     * @param string $key     key or test name
     * @param string $compare used with "accept" tests: user_agent(accept_lang, en)
     *
     * @return array  languages and charsets
     * @return string all other keys
     * @return bool   all tests
     *
     * @deprecated
     */
    public static function user_agent($key = 'agent', $compare = null) {
        return static::userAgent($key, $compare);
    }

    /**
     * Retrieves current user agent information:
     * keys:  browser, version, platform, mobile, robot, referrer, languages, charsets
     * tests: is_browser, is_mobile, is_robot, accept_lang, accept_charset.
     *
     * @param   string   key or test name
     * @param   string   used with "accept" tests: user_agent(accept_lang, en)
     * @param mixed      $key
     * @param null|mixed $compare
     *
     * @return array  languages and charsets
     * @return string all other keys
     * @return bool   all tests
     *
     * @deprecated
     */
    public static function userAgent($key = 'agent', $compare = null) {
        static $info;
        $userAgent = CHTTP::request()->userAgent();
        // Return the raw string
        if ($key === 'agent') {
            return $userAgent;
        }

        if ($info === null) {
            // Parse the user agent and extract basic information
            $agents = self::config('user_agents');

            foreach ($agents as $type => $data) {
                foreach ($data as $agent => $name) {
                    if (stripos($userAgent, $agent) !== false) {
                        if ($type === 'browser' and preg_match('|' . preg_quote($agent) . '[^0-9.]*+([0-9.][0-9.a-z]*)|i', $userAgent, $match)) {
                            // Set the browser version
                            $info['version'] = $match[1];
                        }

                        // Set the agent name
                        $info[$type] = $name;

                        break;
                    }
                }
            }
        }

        if (empty($info[$key])) {
            switch ($key) {
                case 'is_robot':
                case 'is_browser':
                case 'is_mobile':
                    // A boolean result
                    $return = !empty($info[substr($key, 3)]);

                    break;
                case 'languages':
                    $return = [];
                    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                        if (preg_match_all('/[-a-z]{2,}/', strtolower(trim($_SERVER['HTTP_ACCEPT_LANGUAGE'])), $matches)) {
                            // Found a result
                            $return = $matches[0];
                        }
                    }

                    break;
                case 'charsets':
                    $return = [];
                    if (!empty($_SERVER['HTTP_ACCEPT_CHARSET'])) {
                        if (preg_match_all('/[-a-z0-9]{2,}/', strtolower(trim($_SERVER['HTTP_ACCEPT_CHARSET'])), $matches)) {
                            // Found a result
                            $return = $matches[0];
                        }
                    }

                    break;
                case 'referrer':
                    if (!empty($_SERVER['HTTP_REFERER'])) {
                        // Found a result
                        $return = trim($_SERVER['HTTP_REFERER']);
                    }

                    break;
            }

            // Cache the return value
            isset($return) and $info[$key] = $return;
        }

        if (!empty($compare)) {
            // The comparison must always be lowercase
            $compare = strtolower($compare);

            switch ($key) {
                case 'accept_lang':
                    // Check if the lange is accepted
                    return in_array($compare, static::userAgent('languages'));

                    break;
                case 'accept_charset':
                    // Check if the charset is accepted
                    return in_array($compare, static::userAgent('charsets'));

                    break;
                default:
                    // Invalid comparison
                    return false;

                    break;
            }
        }

        // Return the key, if set
        return isset($info[$key]) ? $info[$key] : null;
    }

    /**
     * Displays nice backtrace information.
     *
     * @see http://php.net/debug_backtrace
     *
     * @param   array   backtrace generated by an exception or debug_backtrace
     * @param mixed $trace
     *
     * @return string
     *
     * @deprecated
     */
    public static function backtrace($trace) {
        if (!is_array($trace)) {
            return;
        }

        // Final output
        $output = [];

        foreach ($trace as $entry) {
            $temp = '<li>';

            if (isset($entry['file'])) {
                $temp .= self::lang('core.error_file_line', ['file' => preg_replace('!^' . preg_quote(DOCROOT) . '!', '', $entry['file']), 'line' => $entry['line']]);
            }

            $temp .= '<pre>';

            if (isset($entry['class'])) {
                // Add class and call type
                $temp .= $entry['class'] . $entry['type'];
            }

            // Add function
            $temp .= $entry['function'] . '( ';

            // Add function args
            if (isset($entry['args']) and is_array($entry['args'])) {
                // Separator starts as nothing
                $sep = '';

                while ($arg = array_shift($entry['args'])) {
                    if (is_string($arg) and self::isFile($arg)) {
                        // Remove docroot from filename
                        $arg = preg_replace('!^' . preg_quote(DOCROOT) . '!', '', $arg);
                    }

                    $temp .= $sep . c::e(@print_r($arg, true));

                    // Change separator to a comma
                    $sep = ', ';
                }
            }

            $temp .= ' )</pre></li>';

            $output[] = $temp;
        }

        return '<ul class="backtrace">' . implode("\n", $output) . '</ul>';
    }

    /**
     * @param string $domain
     *
     * @return array
     *
     * @deprecated
     */
    public static function domain_data($domain) {
        $data = CFData::get($domain, 'domain');
        $result = [];
        $result['app_id'] = '';
        $result['app_code'] = '';
        $result['org_id'] = '';
        $result['org_code'] = '';
        $result['store_id'] = '';
        $result['store_code'] = '';
        $result['shared_app_code'] = [];
        $result['theme'] = '';

        if ($data != null) {
            $result['app_id'] = isset($data['app_id']) ? $data['app_id'] : null;
            $result['app_code'] = isset($data['app_code']) ? $data['app_code'] : null;
            $result['org_id'] = isset($data['org_id']) ? $data['org_id'] : null;
            $result['org_code'] = isset($data['org_code']) ? $data['org_code'] : null;
            $result['store_id'] = isset($data['store_id']) ? $data['store_id'] : null;
            $result['store_code'] = isset($data['store_code']) ? $data['store_code'] : null;
            $result['shared_app_code'] = isset($data['shared_app_code']) ? $data['shared_app_code'] : [];
            $result['theme'] = isset($data['theme']) ? $data['theme'] : null;
        }

        return $result;
    }

    /**
     * Fetch an i18n language item.
     *
     * @param null|mixed $key  language key to fetch
     * @param array      $args additional information to insert into the line
     *
     * @return string i18n language string, or the requested key if the i18n item is not found
     *
     * @deprecated since 1.2, use c::__
     */
    public static function trans($key = null, array $args = []) {
        return c::__($key, $args);
    }

    /**
     * Fetch an i18n language item.
     *
     * @param null|string $key    language key to fetch
     * @param null|array  $args   argument for replace
     * @param null|array  $locale additional information to insert into the line
     *
     * @return string i18n language string, or the requested key if the i18n item is not found
     *
     * @deprecated since 1.2, use c::__
     */
    public static function lang($key = null, array $args = [], $locale = null) {
        return c::__($key, $args, $locale);
    }

    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param mixed         $value
     * @param null|callable $callback
     *
     * @return mixed
     *
     * @deprecated since 1.2, use c::tap
     */
    public static function tap($value, $callback = null) {
        return c::tap($value, $callback);
    }

    /**
     * Get the class "basename" of the given object / class.
     *
     * @param string|object $class
     *
     * @return string
     *
     * @deprecated  since 1.2, use c::classBlasename
     */
    public static function classBasename($class) {
        return c::classBasename($class);
    }

    /**
     * Returns all traits used by a class, its subclasses and trait of their traits.
     *
     * @param object|string $class
     *
     * @return array
     *
     * @deprecated since 1.2, use c::classUsesRecursive
     */
    public static function classUsesRecursive($class) {
        return c::classUsesRecursive($class);
    }

    /**
     * Returns all traits used by a trait and its traits.
     *
     * @param string $trait
     *
     * @return array
     *
     * @deprecated since 1.2, use c::traitUsesRecursive
     */
    public static function traitUsesRecursive($trait) {
        return c::traitUsesRecursive($trait);
    }

    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     *
     * @return mixed
     *
     * @deprecated 1.2 use c::value
     */
    public static function value($value) {
        return c::value($value);
    }

    /**
     * Get an item from an array or object using "dot" notation.
     *
     * @param mixed        $target
     * @param string|array $key
     * @param mixed        $default
     *
     * @return mixed
     *
     * @deprecated 1.2 use c::get
     */
    public static function get($target, $key, $default = null) {
        return c::get($target, $key, $default);
    }

    /**
     * Set an item on an array or object using dot notation.
     *
     * @param mixed        $target
     * @param string|array $key
     * @param mixed        $value
     * @param bool         $overwrite
     *
     * @return mixed
     *
     * @deprecated 1.2 use c::set
     */
    public static function set(&$target, $key, $value, $overwrite = true) {
        return c::set($target, $key, $value);
    }

    /**
     * Create a collection from the given value.
     *
     * @param mixed $value
     *
     * @return CCollection
     *
     * @deprecated 1.1, use c::collect
     */
    public static function collect($value = null) {
        return c::collect($value);
    }
}
// @codingStandardsIgnoreStart
