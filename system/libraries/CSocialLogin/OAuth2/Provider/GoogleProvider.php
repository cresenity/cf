<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 15, 2019, 8:00:37 PM
 */
class CSocialLogin_OAuth2_Provider_GoogleProvider extends CSocialLogin_OAuth2_AbstractProvider implements CSocialLogin_OAuth2_ProviderInterface {
    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [
        'openid',
        'profile',
        'email',
    ];

    /**
     * @inheritdoc
     */
    protected function getAuthUrl($state) {
        return $this->buildAuthUrlFromBase('https://accounts.google.com/o/oauth2/auth', $state);
    }

    /**
     * @inheritdoc
     */
    protected function getTokenUrl() {
        return 'https://www.googleapis.com/oauth2/v4/token';
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code) {
        return carr::add(parent::getTokenFields($code), 'grant_type', 'authorization_code');
    }

    /**
     * @inheritdoc
     */
    protected function getUserByToken($token) {
        if ($this->isJwtToken($token)) {
            return $this->getUserFromJwtToken($token);
        }

        $response = $this->getHttpClient()->get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'query' => [
                'prettyPrint' => 'false',
            ],
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * @inheritdoc
     */
    public function refreshToken($refreshToken) {
        $response = $this->getRefreshTokenResponse($refreshToken);

        return new CSocialLogin_OAuth2_Token(
            carr::get($response, 'access_token'),
            carr::get($response, 'refresh_token', $refreshToken),
            carr::get($response, 'expires_in'),
            explode($this->scopeSeparator, carr::get($response, 'scope', ''))
        );
    }

    /**
     * Determine if the given token is a JWT (ID token).
     *
     * @param string $token
     *
     * @return bool
     */
    protected function isJwtToken($token) {
        return substr_count((string) $token, '.') === 2 && strlen((string) $token) > 100;
    }

    /**
     * Get the user claims from a Google ID token, verifying its signature, issuer and audience.
     *
     * @param string $idToken
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function getUserFromJwtToken($idToken) {
        try {
            $user = (array) \Firebase\JWT\JWT::decode($idToken, \Firebase\JWT\JWK::parseKeySet($this->getGoogleJwks()));

            if (!in_array(carr::get($user, 'iss'), ['https://accounts.google.com', 'accounts.google.com'], true)) {
                throw new Exception('Invalid ID token issuer.');
            }

            if (carr::get($user, 'aud') !== $this->clientId) {
                throw new Exception('Invalid ID token audience.');
            }

            return $user;
        } catch (Exception $e) {
            throw new Exception('Failed to verify Google JWT token: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get Google's JSON Web Key Set for ID token verification.
     *
     * @return array
     */
    protected function getGoogleJwks() {
        $response = $this->getHttpClient()->get('https://www.googleapis.com/oauth2/v3/certs');

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * @inheritdoc
     */
    protected function mapUserToObject(array $user) {
        // Deprecated: Fields added to keep backwards compatibility in 4.0. These will be removed in 5.0
        $user['id'] = carr::get($user, 'sub');
        $user['verified_email'] = carr::get($user, 'email_verified');
        $user['link'] = carr::get($user, 'profile');

        return (new CSocialLogin_OAuth2_User())->setRaw($user)->map([
            'id' => carr::get($user, 'sub'),
            'nickname' => carr::get($user, 'nickname'),
            'name' => carr::get($user, 'name'),
            'email' => carr::get($user, 'email'),
            'avatar' => $avatarUrl = carr::get($user, 'picture'),
            'avatar_original' => $avatarUrl,
        ]);
    }
}
