<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Session Class.
 *
 * @see CSession_Store
 *
 * @method void set(string $key, null|mixed $value = null)
 * @method void delete($keys)
 */
class CSession {
    use CTrait_Compat_Session;

    protected $initialized = false;

    /**
     * Session singleton.
     *
     * @var CSession
     */
    protected static $instance;

    /**
     * @var CSession_Store
     */
    protected $store;

    /**
     * Singleton instance of Session.
     *
     * @return CSession
     */
    public static function instance() {
        if (self::$instance == null) {
            // Create a new instance
            self::$instance = new CSession();
        }

        return self::$instance;
    }

    /**
     * On first session instance creation, sets up the driver and creates session.
     */
    private function __construct() {
        $this->initializeSession();
    }

    /**
     * @return CSession_Store
     */
    public static function store() {
        return CBase::session();
    }

    /**
     * Get the session id.
     *
     * @return string
     */
    public function id() {
        return $this->store()->getId();
    }

    public function __call($name, $arguments) {
        return call_user_func_array([$this->store(), $name], $arguments);
    }

    public static function manager() {
        return CSession_Manager::instance();
    }

    protected function initializeSession() {
        if (!$this->initialized && static::sessionConfigured()) {
            $this->initialized = true;

            return CBase::session();
        }
    }

    /**
     * Determine if a session driver has been configured.
     *
     * @return bool
     */
    public static function sessionConfigured() {
        return !is_null(carr::get(static::manager()->getSessionConfig(), 'driver'));
    }

    /**
     * @deprecated 1.3
     *
     * @return void
     */
    public function destroy() {
        return $this->store()->invalidate();
    }
}

// End Session Class
