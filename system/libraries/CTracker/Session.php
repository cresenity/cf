<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 1:51:48 AM
 */
class CTracker_Session {
    public function get($key) {
        $session = $this->getNamespaceData();

        return carr::get($session, $key);
    }

    public function has($key) {
        $session = $this->getNamespaceData();

        return isset($session[$key]);
    }

    public function set($key, $value) {
        $session = $this->getNamespaceData();
        if (!is_array($session)) {
            $session = [];
        }

        $session[$key] = $value;

        $this->setNamespaceData($session);
    }

    /**
     * Alias of function set
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function put($key, $value) {
        return $this->set($key, $value);
    }

    public static function sessionNamespace() {
        return CTracker::config()->get('sessionNamespace', 'CTracker');
    }

    protected function getNamespaceData() {
        return CSession::instance()->get(static::sessionNamespace(), []);
    }

    protected function setNamespaceData($value) {
        return CSession::instance()->set(static::sessionNamespace(), $value);
    }
}
