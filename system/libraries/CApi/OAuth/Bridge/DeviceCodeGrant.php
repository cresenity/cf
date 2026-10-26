<?php

use Psr\Http\Message\ServerRequestInterface;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;

/**
 * OAuth 2.0 Device Authorization Grant (RFC 8628) -- "buka URL ini, masukkan kode
 * ini" di perangkat tanpa browser lokal/di belakang NAT. League tidak punya grant
 * ini bawaan sama sekali (tidak ada di `system/vendor/League/OAuth2/Server/Grant/`),
 * jadi ini custom penuh, pola konstruksinya niru CApi_OAuth_Bridge_PersonalAccessGrant
 * (extends AbstractGrant langsung, bukan AbstractAuthorizeGrant -- grant ini tidak
 * punya leg redirect `/oauth/authorize`, polling-nya lewat `/oauth/token` biasa).
 *
 * Opt-in lewat CApi_OAuth::$deviceCodeGrantEnabled -- lihat Loader::registerAuthorizationServer().
 */
class CApi_OAuth_Bridge_DeviceCodeGrant extends AbstractGrant {
    /**
     * @var CApi_OAuth_DeviceCodeRepository
     */
    protected $deviceCodeRepository;

    public function __construct(
        CApi_OAuth_DeviceCodeRepository $deviceCodeRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository
    ) {
        $this->deviceCodeRepository = $deviceCodeRepository;
        $this->setRefreshTokenRepository($refreshTokenRepository);
    }

    /**
     * @inheritdoc
     */
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ) {
        $client = $this->validateClient($request);
        $deviceCodeParam = $this->getRequestParameter('device_code', $request);

        if ($deviceCodeParam === null) {
            throw OAuthServerException::invalidRequest('device_code');
        }

        $deviceCode = $this->deviceCodeRepository->findByDeviceCode($deviceCodeParam);

        if ($deviceCode === null || (int) $deviceCode->oauth_client_id !== (int) $client->getIdentifier()) {
            throw $this->expiredTokenException();
        }

        if ($deviceCode->expires_at !== null && CCarbon::now()->greaterThan($deviceCode->expires_at)) {
            $this->deviceCodeRepository->consume($deviceCode);

            throw $this->expiredTokenException();
        }

        if ($deviceCode->device_code_status === CApi_OAuth_Model_OAuthDeviceCode::STATUS_DENIED) {
            throw OAuthServerException::accessDenied();
        }

        if ($deviceCode->device_code_status === CApi_OAuth_Model_OAuthDeviceCode::STATUS_PENDING) {
            if ($this->deviceCodeRepository->isPolledTooSoon($deviceCode)) {
                throw $this->slowDownException();
            }

            $this->deviceCodeRepository->touchPolled($deviceCode);

            throw $this->authorizationPendingException();
        }

        // APPROVED: terbitkan token lalu konsumsi baris ini (single use).
        $scopes = $this->validateScopes($deviceCode->scopes ?: []);
        $scopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client, $deviceCode->user_id);

        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $deviceCode->user_id, $scopes);
        $responseType->setAccessToken($accessToken);

        $refreshToken = $this->issueRefreshToken($accessToken);
        if ($refreshToken !== null) {
            $responseType->setRefreshToken($refreshToken);
        }

        $this->deviceCodeRepository->consume($deviceCode);

        return $responseType;
    }

    /**
     * @inheritdoc
     */
    public function getIdentifier() {
        return 'urn:ietf:params:oauth:grant-type:device_code';
    }

    /**
     * @return OAuthServerException
     */
    protected function authorizationPendingException() {
        return new OAuthServerException(
            'The authorization request is still pending.',
            20,
            'authorization_pending',
            400
        );
    }

    /**
     * @return OAuthServerException
     */
    protected function slowDownException() {
        return new OAuthServerException(
            'Polling terlalu cepat, perbesar interval.',
            21,
            'slow_down',
            400
        );
    }

    /**
     * @return OAuthServerException
     */
    protected function expiredTokenException() {
        return new OAuthServerException(
            'Device code tidak dikenal atau sudah kedaluwarsa.',
            22,
            'expired_token',
            400
        );
    }
}
