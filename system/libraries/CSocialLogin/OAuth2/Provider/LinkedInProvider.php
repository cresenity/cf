<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * LinkedIn v2 profile API (r_liteprofile/r_emailaddress); only apps that already hold those permissions can use it.
 *
 * @since May 16, 2019, 5:08:39 PM
 * @deprecated 1.9 use CSocialLogin_OAuth2_Provider_LinkedInOpenIdProvider (driver `linkedin-openid`)
 */
class CSocialLogin_OAuth2_Provider_LinkedInProvider extends CSocialLogin_OAuth2_AbstractProvider implements CSocialLogin_OAuth2_ProviderInterface {
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['r_liteprofile', 'r_emailaddress'];

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
     * Get the POST fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code) {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * @inheritdoc
     */
    protected function getUserByToken($token) {
        $basicProfile = $this->getBasicProfile($token);
        $emailAddress = $this->getEmailAddress($token);

        return array_merge($basicProfile, $emailAddress);
    }

    /**
     * Get the basic profile fields for the user.
     *
     * @param string $token
     *
     * @return array
     */
    protected function getBasicProfile($token) {
        $url = 'https://api.linkedin.com/v2/me?projection=(id,firstName,lastName,profilePicture(displayImage~:playableStreams))';
        $response = $this->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
        ]);

        return (array) json_decode($response->getBody(), true);
    }

    /**
     * Get the email address for the user.
     *
     * @param string $token
     *
     * @return array
     */
    protected function getEmailAddress($token) {
        $url = 'https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))';
        $response = $this->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
        ]);

        return (array) carr::get((array) json_decode($response->getBody(), true), 'elements.0.handle~');
    }

    /**
     * @inheritdoc
     */
    protected function mapUserToObject(array $user) {
        $preferredLocale = carr::get($user, 'firstName.preferredLocale.language') . '_' . carr::get($user, 'firstName.preferredLocale.country');
        $firstName = carr::get($user, 'firstName.localized.' . $preferredLocale);
        $lastName = carr::get($user, 'lastName.localized.' . $preferredLocale);
        $images = (array) carr::get($user, 'profilePicture.displayImage~.elements', []);
        $avatar = carr::first(carr::where($images, function ($image) {
            return $image['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['width'] === 100;
        }));
        $originalAvatar = carr::first(carr::where($images, function ($image) {
            return $image['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['width'] === 800;
        }));

        return (new CSocialLogin_OAuth2_User())->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => null,
            'name' => $firstName . ' ' . $lastName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => carr::get($user, 'emailAddress'),
            'avatar' => carr::get($avatar, 'identifiers.0.identifier'),
            'avatar_original' => carr::get($originalAvatar, 'identifiers.0.identifier'),
        ]);
    }
}
