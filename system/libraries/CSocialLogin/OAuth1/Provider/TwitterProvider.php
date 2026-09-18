<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 16, 2019, 4:15:48 PM
 */
class CSocialLogin_OAuth1_Provider_TwitterProvider extends CSocialLogin_OAuth1_AbstractProvider {
    /**
     * @inheritdoc
     */
    public function user() {
        if (!$this->hasNecessaryVerifier()) {
            throw new CSocialLogin_OAuth1_Exception_MissingVerifierException('Invalid request. Missing OAuth verifier.');
        }

        $user = $this->server->getUserDetails($token = $this->getToken(), $this->shouldBypassCache($token->getIdentifier(), $token->getSecret()));
        $extraDetails = [
            'location' => $user->location,
            'description' => $user->description,
        ];
        $instance = (new CSocialLogin_OAuth1_User())->setRaw(array_merge($user->extra, $user->urls, $extraDetails))
            ->setToken($token->getIdentifier(), $token->getSecret());

        return $instance->map([
            'id' => $user->uid,
            'nickname' => $user->nickname,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->imageUrl,
            'avatar_original' => str_replace('_normal', '', $user->imageUrl),
        ]);
    }
}
