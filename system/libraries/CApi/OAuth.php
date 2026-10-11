<?php

use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @see https://github.com/rinvex/laravel-oauth/
 */
class CApi_OAuth {
    /**
     * Indicates if the implicit grant type is enabled.
     *
     * @var null|bool
     */
    public $implicitGrantEnabled = false;

    /**
     * The default scope.
     *
     * @var string
     */
    public $defaultScope;

    /**
     * All of the scopes defined for the application.
     *
     * @var array
     */
    public $scopes = [

    ];

    /**
     * The interval when access tokens expire.
     *
     * @var null|\DateInterval
     */
    public $tokensExpireIn;

    /**
     * The date when refresh tokens expire.
     *
     * @var null|\DateInterval
     */
    public $refreshTokensExpireIn;

    /**
     * The date when personal access tokens expire.
     *
     * @var null|\DateInterval
     */
    public $personalAccessTokensExpireIn;

    /**
     * The date when personal access tokens expire.
     *
     * @var null|\DateInterval
     */
    public $socialAccessTokensExpireIn;

    /**
     * The name for API token cookies.
     *
     * @var string
     */
    public $cookie = 'cf_oauth_token';

    /**
     * Indicates if OAuth should ignore incoming CSRF tokens.
     *
     * @var bool
     */
    public $ignoreCsrfToken = false;

    /**
     * The storage location of the encryption keys.
     *
     * @var string
     */
    public $keyPath;

    /**
     * The auth code model class name.
     *
     * @var string
     */
    public $authCodeModel = CApi_OAuth_Model_OAuthAuthCode::class;

    /**
     * The client model class name.
     *
     * @var string
     */
    public $clientModel = CApi_OAuth_Model_OAuthClient::class;

    /**
     * Indicates if client's are identified by UUIDs.
     *
     * @var bool
     */
    public $clientUuids = false;

    /**
     * The personal access client model class name.
     *
     * @var string
     */
    public $personalAccessClientModel = CApi_OAuth_Model_OAuthPersonalAccessClient::class;

    /**
     * The token model class name.
     *
     * @var string
     */
    public $tokenModel = CApi_OAuth_Model_OAuthAccessToken::class;

    /**
     * The refresh token model class name.
     *
     * @var string
     */
    public $refreshTokenModel = CApi_OAuth_Model_OAuthRefreshToken::class;

    /**
     * Indicates if Passport should unserializes cookies.
     *
     * @var bool
     */
    public $unserializesCookies = false;

    /**
     * Indicates if client secrets will be hashed.
     *
     * @var bool
     */
    public $hashesClientSecrets = false;

    /**
     * The callback that should be used to generate JWT encryption keys.
     *
     * @var callable
     */
    public $tokenEncryptionKeyCallback;

    /**
     * Indicates the scope should inherit its parent scope.
     *
     * @var bool
     */
    public $withInheritedScopes = false;

    /**
     * The authorization server response type.
     *
     * @var null|\League\OAuth2\Server\ResponseTypes\ResponseTypeInterface
     */
    public $authorizationServerResponseType;

    /**
     * @var string
     */
    private $apiGroup;

    /**
     * @var CApi_OAuth_Loader
     */
    private $loader;

    /**
     * @var CApi_OAuth_RouteManager
     */
    private $routeManager;

    /**
     * @var CApi_OAuth_ViewManager
     */
    private $viewManager;

    public function __construct($apiGroup) {
        $this->apiGroup = $apiGroup;
        $this->routeManager = new CApi_OAuth_RouteManager();
        $this->viewManager = new CApi_OAuth_ViewManager();
        $this->clientModel = CF::config('api.groups.' . $apiGroup . '.model.client', CApi_OAuth_Model_OAuthClient::class);
    }

    /**
     * @return CApi_OAuth_RouteManager
     */
    public function routeManager() {
        return $this->routeManager;
    }

    /**
     * @return CApi_OAuth_ViewManager
     */
    public function viewManager() {
        return $this->viewManager;
    }

    /**
     * @return CApi_OAuth_Loader
     */
    public function loader() {
        if ($this->loader == null) {
            $this->loader = new CApi_OAuth_Loader($this);
        }

        return $this->loader;
    }

    /**
     * @return \League\OAuth2\Server\AuthorizationServer
     */
    public function authorizationServer() {
        return $this->loader()->getAuthorizationServer();
    }

