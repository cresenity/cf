<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 16, 2019, 5:11:08 PM
 */
use GuzzleHttp\ClientInterface;

class CSocialLogin_OAuth2_Provider_BitbucketProvider extends CSocialLogin_OAuth2_AbstractProvider implements CSocialLogin_OAuth2_ProviderInterface {
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['email'];

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
        return $this->buildAuthUrlFromBase('https://bitbucket.org/site/oauth2/authorize', $state);
    }

    /**
     * @inheritdoc
     */
    protected function getTokenUrl() {
        return 'https://bitbucket.org/site/oauth2/access_token';
    }

    /**
     * @inheritdoc
     */
    protected function getUserByToken($token) {
        $response = $this->getHttpClient()->get('https://api.bitbucket.org/2.0/user', [
            'query' => ['access_token' => $token],
        ]);

        $user = json_decode($response->getBody(), true);

        if (in_array('email', $this->scopes)) {
            $user['email'] = $this->getEmailByToken($token);
        }

        return $user;
    }

    /**
     * Get the email for the given access token.
     *
     * @param string $token
     *
     * @return null|string
     */
    protected function getEmailByToken($token) {
        $emailsUrl = 'https://api.bitbucket.org/2.0/user/emails?access_token=' . $token;

        try {
            $response = $this->getHttpClient()->get($emailsUrl);
        } catch (Exception $e) {
            return;
        }

        $emails = json_decode($response->getBody(), true);

        foreach ($emails['values'] as $email) {
            if ($email['type'] == 'email' && $email['is_primary'] && $email['is_confirmed']) {
                return $email['email'];
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function mapUserToObject(array $user) {
        return (new CSocialLogin_OAuth2_User())->setRaw($user)->map([
            'id' => $user['uuid'],
            'nickname' => $user['username'],
            'name' => carr::get($user, 'display_name'),
            'email' => carr::get($user, 'email'),
            'avatar' => carr::get($user, 'links.avatar.href'),
        ]);
    }

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return string
     */
    public function getAccessToken($code) {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'auth' => [$this->clientId, $this->clientSecret],
            'headers' => ['Accept' => 'application/json'],
            'form_params' => $this->getTokenFields($code),
        ]);

        return json_decode($response->getBody(), true)['access_token'];
    }
}
