<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 20, 2018, 1:13:03 AM
 */

/**
 * A registry for custom helpers.
 */
class CTemplate_Helpers {
    /**
     * The map of registered helpers.
     *
     * @var array
     */
    protected $map = [];

    /**
     * Constructor.
     *
     * @param array $map a map of helpers
     */
    public function __construct(array $map = []) {
        $this->map = $map;
    }

    /**
     * Magic call to invoke helpers as methods on this registry.
     *
     * @param string $name the registered helper name
     * @param array  $args arguments to pass to the helper invocation
     *
     * @return mixed
     */
    public function __call($name, $args) {
        return call_user_func_array($this->get($name), $args);
    }

    /**
     * Registers a helper.
     *
     * @param string   $name     register the helper under this name
     * @param callable $callable the callable helper
     *
     * @return null
     */
    public function set($name, $callable) {
        $this->map[$name] = $callable;
    }

    /**
     * Is a named helper registered?
     *
     * @param string $name the helper name
     *
     * @return bool
     */
    public function has($name) {
        return isset($this->map[$name]);
    }

    /**
     * Gets a helper from the registry.
     *
     * @param string $name the helper name
     *
     * @return callable
     */
    public function get($name) {
        if (!$this->has($name)) {
            throw new CTemplate_Exception_HelperNotFound($name);
        }
        return $this->map[$name];
    }
}