    /**
     * @return \CApi_OAuth_TokenRepository
     */
    public function tokenRepository() {
        return $this->loader()->getTokenRepository();
    }

    /**
     * @return \CApi_OAuth_Bridge_UserRepository
     */
    public function bridgeUserRepository() {
        return $this->loader()->getBridgeUserRepository();
    }

    /**
     * @return \CApi_OAuth_ClientRepository
     */
    public function clientRepository() {
        return $this->loader()->getClientRepository();
    }

    /**
     * @return \League\OAuth2\Server\ResourceServer
     */
    public function resourceServer() {
        return $this->loader()->getResourceServer();
    }

    /**
     * Enable the implicit grant type.
     *
     * @return static
     */
    public function enableImplicitGrant() {
        $this->implicitGrantEnabled = true;

        return $this;
    }

    /**
     * Set the default scope(s). Multiple scopes may be an array or specified delimited by spaces.
     *
     * @param array|string $scope
     *
     * @return void
     */
    public function setDefaultScope($scope) {
        $this->defaultScope = is_array($scope) ? implode(' ', $scope) : $scope;

        return $this;
    }

    /**
     * Get all of the defined scope IDs.
     *
     * @return array
     */
    public function scopeIds() {
        return $this->scopes()->pluck('id')->values()->all();
    }

    /**
     * Determine if the given scope has been defined.
     *
     * @param string $id
     *
     * @return bool
     */
    public function hasScope($id) {
        return $id === '*' || array_key_exists($id, $this->scopes);
    }

    /**
     * Get all of the scopes defined for the application.
     *
     * @return \CCollection
     */
    public function scopes() {
        return c::collect($this->scopes)->map(function ($description, $id) {
            return new CApi_OAuth_Scope($id, $description);
        })->values();
    }

    /**
     * Get all of the scopes matching the given IDs.
     *
     * @param array $ids
     *
     * @return array
     */
    public function scopesFor(array $ids) {
        return c::collect($ids)->map(function ($id) {
            if (isset($this->scopes[$id])) {
                return new CApi_OAuth_Scope($id, $this->scopes[$id]);
            }
        })->filter()->values()->all();
    }

    /**
     * Define the scopes for the application.
     *
     * @param array $scopes
     *
     * @return void
     */
    public function tokensCan(array $scopes) {
        $this->scopes = $scopes;
    }

    /**
     * Get or set when access tokens expire.
     *
     * @param null|\DateTimeInterface $date
     *
     * @return \DateInterval|static
     */
    public function tokensExpireIn(?DateTimeInterface $date = null) {
        if (is_null($date)) {
            return $this->tokensExpireIn ?: new DateInterval('P1Y');
        }

        $this->tokensExpireIn = CCarbon::now()->diff($date);

        return $this;
    }

    /**
     * Get or set when refresh tokens expire.
     *
     * @param null|\DateTimeInterface $date
     *
     * @return \DateInterval|static
     */
    public function refreshTokensExpireIn(?DateTimeInterface $date = null) {
        if (is_null($date)) {
            return $this->refreshTokensExpireIn ?: new DateInterval('P1Y');
        }

        $this->refreshTokensExpireIn = CCarbon::now()->diff($date);

        return $this;
    }

    /**
     * Get or set when personal access tokens expire.
     *
     * @param null|\DateTimeInterface $date
     *
     * @return \DateInterval|static
     */
    public function personalAccessTokensExpireIn(?DateTimeInterface $date = null) {
        if (is_null($date)) {
            return $this->personalAccessTokensExpireIn ?: new DateInterval('P1Y');
        }

        $this->personalAccessTokensExpireIn = CCarbon::now()->diff($date);

        return $this;
    }

    /**
     * Get or set when personal access tokens expire.
     *
     * @param null|\DateTimeInterface $date
     *
     * @return \DateInterval|static
     */
    public function socialAccessTokensExpireIn(?DateTimeInterface $date = null) {
        if (is_null($date)) {
            return $this->socialAccessTokensExpireIn ?: $this->tokensExpireIn();
        }

        $this->socialAccessTokensExpireIn = CCarbon::now()->diff($date);

        return $this;
    }

    /**
     * Get or set the name for API token cookies.
     *
     * @param null|string $cookie
     *
     * @return string|static
     */
    public function cookie($cookie = null) {
        if (is_null($cookie)) {
            return $this->cookie;
        }

        $this->cookie = $cookie;

        return $this;
    }

