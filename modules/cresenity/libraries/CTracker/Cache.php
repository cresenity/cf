<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 23, 2019, 1:33:21 AM
 */
class CTracker_Cache extends CCache_Repository {
    protected $config;

    public function __construct($options = []) {
        $this->config = CTracker_Config::instance();
        if (empty($options)) {
            $options = [
                'driver' => 'File',
                'options' => [
                    'engine' => 'Temp',
                    'options' => [
                        'directory' => 'CTracker'
                    ],
                ],
            ];
        }
        parent::__construct($options);
    }

    /**
     * @param string $key
     *
     * @return array
     */
    public function findCachedWithKey($key) {
        if ($this->config->isCacheEnabled()) {
            return $this->get($key);
        }
    }

    /**
     * @param string $identifier
     * @param mixed  $attributes
     * @param mixed  $keys
     */
    public function findCached($attributes, $keys, $identifier = null) {
        if (!$this->config->isCacheEnabled()) {
            return;
        }
        $key = $this->makeCacheKey($attributes, $keys, $identifier);

        return [
            $this->findCachedWithKey($key),
            $key,
        ];
    }

    public function makeCacheKey($attributes, $keys, $identifier) {
        $attributes = $this->extractAttributes($attributes);
        $cacheKey = "className=${identifier};";
        $keys = $this->extractKeys($attributes, $keys, $identifier);
        foreach ($keys as $key) {
            if (isset($attributes[$key])) {
                $cacheKey .= "${key}={$attributes[$key]};";
            }
        }

        return sha1($cacheKey);
    }

    public function cachePut($cacheKey, $model) {
        if ($this->config->isCacheEnabled()) {
            return $this->set($cacheKey, $model);
        }
    }

    public function makeKeyAndPut($model, $key) {
        $key = $this->makeCacheKey($model, $key, get_class($model));
        $this->cachePut($key, $model);
    }

    private function extractAttributes($attributes) {
        if (is_array($attributes) || is_string($attributes)) {
            return $attributes;
        }
        if (is_string($attributes) || is_numeric($attributes)) {
            return (array) $attributes;
        }
        if ($attributes instanceof CModel) {
            return $attributes->getAttributes();
        }
    }

    /**
     * @param $attributes
     * @param $keys
     *
     * @return array
     */
    private function extractKeys($attributes, $keys) {
        if (!$keys) {
            $keys = array_keys($attributes);
        }
        if (!is_array($keys)) {
            $keys = (array) $keys;

            return $keys;
        }

        return $keys;
    }
}
