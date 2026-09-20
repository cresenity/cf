<?php

defined('SYSPATH') or die('No direct access allowed.');

use League\OAuth1\Client\Server\Twitter as TwitterServer;

class CSocialLogin_DriverManager {
    use CTrait_Manager_DriverManager {
        createDriver as protected createDriverFromManager;
    }

    protected $config;

    /**
     * Custom driver creators registered through extend(), shared by every manager instance.
     *
     * @var array<string, \Closure>
     */
    protected static $customDriverCreators = [];

    /**
     * Get a driver instance.
     *
     * @param string $driver
     *
     * @return mixed
     */
    public function with($driver) {
        return $this->driver($driver);
    }

    /**
     * @param array $config
     *
     * @return $this
     */
    public function setConfig($config) {
        $this->config = $config;

        return $this;
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createGithubDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_GithubProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createFacebookDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_FacebookProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createGoogleDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_GoogleProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createLinkedinDriver() {
        CF::deprecated('CSocialLogin driver linkedin', "driver 'linkedin-openid'", '1.9');

        return $this->buildProvider(CSocialLogin_OAuth2_Provider_LinkedInProvider::class, $this->config);
    }

    protected function createLinkedinOpenidDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_LinkedInOpenIdProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createBitbucketDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_BitbucketProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createGitlabDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_GitlabProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createInstagramDriver() {
        CF::deprecated('CSocialLogin driver instagram', 'Instagram API with Instagram Login (belum ada provider)', '1.9');

        return $this->buildProvider(CSocialLogin_OAuth2_Provider_InstagramProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createFigmaDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_FigmaProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createDropboxDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_DropboxProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createSignInWithAppleDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_SignInWithAppleProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createDiscordDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_DiscordProvider::class, $this->config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    protected function createAtlassianDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_AtlassianProvider::class, $this->config);
    }

    protected function createTwitterOauth2Driver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_TwitterProvider::class, $this->config);
    }

    protected function createXDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_XProvider::class, $this->config);
    }

    /**
     * Register a custom driver creator; the closure receives ($config, $request, $manager)
     * and returns a CSocialLogin_AbstractProviderInterface. Registrations are process-wide,
     * so one made in an app's bootstrap serves every CSocialLogin::driver() call.
     *
     * @param string   $driver
     * @param \Closure $callback
     *
     * @return $this
     */
    public function extend($driver, Closure $callback) {
        static::$customDriverCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Drop custom driver creators (all, or one).
     *
     * @param null|string $driver
     *
     * @return void
     */
    public static function forgetCustomCreators($driver = null) {
        if ($driver === null) {
            static::$customDriverCreators = [];
        } else {
            unset(static::$customDriverCreators[$driver]);
        }
    }

    /**
     * @inheritdoc
     */
    protected function createDriver($driver) {
        if (isset(static::$customDriverCreators[$driver])) {
            return call_user_func(static::$customDriverCreators[$driver], $this->config, CHTTP::request(), $this);
        }

        return $this->createDriverFromManager($driver);
    }

    /**
     * Build an OAuth 2 provider instance.
     *
     * @param string $provider
     * @param array  $config
     *
     * @return CSocialLogin_OAuth2_AbstractProvider
     */
    public function buildProvider($provider, $config) {
        return new $provider(
            CHTTP::request(),
            $config['client_id'],
            $config['client_secret'],
            $this->formatRedirectUrl($config),
            carr::get($config, 'guzzle', [])
        );
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return CSocialLogin_OAuth1_AbstractProvider
     */
    protected function createTwitterDriver() {
        return new CSocialLogin_OAuth1_Provider_TwitterProvider(CHTTP::request(), new TwitterServer($this->formatConfig($this->config)));
    }

    /**
     * Format the server configuration.
     *
     * @param array $config
     *
     * @return array
     */
    public function formatConfig(array $config) {
        return array_merge([
            'identifier' => $config['client_id'],
            'secret' => $config['client_secret'],
            'callback_uri' => $this->formatRedirectUrl($config),
        ], $config);
    }

    /**
     * Format the callback URL, resolving a relative URI if needed.
     *
     * @param array $config
     *
     * @return string
     */
    protected function formatRedirectUrl(array $config) {
        $redirect = c::value($config['redirect']);

        return cstr::startsWith($redirect, '/') ? c::url()->to($redirect) : $redirect;
    }

    /**
     * Forget all of the resolved driver instances.
     *
     * @return $this
     */
    public function forgetDrivers() {
        $this->drivers = [];

        return $this;
    }

    /**
     * Get the default driver name.
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    public function getDefaultDriver() {
        throw new InvalidArgumentException('No Socialite driver was specified.');
    }
}