    /**
     * Indicate that Passport should ignore incoming CSRF tokens.
     *
     * @param bool $ignoreCsrfToken
     *
     * @return static
     */
    public function ignoreCsrfToken($ignoreCsrfToken = true) {
        $this->ignoreCsrfToken = $ignoreCsrfToken;

        return $this;
    }

    /**
     * Set the current user for the application with the given scopes.
     *
     * @param \CAuth_AuthenticatableInterface|\CApi_OAuth_Trait_HasApiTokenTrait $user
     * @param array                                                              $scopes
     * @param string                                                             $guard
     *
     * @return \CAuth_AuthenticatableInterface
     */
    public static function actingAs($user, $scopes = [], $guard = 'api') {
        $token = Mockery::mock(self::tokenModel())->shouldIgnoreMissing(false);

        foreach ($scopes as $scope) {
            $token->shouldReceive('can')->with($scope)->andReturn(true);
        }

        $user->withAccessToken($token);

        if (isset($user->wasRecentlyCreated) && $user->wasRecentlyCreated) {
            $user->wasRecentlyCreated = false;
        }

        c::auth()->guard($guard)->setUser($user);

        c::auth()->shouldUse($guard);

        return $user;
    }

    /**
     * Set the current client for the application with the given scopes.
     *
     * @param \CApi_OAuth_Model_OAuthClient $client
     * @param array                         $scopes
     *
     * @return \CApi_OAuth_Model_OAuthClient
     */
    public static function actingAsClient($client, $scopes = []) {
        $token = c::container(self::tokenModel());

        $token->client_id = $client->id;
        $token->setRelation('client', $client);

        $token->scopes = $scopes;

        $mock = Mockery::mock(ResourceServer::class);
        $mock->shouldReceive('validateAuthenticatedRequest')
            ->andReturnUsing(function (ServerRequestInterface $request) use ($token) {
                return $request->withAttribute('oauth_client_id', $token->client->id)
                    ->withAttribute('oauth_access_token_id', $token->id)
                    ->withAttribute('oauth_scopes', $token->scopes);
            });

        c::container()->instance(ResourceServer::class, $mock);

        $mock = Mockery::mock(CApi_OAuth_TokenRepository::class);
        $mock->shouldReceive('find')->andReturn($token);

        c::container()->instance(CApi_OAuth_TokenRepository::class, $mock);

        return $client;
    }

    /**
     * Set the storage location of the encryption keys.
     *
     * @param string $path
     *
     * @return void
     */
    public function loadKeysFrom($path) {
        $this->keyPath = $path;
    }

    /**
     * The location of the encryption keys.
     *
     * @param string $file
     *
     * @return string
     */
    public function keyPath($file) {
        $file = ltrim($file, '/\\');

        return $this->keyPath
            ? rtrim($this->keyPath, '/\\') . DIRECTORY_SEPARATOR . $file
            : DOCROOT . $file;
    }

    /**
     * Set the auth code model class name.
     *
     * @param string $authCodeModel
     *
     * @return void
     */
    public function useAuthCodeModel($authCodeModel) {
        $this->authCodeModel = $authCodeModel;
    }

    /**
     * Get the auth code model class name.
     *
     * @return string
     */
    public function authCodeModel() {
        return $this->authCodeModel;
    }

    /**
     * Get a new auth code model instance.
     *
     * @return \CApi_OAuth_Model_OAuthAuthCode
     */
    public function authCode() {
        $authCodeModel = $this->authCodeModel();

        return new $authCodeModel();
    }

    /**
     * Set the client model class name.
     *
     * @param string $clientModel
     *
     * @return $this
     */
    public function useClientModel($clientModel) {
        $this->clientModel = $clientModel;

        return $this;
    }

    /**
     * Get the client model class name.
     *
     * @return string
     */
    public function clientModel() {
        return $this->clientModel;
    }

    /**
     * Get a new client model instance.
     *
     * @return \CApi_OAuth_Model_OAuthClient
     */
    public function client() {
        $clientModel = $this->clientModel();

        return new $clientModel();
    }

    /**
     * Determine if clients are identified using UUIDs.
     *
     * @return bool
     */
    public function clientUuids() {
        return $this->clientUuids;
    }

