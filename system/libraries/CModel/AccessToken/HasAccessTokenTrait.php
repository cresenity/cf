<?php
/**
 * @property-read CModel_Collection|CModel_AccessToken_AccessTokenModel[] $accessToken
 */
trait CModel_AccessToken_HasAccessTokenTrait {
    /**
     * The access token the user is using for the current request.
     *
     * @var \CModel_AccessToken_Contract_HasAbilitiesInterface
     */
    protected $accessToken;

    /**
     * Get the access tokens that belong to model.
     *
     * @return \CModel_Relation_MorphMany
     */
    public function accessToken() {
        return $this->morphMany(CModel_AccessToken::$accessTokenModel, 'tokenable');
    }

    /**
     * Determine if the current API token has a given scope.
     *
     * @param string $ability
     *
     * @return bool
     */
    public function accessTokenCan($ability) {
        return $this->accessToken && $this->accessToken->can($ability);
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param string $name
     * @param array  $abilities
     *
     * @return \CModel_AccessToken_NewAccessToken
     */
    public function createAccessToken(string $name, array $abilities = ['*']) {
        $token = $this->accessToken()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken = cstr::random(40)),
            'abilities' => $abilities,
        ]);

        return new CModel_AccessToken_NewAccessToken($token, $token->getKey() . '|' . $plainTextToken);
    }

    /**
     * Get the access token currently associated with the user.
     *
     * @return \CModel_AccessToken_Contract_HasAbilitiesInterface
     */
    public function currentAccessToken() {
        return $this->accessToken;
    }

    /**
     * Set the current access token for the user.
     *
     * @param \CModel_AccessToken_Contract_HasAbilitiesInterface $accessToken
     *
     * @return $this
     */
    public function withAccessToken($accessToken) {
        $this->accessToken = $accessToken;

        return $this;
    }
}
