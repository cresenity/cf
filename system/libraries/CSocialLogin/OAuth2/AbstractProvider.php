<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 15, 2019, 8:01:24 PM
 */
use GuzzleHttp\Client;

abstract class CSocialLogin_OAuth2_AbstractProvider implements CSocialLogin_AbstractProviderInterface {
    use CSocialLogin_Trait_ConfigTrait;

    /**
     * The HTTP request instance.
     *
     * @var \CHTTP_Request
     */
    protected $request;

    /**
     * The HTTP Client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * The client ID.
     *
     * @var string
     */
    protected $clientId;

    /**
     * The client secret.
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * The redirect URL.
     *
     * @var string
     */
    protected $redirectUrl;

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ',';

    /**
     * The type of the encoding in the query.
     *
     * @var int can be either PHP_QUERY_RFC3986 or PHP_QUERY_RFC1738
     */
    protected $encodingType = PHP_QUERY_RFC1738;

    /**
     * Indicates if the session state should be utilized.
     *
     * @var bool
     */
    protected $stateless = false;

    /**
     * Indicates if PKCE should be used.
     *
     * @var bool
     */
    protected $usesPKCE = false;

    /**
     * The custom Guzzle configuration options.
     *
     * @var array
     */
    protected $guzzle = [];

    /**
     * The cached user instance.
     *
     * @var null|\CSocialLogin_OAuth2_User
     */
    protected $user;

    /**
     * Create a new provider instance.
     *
     * @param \CHTTP_Request $request
     * @param string         $clientId
     * @param string         $clientSecret
     * @param string         $redirectUrl
     * @param array          $guzzle
     *
     * @return void
     */
    public function __construct(CHTTP_Request $request, $clientId, $clientSecret, $redirectUrl, $guzzle = []) {
        $this->guzzle = $guzzle;
        $this->request = $request;
        $this->clientId = $clientId;
        $this->redirectUrl = $redirectUrl;
        $this->clientSecret = $clientSecret;
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     *
     * @return string
     */
    abstract protected function getAuthUrl($state);

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    abstract protected function getTokenUrl();

    /**
     * Get the raw user for the given access token.
     *
     * @param string $token
     *
     * @return array
     */
    abstract protected function getUserByToken($token);

    /**
     * Map the raw user array to a Socialite User instance.
     *
     * @param array $user
     *
     * @return \CSocialLogin_OAuth2_User
     */
    abstract protected function mapUserToObject(array $user);

    /**
     * Redirect the user of the application to the provider's authentication screen.
     *
     * @return \CHTTP_RedirectResponse
     */
    public function redirect() {
        $state = null;

        if ($this->usesState()) {
            $this->request->session()->put('state', $state = $this->getState());
        }

        if ($this->usesPKCE()) {
            $this->request->session()->put('code_verifier', $this->getCodeVerifier());
        }

        return new CHTTP_RedirectResponse($this->getAuthUrl($state));
    }

    /**
     * Build the authentication URL for the provider from the given base URL.
     *
     * @param string $url
     * @param string $state
     *
     * @return string
     */
    protected function buildAuthUrlFromBase($url, $state) {
        return $url . '?' . http_build_query($this->getCodeFields($state), '', '&', $this->encodingType);
    }

    /**
     * Get the GET parameters for the code request.
     *
     * @param null|string $state
     *
     * @return array
     */
    protected function getCodeFields($state = null) {
        $fields = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->getScopes(), $this->scopeSeparator),
            'response_type' => 'code',
        ];

        if ($this->usesState()) {
            $fields['state'] = $state;
        }

        if ($this->usesPKCE()) {
            $fields['code_challenge'] = $this->getCodeChallenge();
            $fields['code_challenge_method'] = $this->getCodeChallengeMethod();
        }

