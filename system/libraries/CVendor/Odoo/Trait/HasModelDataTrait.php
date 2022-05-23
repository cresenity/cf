<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 25, 2019, 10:10:42 PM
 */
trait CVendor_Odoo_Trait_HasModelDataTrait {
    /**
     * Data structure as returned by the API and converted to
     * a native PHP array.
     */
    protected $data = [];

    /**
     * Instantiate with the array data from the ERP model read.
     */
    public function __construct(array $data = []) {
        // Store away the source data.
        $this->setData($data);
    }

    protected function setData($data) {
        $this->data = $data;
    }

    public function getData() {
        return $this->data;
    }

    /**
     * Get a data field using a "dot notation" path.
     *
     * @param mixed      $key
     * @param null|mixed $default
     */
    public function get($key, $default = null) {
        // Since we are running under laravel, use laravel's helper.
        return carr::get($this->data, $key, $default);
    }

    public function __get($name) {
        return $this->get($name);
    }

    public function jsonSerialize() {
        return $this->data;
    }

    /**
     * Supports ArrayAccess.
     *
     * @param mixed $offset
     */
    public function offsetExists($offset) {
        return $this->get($offset) !== null;
    }

    /**
     * Supports ArrayAccess.
     *
     * @param mixed $offset
     */
    public function offsetGet($offset) {
        return $this->get($offset);
    }

    /**
     * Supports ArrayAccess.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        carr::set($this->data, $offset, $value);
    }

    /**
     * Supports ArrayAccess.
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset) {
        $this->offsetSet($offset, null);
    }
}
