<?php

class CApi_OAuth_TokenRepository {
    /**
     * CApi OAuth.
     *
     * @var CApi_OAuth
     */
    protected $oauth;

    /**
     * Create a new refresh token repository.
     *
     * @return void
     */
    public function __construct(CApi_OAuth $oauth) {
        $this->oauth = $oauth;
    }

    /**
     * Creates a new Access Token.
     *
     * @param array $attributes
     *
     * @return \CApi_OAuth_Model_OAuthAccessToken
     */
    public function create($attributes) {
        return $this->oauth->token()->create($attributes);
    }

    /**
     * Get a model by the given ID.
     *
     * @param int $id
     *
     * @return null|\CApi_OAuth_Model_OAuthAccessToken
     */
    public function find($id) {
        return $this->oauth->token()->where('oauth_access_token_id', $id)->first();
    }

    /**
     * Get a model by the given token.
     *
     * @param string $token
     *
     * @return null|\CApi_OAuth_Model_OAuthAccessToken
     */
    public function findToken($token) {
        return $this->oauth->token()->where('token', $token)->first();
    }

    /**
     * Get a token by the given user ID and token ID.
     *
     * @param string $id
     * @param int    $userId
     *
     * @return null|\CApi_OAuth_Model_OAuthAccessToken
     */
    public function findForUser($id, $userId) {
        return $this->oauth->token()->where('oauth_access_token_id', $id)->where('user_id', $userId)->first();
    }

    /**
     * Get the token instances for the given user ID.
     *
     * @param mixed $userId
     *
     * @return \CModel_Collection
     */
    public function forUser($userId) {
        return $this->oauth->token()->where('user_id', $userId)->get();
    }

    /**
     * Get a valid token instance for the given user and client.
     *
     * @param \CModel                       $user
     * @param \CApi_OAuth_Model_OAuthClient $client
     *
     * @return null|\CApi_OAuth_Model_OAuthAccessToken
     */
    public function getValidToken($user, $client) {
        return $client->tokens()
            ->whereUserId($user->getAuthIdentifier())
            ->where('revoked', 0)
            ->where('expires_at', '>', CCarbon::now())
            ->first();
    }

    /**
     * Store the given token instance.
     *
     * @param \CApi_OAuth_Model_OAuthAccessToken $token
     *
     * @return void
     */
    public function save(CApi_OAuth_Model_OAuthAccessToken $token) {
        $token->save();
    }

    /**
     * Revoke an access token.
     *
     * @param string $tokenStr
     *
     * @return mixed
     */
    public function revokeAccessToken($tokenStr) {
        return $this->oauth->token()->where('token', $tokenStr)->update(['revoked' => true]);
    }

    /**
     * Check if the access token has been revoked.
     *
     * @param string $tokenStr
     *
     * @return bool
     */
    public function isAccessTokenRevoked($tokenStr) {
        if ($token = $this->findToken($tokenStr)) {
            return $token->revoked;
        }

        return true;
    }

    /**
     * Find a valid token for the given user and client.
     *
     * @param \CModel                       $user
     * @param \CApi_OAuth_Model_OAuthClient $client
     *
     * @return null|\CApi_OAuth_Model_OAuthAccessToken
     */
    public function findValidToken($user, $client) {
        $userType = $this->oauth->getUserModelFromProvider();

        $query = $client->oauthAccessToken()
            ->where('revoked', 0)
            ->where('expires_at', '>', CCarbon::now())
            ->latest('expires_at');
        if ($user) {
            $query->where('user_id', '=', c::optional($user)->getAuthIdentifier());
            if ($userType) {
                $query->where('user_type', '=', $userType);
            }
        }

        return $query->first();
    }

    /**
     * @return CApi_OAuth
     */
    public function oauth() {
        return $this->oauth;
    }
}
