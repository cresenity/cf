<?php

defined('SYSPATH') or die('No direct access allowed.');

//@codingStandardsIgnoreStart
class curl {
    //@codingStandardsIgnoreEnd

    /**
     * Fetches the current URI.
     *
     * @param bool $qs include the query string
     *
     * @return string
     */
    public static function current($qs = false) {
        return ltrim(($qs === true) ? c::request()->getRequestUri() : c::request()->path(), '/');
    }

    /**
     * Base URL, with or without the index page.
     *
     * If protocol (and core.site_protocol) and core.site_domain are both empty,
     * then
     *
     * @param bool $index    include the index page
     * @param bool $protocol non-default protocol
     *
     * @return string
     */
    public static function base($index = false, $protocol = false) {
        // Load the site domain
        $site_domain = (string) CF::config('core.site_domain', '');
        $domain = carr::get($_SERVER, 'HTTP_HOST');
        if (!$domain) {
            $domain = CF::domain();
        }
        if ($protocol == false) {
            if ($site_domain === '' or $site_domain[0] === '/') {
                // Use the configured site domain
                $base_url = $site_domain;
            } else {
                // Guess the protocol to provide full http://domain/path URL
                $base_url = ((empty($_SERVER['HTTPS']) or $_SERVER['HTTPS'] === 'off') ? 'http' : 'https') . '://' . $site_domain;
            }
        } else {
            if ($site_domain === '' or $site_domain[0] === '/') {
                // Guess the server name if the domain starts with slash
                $base_url = $protocol . '://' . $domain . $site_domain;
            } else {
                // Use the configured site domain
                $base_url = $protocol . '://' . $site_domain;
            }
        }

        if ($index === true and $index = CF::config('core.index_page')) {
            // Append the index page
            $base_url = $base_url . $index;
        }

        // Force a slash on the end of the URL
        return rtrim($base_url, '/') . '/';
    }

