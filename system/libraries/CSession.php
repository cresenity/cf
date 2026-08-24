<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Session Class.
 *
 * @see CSession_Store
 *
 * @method bool start()
 * @method void save()
 * @method void ageFlashData()
 * @method array all()
 * @method array only(array $keys)
 * @method bool exists($key)
 * @method bool missing($key)
 * @method bool has($key)
 * @method mixed get($key, $default = null)
 * @method mixed pull($key, $default = null)
 * @method bool hasOldInput($key = null)
 * @method mixed getOldInput($key = null, $default = null)
 * @method void replace(array $attributes)
 * @method void put($key, $value = null)
 * @method void set($key, $value = null)
 * @method mixed remember($key, Closure $callback)
 * @method void push($key, $value)
 * @method mixed increment($key, $amount = 1)
 * @method int decrement($key, $amount = 1)
 * @method void flash($key, $value = true)
 * @method void now($key, $value)
 * @method void reflash()
 * @method void keep($keys = null)
 * @method void flashInput(array $value)
 * @method mixed remove($key)
 * @method void forget($keys)
 * @method void delete($keys)
 * @method void flush()
 * @method bool invalidate()
 * @method bool regenerate($destroy = false)
 * @method bool migrate($destroy = false)
 * @method bool isStarted()
 * @method string getName()
 * @method void setName($name)
 * @method string getId()
 * @method void setId($id)
 * @method bool isValidId($id)
 * @method void setExists($value)
 * @method null|string token()
 * @method void regenerateToken()
 * @method null|string previousUrl()
 * @method void setPreviousUrl($url)
 * @method void passwordConfirmed()
 * @method \SessionHandlerInterface getHandler()
 * @method bool handlerNeedsRequest()
 * @method void setRequestOnHandler($request)
 * @method mixed updateLastActivity()
 * @method mixed updateTotalHits()
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
     *
     * @deprecated since 1.6, use c::session()
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
