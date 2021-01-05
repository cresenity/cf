<?php

/**
 * Description of Manager
 *
 * @author Hery
 */
class CSession_Manager {
    /**
     * @var CSession_Manager
     */
    private static $instance;

    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected $drivers = [];

    /**
     * @return CSession_Manager
     */
    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function __construct() {
        $this->config = CConfig::instance('session');
    }

    /**
     * Get a driver instance.
     *
     * @param string|null $driver
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function driver($driver = null) {
        $driver = $driver ?: $this->getDefaultDriver();

        if (is_null($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].',
                static::class
            ));
        }

        // If the given driver has not been created before, we will create the instances
        // here and cache it so we can return it next time very quickly. If there is
        // already a driver created by this name, we'll just return that instance.
        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = CSession_Factory::instance()->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    public function createStore($driver = null) {
        return $this->buildSession($this->driver($driver));
    }

    /**
     * Build the session instance.
     *
     * @param \SessionHandlerInterface $handler
     *
     * @return \Illuminate\Session\Store
     */
    protected function buildSession($handler) {
        return $this->config->get('encrypt') ? $this->buildEncryptedSession($handler) : new CSession_Store($this->config->get('name'), $handler);
    }

    /**
     * Build the encrypted session instance.
     *
     * @param \SessionHandlerInterface $handler
     *
     * @return \Illuminate\Session\EncryptedStore
     */
    protected function buildEncryptedSession($handler) {
        return new CSession_StoreEncrypted(
            $this->config->get('session.cookie'),
            $handler,
            $this->container['encrypter']
        );
    }

    /**
     * Determine if requests for the same session should wait for each to finish before executing.
     *
     * @return bool
     */
    public function shouldBlock() {
        return $this->config->get('block', false);
    }

    /**
     * Get the name of the cache store / driver that should be used to acquire session locks.
     *
     * @return string|null
     */
    public function blockDriver() {
        return $this->config->get('block_store');
    }

    /**
     * Get the session configuration.
     *
     * @return array
     */
    public function getSessionConfig() {
        return $this->config->all();
    }

    /**
     * Get the default session driver name.
     *
     * @return string
     */
    public function getDefaultDriver() {
        return $this->config->get('driver', 'native');
    }

    public function applyNativeSession() {
        //we will replace the $_SESSION with our adapter
        $_SESSION = new CSession_NativeAdapter();
    }
}
