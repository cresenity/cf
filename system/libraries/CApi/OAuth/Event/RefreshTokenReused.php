<?php

/**
 * Dispatched when a refresh token that has already been revoked (normally
 * because it was already consumed by an earlier rotation, or explicitly
 * revoked) is presented again to the refresh_token grant. Per the OAuth 2.0
 * Security BCP this is the standard "refresh token reuse" signal - it can
 * mean a stolen token is being replayed by an attacker, or just a legitimate
 * client retrying a token that already rotated elsewhere. This event only
 * reports the observation; it does not itself take any action.
 */
class CApi_OAuth_Event_RefreshTokenReused {
    /**
     * The refresh token ID that was presented (already revoked).
     *
     * @var string
     */
    public $refreshTokenId;

    /**
     * The revoked refresh token's model, as found in storage.
     *
     * @var CApi_OAuth_Model_OAuthRefreshToken
     */
    public $refreshTokenModel;

    /**
     * Create a new event instance.
     *
     * @param string                              $refreshTokenId
     * @param CApi_OAuth_Model_OAuthRefreshToken $refreshTokenModel
     *
     * @return void
     */
    public function __construct($refreshTokenId, $refreshTokenModel) {
        $this->refreshTokenId = $refreshTokenId;
        $this->refreshTokenModel = $refreshTokenModel;
    }
}
