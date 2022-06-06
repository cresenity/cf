<?php
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Configuration;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ImplicitGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;

class CApi_OAuth_Loader {
    /**
     * AuthorizationServer.
     *
     * @var \League\OAuth2\Server\AuthorizationServer
     */
    private $authorizationServer;

    /**
     * @var CApi_OAuth
     */
    private $oauth;

    private $bridgeRefreshTokenRepository;

    private $bridgeAccessTokenRepository;

    private $bridgeUserRepository;

    private $clientRepository;

    private $tokenRepository;

    private $resourceServer;

    /**
     * @var \CCrypt_EncrypterInterface
     */
    private $encrypter;

    /**
     * @var \Lcobucci\JWT\Parser
     */
    private $jwtParser;

    public function __construct($oauth) {
        $this->oauth = $oauth;
        $this->apiGroup = $oauth->getGroup();
        $this->registerAuthorizationServer();
        $this->registerClientRepository();
        $this->registerJWTParser();
        $this->registerResourceServer();
        $this->registerGuard();
    }

    /**
     * Make the authorization service instance.
     *
     * @return \League\OAuth2\Server\AuthorizationServer
     */
    public function makeAuthorizationServer() {
        return new AuthorizationServer(
            new CApi_OAuth_Bridge_ClientRepository($this->getClientRepository()),
            new CApi_OAuth_Bridge_AccessTokenRepository($this->getTokenRepository()),
            new CApi_OAuth_Bridge_ScopeRepository(),
            $this->makeCryptKey('private'),
            CCrypt::encrypter()->getKey(),
            $this->oauth->authorizationServerResponseType
        );
    }

    /**
     * Register the authorization server.
     *
     * @return void
     */
    protected function registerAuthorizationServer() {
        $this->authorizationServer = c::tap($this->makeAuthorizationServer(), function ($server) {
            $server->setDefaultScope($this->oauth->defaultScope);

            $server->enableGrantType(
                $this->makeAuthCodeGrant(),
                $this->oauth->tokensExpireIn()
            );

            $server->enableGrantType(
                $this->makeRefreshTokenGrant(),
                $this->oauth->tokensExpireIn()
            );

            $server->enableGrantType(
                $this->makePasswordGrant(),
                $this->oauth->tokensExpireIn()
            );

            $server->enableGrantType(
                new CApi_OAuth_Bridge_PersonalAccessGrant(),
                $this->oauth->personalAccessTokensExpireIn()
            );

            $server->enableGrantType(
                new ClientCredentialsGrant(),
                $this->oauth->tokensExpireIn()
            );

            if ($this->oauth->implicitGrantEnabled) {
                $server->enableGrantType(
                    $this->makeImplicitGrant(),
                    $this->oauth->tokensExpireIn()
                );
            }
        });
    }

    /**
     * @return \League\OAuth2\Server\AuthorizationServer
     */
    public function getAuthorizationServer() {
        return $this->authorizationServer;
    }

    /**
     * Create and configure an instance of the Auth Code grant.
     *
     * @return \League\OAuth2\Server\Grant\AuthCodeGrant
     */
    protected function makeAuthCodeGrant() {
        return c::tap($this->buildAuthCodeGrant(), function ($grant) {
            $grant->setRefreshTokenTTL($this->oauth->refreshTokensExpireIn());
        });
    }

    /**
     * Build the Auth Code grant instance.
     *
     * @return \League\OAuth2\Server\Grant\AuthCodeGrant
     */
    protected function buildAuthCodeGrant() {
        return new AuthCodeGrant(
            new CApi_OAuth_Bridge_AuthCodeRepository(),
            $this->getBridgeRefreshTokenRepository(),
            new DateInterval('PT10M')
        );
    }

    /**
     * Create and configure a Refresh Token grant instance.
     *
     * @return \League\OAuth2\Server\Grant\RefreshTokenGrant
     */
    protected function makeRefreshTokenGrant() {
        $repository = $this->getBridgeRefreshTokenRepository();

        return c::tap(new RefreshTokenGrant($repository), function ($grant) {
            $grant->setRefreshTokenTTL($this->oauth->refreshTokensExpireIn());
        });
    }

    /**
     * Create and configure a Password grant instance.
     *
     * @return \League\OAuth2\Server\Grant\PasswordGrant
     */
    protected function makePasswordGrant() {
        $grant = new PasswordGrant(
            $this->getBridgeUserRepository(),
            $this->getBridgeRefreshTokenRepository(),
        );

        $grant->setRefreshTokenTTL($this->oauth->refreshTokensExpireIn());

        return $grant;
    }

