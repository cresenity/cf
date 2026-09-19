<?php
class CSocialLogin_OAuth2_Provider_TwitterProvider extends CSocialLogin_OAuth2_AbstractProvider implements CSocialLogin_OAuth2_ProviderInterface {
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['users.read', 'tweet.read'];

    /**
     * Indicates if PKCE should be used.
     *
     * @var bool
     */
    protected $usesPKCE = true;

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * @inheritdoc
     */
    public function getAuthUrl($state) {
        return $this->buildAuthUrlFromBase('https://twitter.com/i/oauth2/authorize', $state);
    }

    /**
     * @inheritdoc
     */
    protected function getTokenUrl() {
        return 'https://api.twitter.com/2/oauth2/token';
    }

    /**
     * @inheritdoc
     */
    protected function getUserByToken($token) {
        $response = $this->getHttpClient()->get('https://api.twitter.com/2/users/me', [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'query' => ['user.fields' => 'profile_image_url'],
        ]);

        return carr::get(json_decode($response->getBody(), true), 'data');
    }

    /**
     * @inheritdoc
     */
    protected function mapUserToObject(array $user) {
        return (new CSocialLogin_OAuth2_User())->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => $user['username'],
            'name' => $user['name'],
            'avatar' => $user['profile_image_url'],
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function getRefreshTokenResponse($refreshToken) {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'auth' => [$this->clientId, $this->clientSecret],
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * @inheritdoc
     */
    protected function getCodeFields($state = null) {
        $fields = parent::getCodeFields($state);

        if ($this->isStateless()) {
            $fields['state'] = 'state';
        }

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function getAccessTokenResponse($code) {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'auth' => [$this->clientId, $this->clientSecret],
            'form_params' => $this->getTokenFields($code),
        ]);

        return json_decode($response->getBody(), true);
    }
}