    public static function httpbase() {
        return curl::base(false, (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http');
    }

    //@codingStandardsIgnoreStart
    public static function url_full() {
        return self::urlFull();
    }

    //@codingStandardsIgnoreENd

    /**
     * @return string
     */
    public static function urlFull() {
        return static::fullUrl();
    }

    /**
     * @param bool $qs
     *
     * @return string
     */
    public static function fullUrl($qs = true) {
        return $qs ? c::url()->full() : c::url()->current();
    }

    /**
     * Fetches an absolute site URL based on a URI segment.
     *
     * @param   string  site URI to convert
     * @param   string  non-default protocol
     * @param mixed $uri
     * @param mixed $protocol
     *
     * @return string
     */
    public static function site($uri = '', $protocol = false) {
        if ($path = trim(parse_url($uri, PHP_URL_PATH), '/')) {
            // Add path suffix
            $path .= CF::config('core.url_suffix');
        }

        if ($query = parse_url($uri, PHP_URL_QUERY)) {
            // ?query=string
            $query = '?' . $query;
        }

        if ($fragment = parse_url($uri, PHP_URL_FRAGMENT)) {
            // #fragment
            $fragment = '#' . $fragment;
        }

        // Concat the URL
        return curl::base(true, $protocol) . $path . $query . $fragment;
    }

    /**
     * Return the URL to a file. Absolute filenames and relative filenames
     * are allowed.
     *
     * @param   string   filename
     * @param   bool  include the index page
     * @param mixed $file
     * @param mixed $index
     *
     * @return string
     */
    public static function file($file, $index = false) {
        if (strpos($file, '://') === false) {
            // Add the base URL to the filename
            $file = curl::base($index) . $file;
        }

        return $file;
    }

    /**
     * Convert a phrase to a URL-safe title.
     *
     * @param string $title     phrase to convert
     * @param string $separator word separator (- or _)
     *
     * @return string
     */
    public static function title($title, $separator = '-') {
        $separator = ($separator === '-') ? '-' : '_';

        // Replace accented characters by their unaccented equivalents
        $title = CUTF8::transliterateToAscii($title);

        // Remove all characters that are not the separator, a-z, 0-9, or whitespace
        $title = preg_replace('/[^' . $separator . 'a-z0-9\s]+/', '', strtolower($title));

        // Replace all separator characters and whitespace by a single separator
        $title = preg_replace('/[' . $separator . '\s]+/', $separator, $title);

        // Trim separators from the beginning and end
        return trim($title, $separator);
    }

    /**
     * Sends a page redirect header and runs the system.redirect Event.
     *
     * @param mixed  $uri    string site URI or URL to redirect to, or array of strings if method is 300
     * @param string $method HTTP method of redirect
     *
     * @return false
     */
    public static function redirect($uri = '', $method = '302') {
        if (CFEvent::hasRun('system.send_headers')) {
            return false;
        }

        $codes = [
            'refresh' => 'Refresh',
            '300' => 'Multiple Choices',
            '301' => 'Moved Permanently',
            '302' => 'Found',
            '303' => 'See Other',
            '304' => 'Not Modified',
            '305' => 'Use Proxy',
            '307' => 'Temporary Redirect'
        ];

        // Validate the method and default to 302
        $method = isset($codes[$method]) ? (string) $method : '302';

        if ($method === '300') {
            $uri = (array) $uri;

            $output = '<ul>';
            foreach ($uri as $link) {
                $output .= '<li>' . chtml::anchor($link) . '</li>';
            }
            $output .= '</ul>';

            // The first URI will be used for the Location header
            $uri = $uri[0];
        } else {
            $output = '<p>' . chtml::anchor($uri) . '</p>';
        }

        // Run the redirect event
        CFEvent::run('system.redirect', $uri);

        if (is_string($uri) && strpos($uri, '://') === false) {
            // HTTP headers expect absolute URLs
            $uri = curl::site($uri, static::protocol());
        }

        if ($method === 'refresh') {
            header('Refresh: 0; url=' . $uri);
        } else {
            header('HTTP/1.1 ' . $method . ' ' . $codes[$method]);
            header('Location: ' . $uri);
        }

        // We are about to exit, so run the send_headers event
        CFEvent::run('system.send_headers');
        //force save the session
        if ($session = c::session()) {
            $session->save();
        }
        exit('<h1>' . $method . ' - ' . $codes[$method] . '</h1>' . $output);
    }

    /**
     * @param string      $val
     * @param null|string $key pass null to no key
     *
     * @return string
     */
    public static function asPostString($val, $key = null) {
        $result = '';
        $prefix = $key;

        if (is_array($val)) {
            foreach ($val as $k => $v) {
                if ($prefix === null) {
                    $result .= '&' . static::asPostString($v, $k);
                } else {
                    $result .= '&' . static::asPostString($v, $prefix . '[' . $k . ']');
                }
            }
        } else {
            $encoded_val = urlencode($val);
            if (isset($val[0]) && $val[0] == '@') {
                //$encoded_val = '@' . urlencode(substr($val, 1));
                $encoded_val = $val;
            }
            $result .= '&' . urlencode((string) $prefix) . '=' . $encoded_val;
        }

        $result = substr($result, 1);

        return $result;
    }

    /**
     * @param string      $val
     * @param null|string $key
     *
     * @return string
     *
     * @deprecated
     */
    public static function as_post_string($val, $key = null) {
        CF::deprecated(__METHOD__, 'http_build_query()', null, null, true);
        return static::asPostString($val, $key);
    }

    /**
     * @param string $url
     *
     * @return string
     */
    public static function removeScheme($url) {
        return preg_replace('~^[^:/?#]+:(//)?~', '', $url);
    }

    /**
     * @param string $url
     *
     * @return string
     *
     * @deprecated
     */
    //@codingStandardsIgnoreStart
    public static function remove_scheme($url) {
        CF::deprecated(__METHOD__, 'curl::removeScheme()', null, null, true);
        return static::removeScheme($url);
    }

    //@codingStandardsIgnoreEnd

    /**
     * Returns the current request protocol, based on $_SERVER['https']. In CLI
     * mode, NULL will be returned.
     *
     * @return string
     */
    public static function protocol() {
        if (PHP_SAPI === 'cli') {
            return null;
        } elseif (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] === 'on') {
            return 'https';
        } else {
            return 'http';
        }
    }
}