    /**
     * Create and configure an instance of the Implicit grant.
     *
     * @return \League\OAuth2\Server\Grant\ImplicitGrant
     */
    protected function makeImplicitGrant() {
        return new ImplicitGrant($this->oauth->tokensExpireIn());
    }

    /**
     * Register the client repository.
     *
     * @return void
     */
    protected function registerClientRepository() {
        $this->getClientRepository();
    }

    /**
     * Register the JWT Parser.
     *
     * @return void
     */
    protected function registerJWTParser() {
        $this->getJwtParser();
    }

    /**
     * Register the resource server.
     *
     * @return void
     */
    protected function registerResourceServer() {
        $this->getResourceServer();
    }

    /**
     * Create a CryptKey instance without permissions check.
     *
     * @param string $type
     *
     * @return \League\OAuth2\Server\CryptKey
     */
    protected function makeCryptKey($type) {
        $key = str_replace('\\n', "\n", CF::config('api.groups.' . $this->apiGroup . '.oauth.' . $type . '_key') ?: '');

        if (!$key) {
            $key = 'file://' . $this->oauth->keyPath('oauth-' . $type . '.key');
        }

        return new CryptKey($key, null, false);
    }

    /**
     * Register the token guard.
     *
     * @return void
     */
    protected function registerGuard() {
        CAuth::manager()->extend('oauth', function ($app, $name, array $config) {
            return c::tap($this->makeGuard($config), function ($guard) {
                $guard->setRequest(c::request());
            });
        });
    }

    /**
     * Make an instance of the token guard.
     *
     * @param array $config
     *
     * @return \CAuth_Guard_RequestGuard
     */
    protected function makeGuard(array $config) {
        return new CAuth_Guard_RequestGuard(function ($request) use ($config) {
            return (new CApi_OAuth_Guard_TokenGuard(
                $this->getResourceServer(),
                new CApi_OAuth_UserProvider(c::auth()->createUserProvider($config['provider']), $config['provider']),
                $this->getTokenRepository(),
                $this->getClientRepository(),
                $this->getEncrypter()
            ))->user($request);
        }, $this->app['request']);
    }

    /**
     * Register the cookie deletion event handler.
     *
     * @return void
     */
    protected function deleteCookieOnLogout() {
        CEvent::dispatcher()->listen(CAuth_Event_Logout::class, function () {
            if (c::request()->hasCookie($this->oauth->cookie())) {
                CHTTP::cookie()->queue(CHTTP::cookie()->forget($this->oauth->cookie()));
            }
        });
    }

    protected function getBridgeRefreshTokenRepository() {
        if ($this->bridgeRefreshTokenRepository == null) {
            $this->bridgeRefreshTokenRepository = new CApi_OAuth_Bridge_RefreshTokenRepository(new CApi_OAuth_RefreshTokenRepository());
        }

        return $this->bridgeRefreshTokenRepository;
    }

    protected function getBridgeAccessTokenRepository() {
        if ($this->bridgeAccessTokenRepository == null) {
            $this->bridgeAccessTokenRepository = new CApi_OAuth_Bridge_AccessTokenRepository($this->getTokenRepository());
        }

        return $this->bridgeAccessTokenRepository;
    }

    protected function getBridgeUserRepository() {
        if ($this->bridgeUserRepository == null) {
            $this->bridgeUserRepository = new CApi_OAuth_Bridge_UserRepository($this->apiGroup);
        }

        return $this->bridgeUserRepository;
    }

    public function getResourceServer() {
        if ($this->resourceServer == null) {
            $this->resourceServer = new ResourceServer($this->getBridgeAccessTokenRepository(), $this->makeCryptKey('public'));
        }

        return $this->resourceServer;
    }

    public function getClientRepository() {
        if ($this->clientRepository == null) {
            $personalAccessClientId = CF::config('api.groups.' . $this->apiGroup . '.oauth.personal_access_client.id');
            $personalAccessClientSecret = CF::config('api.groups.' . $this->apiGroup . '.oauth.personal_access_client.secret');
            $this->clientRepository = new CApi_OAuth_ClientRepository($this->oauth, $personalAccessClientId, $personalAccessClientSecret);
        }

        return $this->clientRepository;
    }

    public function getTokenRepository() {
        if ($this->tokenRepository == null) {
            $this->tokenRepository = new CApi_OAuth_TokenRepository();
        }

        return $this->tokenRepository;
    }

    public function getEncrypter() {
        if ($this->encrypter == null) {
            $this->encrypter = CCrypt::encrypter();
        }

        return $this->encrypter;
    }

    public function getJwtParser() {
        if ($this->jwtParser == null) {
            $this->jwtParser = Configuration::forUnsecuredSigner()->parser();
        }

        return $this->jwtParser;
    }
}
