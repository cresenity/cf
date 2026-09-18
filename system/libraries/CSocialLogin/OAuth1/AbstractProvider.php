<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 16, 2019, 4:24:44 PM
 */
use League\OAuth1\Client\Server\Server;
use League\OAuth1\Client\Credentials\TokenCredentials;

abstract class CSocialLogin_OAuth1_AbstractProvider extends CSocialLogin_AbstractProvider {
    /**
     * The HTTP request instance.
     *
     * @var \CHTTP_Request
     */
    protected $request;

    /**
     * The OAuth server implementation.
     *
     * @var \League\OAuth1\Client\Server\Server
     */
    protected $server;

    /**
     * A hash representing the last requested user.
     *
     * @var string
     */
    protected $userHash;

    /**
     * Create a new provider instance.
     *
     * @param \CHTTP_Request                      $request
     * @param \League\OAuth1\Client\Server\Server $server
     *
     * @return void
     */
    public function __construct(CHTTP_Request $request, Server $server) {
        $this->server = $server;
        $this->request = $request;
    }

    /**
     * Redirect the user to the authentication page for the provider.
     *
     * @return \CHTTP_RedirectResponse
     */
    public function redirect() {
        $this->request->session()->put(
            'oauth.temp',
            $temp = $this->server->getTemporaryCredentials()
        );

        return new CHTTP_RedirectResponse($this->server->getAuthorizationUrl($temp));
    }

    /**
     * Get the User instance for the authenticated user.
     *
     * @throws \CSocialLogin_OAuth1_Exception_MissingVerifierException
     *
     * @return \CSocialLogin_OAuth1_User
     */
    public function user() {
        if (!$this->hasNecessaryVerifier()) {
            throw new CSocialLogin_OAuth1_Exception_MissingVerifierException('Invalid request. Missing OAuth verifier.');
        }

        $token = $this->getToken();

        $user = $this->server->getUserDetails(
            $token,
            $this->shouldBypassCache($token->getIdentifier(), $token->getSecret())
        );

        $instance = (new CSocialLogin_OAuth1_User())->setRaw($user->extra)
            ->setToken($token->getIdentifier(), $token->getSecret());

        return $instance->map([
            'id' => $user->uid,
            'nickname' => $user->nickname,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->imageUrl,
        ]);
    }

    /**
     * Get a Social User instance from a known access token and secret.
     *
     * @param string $token
     * @param string $secret
     *
     * @return \CSocialLogin_OAuth1_User
     */
    public function userFromTokenAndSecret($token, $secret) {
        $tokenCredentials = new TokenCredentials();

        $tokenCredentials->setIdentifier($token);
        $tokenCredentials->setSecret($secret);

        $user = $this->server->getUserDetails(
            $tokenCredentials,
            $this->shouldBypassCache($token, $secret)
        );

        $instance = (new CSocialLogin_OAuth1_User())->setRaw($user->extra)
            ->setToken($tokenCredentials->getIdentifier(), $tokenCredentials->getSecret());

        return $instance->map([
            'id' => $user->uid,
            'nickname' => $user->nickname,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->imageUrl,
        ]);
    }

    /**
     * Get the token credentials for the request.
     *
     * @return \League\OAuth1\Client\Credentials\TokenCredentials
     */
    protected function getToken() {
        $temp = $this->request->session()->get('oauth.temp');

        if (!$temp) {
            throw new CSocialLogin_OAuth1_Exception_MissingTemporaryCredentialsException('Missing temporary OAuth credentials.');
        }

        return $this->server->getTokenCredentials(
            $temp,
            $this->request->get('oauth_token'),
            $this->request->get('oauth_verifier')
        );
    }

    /**
     * Determine if the request has the necessary OAuth verifier.
     *
     * @return bool
     */
    protected function hasNecessaryVerifier() {
        return $this->request->has(['oauth_token', 'oauth_verifier']);
    }

    /**
     * Determine if the user information cache should be bypassed.
     *
     * @param string $token
     * @param string $secret
     *
     * @return bool
     */
    protected function shouldBypassCache($token, $secret) {
        $newHash = sha1($token . '_' . $secret);

        if (!empty($this->userHash) && $newHash !== $this->userHash) {
            $this->userHash = $newHash;

            return true;
        }

        $this->userHash = $this->userHash ?: $newHash;

        return false;
    }

    /**
     * Set the request instance.
     *
     * @param \CHTTP_Request $request
     *
     * @return $this
     */
    public function setRequest(CHTTP_Request $request) {
        $this->request = $request;

        return $this;
    }
}
