<?php

/**
 * LinkedIn "Sign In with LinkedIn using OpenID Connect" (driver `linkedin-openid`).
 */
class CSocialLogin_OAuth2_Provider_LinkedInOpenIdProvider extends CSocialLogin_OAuth2_AbstractProvider implements CSocialLogin_OAuth2_ProviderInterface {
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['openid', 'profile', 'email'];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * @inheritdoc
     */
    protected function getAuthUrl($state) {
        return $this->buildAuthUrlFromBase('https://www.linkedin.com/oauth/v2/authorization', $state);
    }

    /**
     * @inheritdoc
     */
    protected function getTokenUrl() {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    /**
     * @inheritdoc
     */
    protected function getUserByToken($token) {
        return $this->getBasicProfile($token);
    }

    /**
     * Get the OIDC userinfo claims for the given access token.
     *
     * @param string $token
     *
     * @return array
     */
    protected function getBasicProfile($token) {
        $response = $this->getHttpClient()->get('https://api.linkedin.com/v2/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
            'query' => [
                'projection' => '(sub,email,email_verified,name,given_name,family_name,picture)',
            ],
        ]);

        return (array) json_decode($response->getBody(), true);
    }

    /**
     * @inheritdoc
     */
    protected function mapUserToObject(array $user) {
        return (new CSocialLogin_OAuth2_User())->setRaw($user)->map([
            'id' => $user['sub'],
            'nickname' => null,
            'name' => carr::get($user, 'name'),
            'first_name' => carr::get($user, 'given_name'),
            'last_name' => carr::get($user, 'family_name'),
            'email' => carr::get($user, 'email'),
            'email_verified' => carr::get($user, 'email_verified'),
            'avatar' => carr::get($user, 'picture'),
            'avatar_original' => carr::get($user, 'picture'),
        ]);
    }
}
