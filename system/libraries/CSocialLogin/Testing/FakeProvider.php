<?php

/**
 * Stand-in provider returned by CSocialLogin::driver() after CSocialLogin::fake().
 */
class CSocialLogin_Testing_FakeProvider implements CSocialLogin_AbstractProviderInterface {
    use CTrait_ForwardsCalls;

    /**
     * @var string
     */
    protected $driver;

    /**
     * @var null|\Closure|CSocialLogin_Contract_UserInterface
     */
    protected $user;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var null|CSocialLogin_AbstractProviderInterface
     */
    protected $provider;

    /**
     * @param string                                             $driver
     * @param null|\Closure|CSocialLogin_Contract_UserInterface $user
     */
    public function __construct($driver, $user = null) {
        $this->driver = $driver;
        $this->user = $user;
    }

    /**
     * Remember the options the app passed to CSocialLogin::driver(), for the real provider fallback.
     *
     * @param array $config
     *
     * @return $this
     */
    public function withConfig(array $config) {
        if ($config) {
            $this->config = $config;
            $this->provider = null;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function redirect() {
        return new CHTTP_RedirectResponse('https://sociallogin.fake/' . $this->driver . '/authorize');
    }

    /**
     * @inheritdoc
     */
    public function user() {
        if ($this->user instanceof Closure) {
            return call_user_func($this->user);
        }

        return $this->user !== null ? $this->user : CSocialLogin_OAuth2_User::fake();
    }

    /**
     * The real provider this fake stands in for (built lazily from the remembered config).
     *
     * @return CSocialLogin_AbstractProviderInterface
     */
    public function provider() {
        if ($this->provider === null) {
            $this->provider = (new CSocialLogin_DriverManager())->setConfig($this->config)->driver($this->driver);
        }

        return $this->provider;
    }

    /**
     * Forward fluent calls (scopes(), stateless(), with(), ...) to the real provider.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, array $parameters) {
        return $this->forwardDecoratedCallTo($this->provider(), $method, $parameters);
    }
}
