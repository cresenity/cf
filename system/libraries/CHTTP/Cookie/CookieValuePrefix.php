<?php

class CHTTP_Cookie_CookieValuePrefix {
    /**
     * Create a new cookie value prefix for the given cookie name.
     *
     * @param string $cookieName
     * @param string $key
     *
     * @return string
     */
    public static function create($cookieName, $key) {
        return hash_hmac('sha1', $cookieName . 'v2', $key) . '|';
    }

    /**
     * Remove the cookie value prefix.
     *
     * @param string $cookieValue
     *
     * @return string
     */
    public static function remove($cookieValue) {
        return substr($cookieValue, 41);
    }

    /**
     * Validate the value against each of the given keys and return it without the prefix, or null when none matches.
     *
     * @param string $cookieName
     * @param string $cookieValue
     * @param array  $keys
     *
     * @return null|string
     */
    public static function validate($cookieName, $cookieValue, array $keys) {
        foreach ($keys as $key) {
            if (strpos($cookieValue, static::create($cookieName, $key)) === 0) {
                return static::remove($cookieValue);
            }
        }

        return null;
    }
}