        return array_merge($fields, $this->parameters);
    }

    /**
     * Format the given scopes.
     *
     * @param array  $scopes
     * @param string $scopeSeparator
     *
     * @return string
     */
    protected function formatScopes(array $scopes, $scopeSeparator) {
        return implode($scopeSeparator, $scopes);
    }

    /**
     * @inheritdoc
     */
    public function user() {
        if ($this->user) {
            return $this->user;
        }

        if ($this->hasInvalidState()) {
            throw new CSocialLogin_Exception_InvalidStateException('Invalid State');
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        $this->user = $this->mapUserToObject($this->getUserByToken(
            $token = carr::get($response, 'access_token')
        ));

        return $this->user->setToken($token)
            ->setRefreshToken(carr::get($response, 'refresh_token'))
            ->setExpiresIn(carr::get($response, 'expires_in'));
    }

    /**
     * Get a Social User instance from a known access token.
     *
     * @param string $token
     *
     * @return \CSocialLogin_OAuth2_User
     */
    public function userFromToken($token) {
        $user = $this->mapUserToObject($this->getUserByToken($token));

        return $user->setToken($token);
    }

    /**
     * Determine if the current request / session has a mismatching "state".
     *
     * @return bool
     */
    protected function hasInvalidState() {
        if ($this->isStateless()) {
            return false;
        }

        $state = $this->request->session()->pull('state');

        return empty($state) || $this->request->input('state') !== $state;
    }

    /**
     * Get the access token response for the given code.
     *
     * @param string $code
     *
     * @return array
     */
    public function getAccessTokenResponse($code) {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => $this->getTokenFields($code),
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code) {
        $fields = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
        ];

        if ($this->usesPKCE()) {
            $fields['code_verifier'] = $this->request->session()->pull('code_verifier');
        }

        return $fields;
    }

    /**
     * Get the code from the request.
     *
     * @return string
     */
    protected function getCode() {
        return $this->request->input('code');
    }

    /**
     * Merge the scopes of the requested access.
     *
     * @param array|string $scopes
     *
     * @return $this
     */
    public function scopes($scopes) {
        $this->scopes = array_unique(array_merge($this->scopes, (array) $scopes));

        return $this;
    }

    /**
     * Set the scopes of the requested access.
     *
     * @param array|string $scopes
     *
     * @return $this
     */
    public function setScopes($scopes) {
        $this->scopes = array_unique((array) $scopes);

        return $this;
    }

    /**
     * Get the current scopes.
     *
     * @return array
     */
    public function getScopes() {
        return $this->scopes;
    }

    /**
     * Set the redirect URL.
     *
     * @param string $url
     *
     * @return $this
     */
    public function redirectUrl($url) {
        $this->redirectUrl = $url;

        return $this;
    }

    /**
     * Get a instance of the Guzzle HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient() {
        if (is_null($this->httpClient)) {
            $this->httpClient = new Client($this->guzzle);
        }

        return $this->httpClient;
    }

    /**
     * Set the Guzzle HTTP client instance.
     *
     * @param \GuzzleHttp\Client $client
     *
     * @return $this
     */
    public function setHttpClient(Client $client) {
        $this->httpClient = $client;

        return $this;
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

    /**
     * Determine if the provider is operating with state.
     *
     * @return bool
     */
    protected function usesState() {
        return !$this->stateless;
    }

    /**
     * Determine if the provider is operating as stateless.
     *
     * @return bool
     */
    protected function isStateless() {
        return $this->stateless;
    }

    /**
     * Indicates that the provider should operate as stateless.
     *
     * @return $this
     */
    public function stateless() {
        $this->stateless = true;

        return $this;
    }

    /**
     * Get the string used for session state.
     *
     * @return string
     */
    protected function getState() {
        return cstr::random(40);
    }

    /**
     * Determine if the provider uses PKCE.
     *
     * @return bool
     */
    protected function usesPKCE() {
        return $this->usesPKCE;
    }

    /**
     * Enables PKCE for the provider.
     *
     * @return $this
     */
    public function enablePKCE() {
        $this->usesPKCE = true;

        return $this;
    }

    /**
     * Generates a random string of the right length for the PKCE code verifier.
     *
     * @return string
     */
    protected function getCodeVerifier() {
        return cstr::random(96);
    }

    /**
     * Generates the PKCE code challenge based on the PKCE code verifier in the session.
     *
     * @return string
     */
    protected function getCodeChallenge() {
        $hashed = hash('sha256', $this->request->session()->get('code_verifier'), true);

        return rtrim(strtr(base64_encode($hashed), '+/', '-_'), '=');
    }

    /**
     * Returns the hash method used to calculate the PKCE code challenge.
     *
     * @return string
     */
    protected function getCodeChallengeMethod() {
        return 'S256';
    }

    /**
     * Set the custom parameters of the request.
     *
     * @param array $parameters
     *
     * @return $this
     */
    public function with(array $parameters) {
        $this->parameters = $parameters;

        return $this;
    }
}
