<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 16, 2019, 4:52:04 PM
 */
use GuzzleHttp\ClientInterface;

class CSocialLogin_OAuth2_Provider_FacebookProvider extends CSocialLogin_OAuth2_AbstractProvider implements CSocialLogin_OAuth2_ProviderInterface {
    /**
     * The base Facebook Graph URL.
     *
     * @var string
     */
    protected $graphUrl = 'https://graph.facebook.com';

    /**
     * The Graph API version for the request.
     *
     * @var string
     */
    protected $version = 'v23.0';

    /**
     * The user fields being requested.
     *
     * @var array
     */
    protected $fields = ['name', 'email', 'gender', 'verified', 'link'];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['email'];

    /**
     * Display the dialog in a popup view.
     *
     * @var bool
     */
    protected $popup = false;

    /**
     * Re-request a declined permission.
     *
     * @var bool
     */
    protected $reRequest = false;

    /**
     * The access token that was last used to retrieve a user.
     *
     * @var null|string
     */
    protected $lastToken;

    /**
     * The nonce expected when using Facebook Limited Login OIDC tokens.
     *
     * @var null|string
     */
    protected $expectedNonce;

    /**
     * @inheritdoc
     */
    protected function getAuthUrl($state) {
        return $this->buildAuthUrlFromBase('https://www.facebook.com/' . $this->version . '/dialog/oauth', $state);
    }

    /**
     * @inheritdoc
     */
    protected function getTokenUrl() {
        return $this->graphUrl . '/' . $this->version . '/oauth/access_token';
    }

    /**
     * @inheritdoc
     */
    public function getAccessTokenResponse($code) {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'form_params' => $this->getTokenFields($code),
        ]);

        $data = json_decode($response->getBody(), true);

        return carr::add($data, 'expires_in', carr::pull($data, 'expires'));
    }

    /**
     * @inheritdoc
     */
    protected function getUserByToken($token) {
        $this->lastToken = $token;

        $user = $this->getUserByOIDCToken($token);

        return $user !== null ? $user : $this->getUserFromAccessToken($token);
    }

    /**
     * Get a user instance from a known access token or Limited Login OIDC token.
     *
     * @param string      $token
     * @param null|string $nonce
     *
     * @return \CSocialLogin_OAuth2_User
     */
    public function userFromToken($token, $nonce = null) {
        if ($nonce !== null) {
            $this->withNonce($nonce);
        }

        return parent::userFromToken($token);
    }

    /**
     * Get the user claims from a Limited Login OIDC token; null when the token is not a JWT.
     *
     * @param string $token
     *
     * @throws \Exception
     *
     * @return null|array
     */
    protected function getUserByOIDCToken($token) {
        $header = json_decode(base64_decode(strtr(explode('.', (string) $token)[0], '-_', '+/')), true);
        $kid = is_array($header) ? carr::get($header, 'kid') : null;

        if ($kid === null) {
            return null;
        }

        $data = (array) \Firebase\JWT\JWT::decode($token, $this->getPublicKeysOfOIDCToken());

        if (carr::get($data, 'aud') !== $this->clientId) {
            throw new Exception('Token has incorrect audience.');
        }
        if (carr::get($data, 'iss') !== 'https://www.facebook.com') {
            throw new Exception('Token has incorrect issuer.');
        }

        $expectedNonce = $this->getExpectedNonce();

        if ($expectedNonce === null || !isset($data['nonce']) || !hash_equals($expectedNonce, (string) $data['nonce'])) {
            throw new Exception('Token has incorrect nonce.');
        }

        $data['id'] = $data['sub'];

        if (isset($data['given_name'])) {
            $data['first_name'] = $data['given_name'];
        }

        if (isset($data['family_name'])) {
            $data['last_name'] = $data['family_name'];
        }

        return $data;
    }

    /**
     * Get the JWKS used to verify Limited Login OIDC tokens, keyed by kid.
     *
     * @return array
     */
    protected function getPublicKeysOfOIDCToken() {
        $response = $this->getHttpClient()->get('https://limited.facebook.com/.well-known/oauth/openid/jwks/');

        return \Firebase\JWT\JWK::parseKeySet(json_decode((string) $response->getBody(), true), 'RS256');
    }

    /**
     * Get the raw user from the Graph API for the given access token.
     *
     * @param string $token
     *
     * @return array
     */
    protected function getUserFromAccessToken($token) {
        $params = [
            'access_token' => $token,
            'fields' => implode(',', $this->fields),
        ];

        if (!empty($this->clientSecret)) {
            $params['appsecret_proof'] = hash_hmac('sha256', $token, $this->clientSecret);
        }

        $response = $this->getHttpClient()->get($this->graphUrl . '/' . $this->version . '/me', [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => $params,
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * @inheritdoc
     */
    protected function mapUserToObject(array $user) {
        $avatarUrl = $this->graphUrl . '/' . $this->version . '/' . $user['id'] . '/picture';

        return (new CSocialLogin_OAuth2_User())->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => null,
            'name' => isset($user['name']) ? $user['name'] : null,
            'email' => isset($user['email']) ? $user['email'] : null,
            'avatar' => $avatarUrl . '?type=normal',
            'avatar_original' => $avatarUrl . '?width=1920',
            'profileUrl' => isset($user['link']) ? $user['link'] : null,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function getCodeFields($state = null) {
        $fields = parent::getCodeFields($state);

        if ($this->popup) {
            $fields['display'] = 'popup';
        }

        if ($this->reRequest) {
            $fields['auth_type'] = 'rerequest';
        }

        return $fields;
    }

    /**
     * Set the user fields to request from Facebook.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function fields(array $fields) {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Set the dialog to be displayed as a popup.
     *
     * @return $this
     */
    public function asPopup() {
        $this->popup = true;

        return $this;
    }

    /**
     * Re-request permissions which were previously declined.
     *
     * @return $this
     */
    public function reRequest() {
        $this->reRequest = true;

        return $this;
    }

    /**
     * Get the last access token used.
     *
     * @return null|string
     */
    public function lastToken() {
        return $this->lastToken;
    }

    /**
     * Specify the nonce expected when using Facebook Limited Login OIDC tokens.
     *
     * @param string $nonce
     *
     * @return $this
     */
    public function withNonce($nonce) {
        $this->expectedNonce = $nonce;

        return $this;
    }

    /**
     * Get the expected OIDC token nonce.
     *
     * @return null|string
     */
    protected function getExpectedNonce() {
        return $this->expectedNonce !== null ? $this->expectedNonce : carr::get($this->parameters, 'nonce');
    }

    /**
     * Specify which graph version should be used.
     *
     * @param string $version
     *
     * @return $this
     */
    public function graphVersion($version) {
        return $this->usingGraphVersion($version);
    }

    /**
     * Specify which graph version should be used.
     *
     * @param string $version
     *
     * @return $this
     */
    public function usingGraphVersion($version) {
        $this->version = $version;

        return $this;
    }
}