    /**
     * Specify if clients are identified using UUIDs.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setClientUuids($value) {
        $this->clientUuids = $value;

        return $this;
    }

    /**
     * Set the personal access client model class name.
     *
     * @param string $personalAccessClientModel
     *
     * @return $this
     */
    public function usePersonalAccessClientModel($personalAccessClientModel) {
        $this->personalAccessClientModel = $personalAccessClientModel;

        return $this;
    }

    /**
     * Get the personal access client model class name.
     *
     * @return string
     */
    public function personalAccessClientModel() {
        return $this->personalAccessClientModel;
    }

    /**
     * Get a new personal access client model instance.
     *
     * @return \CApi_OAuth_Model_OAuthPersonalAccessClient
     */
    public function personalAccessClient() {
        $personalAccessClientModel = $this->personalAccessClientModel();

        return new $personalAccessClientModel();
    }

    /**
     * Set the token model class name.
     *
     * @param string $tokenModel
     *
     * @return $this
     */
    public function useTokenModel($tokenModel) {
        $this->tokenModel = $tokenModel;

        return $this;
    }

    /**
     * Get the token model class name.
     *
     * @return string
     */
    public function tokenModel() {
        return $this->tokenModel;
    }

    /**
     * Get a new personal access client model instance.
     *
     * @return \CApi_OAuth_Model_OAuthAccessToken
     */
    public function token() {
        $tokenModel = $this->tokenModel();

        return new $tokenModel();
    }

    /**
     * Set the refresh token model class name.
     *
     * @param string $refreshTokenModel
     *
     * @return $this
     */
    public function useRefreshTokenModel($refreshTokenModel) {
        $this->refreshTokenModel = $refreshTokenModel;

        return $this;
    }

    /**
     * Get the refresh token model class name.
     *
     * @return string
     */
    public function refreshTokenModel() {
        return $this->refreshTokenModel;
    }

    /**
     * Get a new refresh token model instance.
     *
     * @return \CApi_OAuth_Model_OAuthRefreshToken
     */
    public function refreshToken() {
        $refreshTokenModel = $this->refreshTokenModel;

        return new $refreshTokenModel();
    }

    /**
     * Configure OAuth to hash client credential secrets.
     *
     * @return static
     */
    public function hashClientSecrets() {
        $this->hashesClientSecrets = true;

        return $this;
    }

    /**
     * Specify the callback that should be invoked to generate encryption keys for encrypting JWT tokens.
     *
     * @param callable $callback
     *
     * @return static
     */
    public function encryptTokensUsing($callback) {
        $this->tokenEncryptionKeyCallback = $callback;

        return $this;
    }

    /**
     * Generate an encryption key for encrypting JWT tokens.
     *
     * @param \CCrypt_Encrypter $encrypter
     *
     * @return string
     */
    public static function tokenEncryptionKey(CCrypt_Encrypter $encrypter) {
        $tokenEncryptionKeyCallback = static::$tokenEncryptionKeyCallback;

        return is_callable($tokenEncryptionKeyCallback)
            ? $tokenEncryptionKeyCallback($encrypter)
            : $encrypter->getKey();
    }

    /**
     * Instruct OAuth to enable cookie serialization.
     *
     * @return static
     */
    public function withCookieSerialization() {
        $this->unserializesCookies = true;

        return $this;
    }

    /**
     * Instruct OAuth to disable cookie serialization.
     *
     * @return static
     */
    public function withoutCookieSerialization() {
        $this->unserializesCookies = false;

        return $this;
    }

    public function getGroup() {
        return $this->apiGroup;
    }

    public function createSessionGuard() {
        $guard = $this->getGuardName();
        $guardConfig = CF::config('auth.guards.' . $guard);
        $auth = CAuth::manager()->createSessionDriver($guard, $guardConfig);

        return $auth;
    }

    public function getGuardName() {
        $guard = CF::config('api.groups.' . $this->apiGroup . '.auth.guard', 'api');

        return $guard;
    }

    public function getGuard() {
        return c::auth($this->getGuardName());
    }

    public function getUserModelFromProvider() {
        $guard = CF::config('api.groups.' . $this->apiGroup . '.auth.guard', 'api');

        $provider = CF::config('auth.guards.' . $guard . '.provider');

        return CF::config('auth.providers.' . $provider . '.model');
    }
}
