<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 16, 2019, 4:55:13 PM
 */
class CSocialLogin_OAuth2_Provider_GitlabProvider extends CSocialLogin_OAuth2_AbstractProvider implements CSocialLogin_OAuth2_ProviderInterface {
    protected $baseUrl = 'https://gitlab.com/';

    /**
     * @return string
     */
    public function getBaseUrl() {
        return rtrim($this->baseUrl, '/') . '/';
    }

    /**
     * @param string $baseUrl
     *
     * @return $this
     */
    public function setBaseUrl($baseUrl) {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function getAuthUrl($state) {
        return $this->buildAuthUrlFromBase($this->getBaseUrl() . 'oauth/authorize', $state);
    }

    /**
     * @inheritdoc
     */
    protected function getTokenUrl() {
        return $this->baseUrl . 'oauth/token';
    }

    /**
     * @inheritdoc
     */
    protected function getUserByToken($token) {
        $userUrl = $this->baseUrl . 'api/v3/user?access_token=' . $token;
        $response = $this->getHttpClient()->get($userUrl);
        $user = json_decode($response->getBody(), true);

        return $user;
    }

    /**
     * @inheritdoc
     */
    protected function mapUserToObject(array $user) {
        return (new CSocialLogin_OAuth2_User())->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => $user['username'],
            'name' => $user['name'],
            'email' => $user['email'],
            'avatar' => $user['avatar_url'],
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function getTokenFields($code) {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }
}
