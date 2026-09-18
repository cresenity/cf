<?php

/**
 * Description of View
 */

// @codingStandardsIgnoreStart
trait CTrait_Compat_View {
    protected static $global_data = [];

    /**
     * Load a view.
     *
     * @param string $view_filename filename of view
     * @param array  $input_data    data to pass to view
     *
     * @return string
     *
     * @deprecated
     */
    public static function load_view($view_filename, $input_data) {
        return static::loadView($view_filename, $input_data);
    }

    /**
     * Sets the view filename.
     *
     * @chainable
     *
     * @param   string  view filename
     * @param   string  view file type
     * @param mixed      $name
     * @param null|mixed $type
     *
     * @return object
     *
     * @deprecated
     */
    public function set_filename($name, $type = null) {
        return $this->setFilename($name, $type);
    }

    /**
     * Sets a view global variable.
     *
     * @param string|array $name  name of variable or an array of variables
     * @param mixed        $value value when using a named variable
     *
     * @return void
     *
     * @deprecated
     */
    public static function set_global($name, $value = null) {
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                self::$global_data[$key] = $value;
            }
        } else {
            self::$global_data[$name] = $value;
        }
    }

    /**
     * Checks for a property existence in the view locally or globally. Unlike the built in __isset(),
     * this method can take an array of properties to test simultaneously.
     *
     * @param string|array $key property name to test for
     *
     * @return bool|array property test result
     *
     * @deprecated 1.2
     */
    public function is_set($key = false) {
        return $this->isSet($key);
    }
}
