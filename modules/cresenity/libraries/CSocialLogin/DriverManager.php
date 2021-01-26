<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since May 15, 2019, 8:13:37 PM
 */
use League\OAuth1\Client\Server\Twitter as TwitterServer;

class CSocialLogin_DriverManager {
    use CTrait_Manager_DriverManager;

    protected $config;

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
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_LinkedInProvider::class, $this->config);
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
    protected function createSignInWithAppleDriver() {
        return $this->buildProvider(CSocialLogin_OAuth2_Provider_SignInWithAppleProvider::class, $this->config);
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
        return new CSocialLogin_OAuth1_Provider_TwitterProvider(new TwitterServer($this->formatConfig($this->config)));
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
        //return cstr::startsWith($redirect, '/') ? $this->app['url']->to($redirect) : $redirect;
        return $redirect;
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
