<?php

/**
 * Description of Session
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Session {
    // Flash variables
    protected static $flash;

    /**
     * Set a flash variable.
     *
     * @param   string|array  key, or array of values
     * @param   mixed         value (if keys is not an array)
     * @param mixed $keys
     * @param mixed $val
     *
     * @return void
     */
    public function set_flash($keys, $val = false) {
        if (empty($keys)) {
            return false;
        }

        if (!is_array($keys)) {
            $keys = [$keys => $val];
        }

        foreach ($keys as $key => $val) {
            if ($key == false) {
                continue;
            }

            static::$flash[$key] = 'new';
            static::set($key, $val);
        }
    }

    /**
     * Freshen one, multiple or all flash variables.
     *
     * @param   string  variable key(s)
     * @param null|mixed $keys
     *
     * @return void
     */
    public function keep_flash($keys = null) {
        $keys = ($keys === null) ? array_keys(static::$flash) : func_get_args();

        foreach ($keys as $key) {
            if (isset(static::$flash[$key])) {
                static::$flash[$key] = 'new';
            }
        }
    }

    /**
     * Expires old flash data and removes it from the session.
     *
     * @return void
     */
    public function expire_flash() {
        static $run;

        // Method can only be run once
        if ($run === true) {
            return;
        }

        if (!empty(static::$flash)) {
            foreach (static::$flash as $key => $state) {
                if ($state === 'old') {
                    // Flash has expired
                    unset(static::$flash[$key], $_SESSION[$key]);
                } else {
                    // Flash will expire
                    static::$flash[$key] = 'old';
                }
            }
        }

        // Method has been run
        $run = true;
    }
}
//@codingStandardsIgnoreEnd
