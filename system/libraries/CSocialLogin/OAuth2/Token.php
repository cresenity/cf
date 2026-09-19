<?php

/**
 * Access token returned by CSocialLogin_OAuth2_AbstractProvider::refreshToken().
 */
class CSocialLogin_OAuth2_Token {
    /**
     * The user's access token.
     *
     * @var string
     */
    public $token;

    /**
     * The refresh token that can be exchanged for a new access token (null when the provider did not issue one).
     *
     * @var null|string
     */
    public $refreshToken;

    /**
     * The number of seconds the access token is valid for.
     *
     * @var null|int
     */
    public $expiresIn;

    /**
     * The scopes the user authorized; may be a subset of the requested scopes.
     *
     * @var array
     */
    public $approvedScopes;

    /**
     * @param string      $token
     * @param null|string $refreshToken
     * @param null|int    $expiresIn
     * @param array       $approvedScopes
     */
    public function __construct($token, $refreshToken = null, $expiresIn = null, array $approvedScopes = []) {
        $this->token = $token;
        $this->refreshToken = $refreshToken;
        $this->expiresIn = $expiresIn === null ? null : (int) $expiresIn;
        $this->approvedScopes = $approvedScopes;
    }
}
