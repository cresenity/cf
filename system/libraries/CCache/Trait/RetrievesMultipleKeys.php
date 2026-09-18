<?php

defined('SYSPATH') or die('No direct access allowed.');

trait CCache_Trait_RetrievesMultipleKeys {
    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @param array $keys
     *
     * @return array
     */
    public function many(array $keys) {
        $return = [];

        foreach ($keys as $key => $default) {
            if (!is_string($key)) {
                list($key, $default) = [$default, null];
            }
            $value = $this->get($key, $default);
            $return[$key] = $value === null ? $default : $value;
        }

        return $return;
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param array $values
     * @param int   $seconds
     *
     * @return bool
     */
    public function putMany(array $values, $seconds) {
        $manyResult = null;

        foreach ($values as $key => $value) {
            $result = $this->put($key, $value, $seconds);

            $manyResult = is_null($manyResult) ? $result : $result && $manyResult;
        }

        return $manyResult ?: false;
    }
}
