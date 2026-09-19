<?php

/**
 * X (formerly Twitter) OAuth2 with PKCE against api.x.com.
 */
class CSocialLogin_OAuth2_Provider_XProvider extends CSocialLogin_OAuth2_Provider_TwitterProvider {
    /**
     * @inheritdoc
     */
    protected $scopes = ['users.read', 'users.email', 'tweet.read'];

    /**
     * @inheritdoc
     */
    public function getAuthUrl($state) {
        return $this->buildAuthUrlFromBase('https://x.com/i/oauth2/authorize', $state);
    }

    /**
     * @inheritdoc
     */
    protected function getTokenUrl() {
        return 'https://api.x.com/2/oauth2/token';
    }

    /**
     * @inheritdoc
     */
    protected function getUserByToken($token) {
        $response = $this->getHttpClient()->get('https://api.x.com/2/users/me', [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'query' => ['user.fields' => 'profile_image_url,confirmed_email'],
        ]);

        return carr::get(json_decode($response->getBody(), true), 'data');
    }

    /**
     * @inheritdoc
     */
    protected function mapUserToObject(array $user) {
        return (new CSocialLogin_OAuth2_User())->setRaw($user)->map([
            'id' => $user['id'],
            'email' => carr::get($user, 'confirmed_email'),
            'nickname' => $user['username'],
            'name' => $user['name'],
            'avatar' => carr::get($user, 'profile_image_url'),
        ]);
    }
}
