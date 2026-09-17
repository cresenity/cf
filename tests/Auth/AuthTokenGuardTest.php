<?php

use PHPUnit\Framework\TestCase;

/**
 * CAuth_Guard_TokenGuard - padanan suite hulu untuk TokenGuard: token dicari di query string,
 * badan request, header Bearer, lalu kata sandi HTTP basic.
 */
class AuthTokenGuardTest extends TestCase {
    /**
     * @var AuthTokenGuardTestProvider
     */
    private $provider;

    protected function setUp(): void {
        $this->provider = new AuthTokenGuardTestProvider([
            new CAuth_GenericUser(['id' => 1, 'api_token' => 'foo']),
            new CAuth_GenericUser(['id' => 2, 'api_token' => hash('sha256', 'rahasia')]),
        ]);
    }

    public function testUserCanBeRetrievedByQueryStringVariable() {
        $guard = new CAuth_Guard_TokenGuard($this->provider, CHTTP_Request::create('/', 'GET', ['api_token' => 'foo']));

        $user = $guard->user();
        $this->assertSame(1, $user->getAuthIdentifier());
        $this->assertSame(['api_token' => 'foo'], $this->provider->lastCredentials);
        $this->assertTrue($guard->check());
        $this->assertFalse($guard->guest());
        $this->assertSame(1, $guard->id());
    }

    public function testTokenCanBeHashed() {
        $guard = new CAuth_Guard_TokenGuard($this->provider, CHTTP_Request::create('/', 'GET', ['api_token' => 'rahasia']), 'api_token', 'api_token', true);

        $this->assertSame(2, $guard->user()->getAuthIdentifier());
        $this->assertSame(['api_token' => hash('sha256', 'rahasia')], $this->provider->lastCredentials);
    }

    public function testUserCanBeRetrievedByAuthHeaders() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'siapa-saja', 'PHP_AUTH_PW' => 'foo']);
        $guard = new CAuth_Guard_TokenGuard($this->provider, $request);

        $this->assertSame(1, $guard->user()->getAuthIdentifier());
    }

    public function testUserCanBeRetrievedByBearerToken() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer foo']);
        $guard = new CAuth_Guard_TokenGuard($this->provider, $request);

        $this->assertSame(1, $guard->user()->getAuthIdentifier());
    }

    public function testUserCanBeRetrievedFromTheRequestBody() {
        $request = CHTTP_Request::create('/', 'POST', ['api_token' => 'foo']);
        $guard = new CAuth_Guard_TokenGuard($this->provider, $request);

        $this->assertSame(1, $guard->user()->getAuthIdentifier());
    }

    public function testInputAndStorageKeysCanBeCustomised() {
        $request = CHTTP_Request::create('/', 'GET', ['custom_token_field' => 'foo']);
        $guard = new CAuth_Guard_TokenGuard($this->provider, $request, 'custom_token_field', 'custom_token_field');

        $this->assertSame('foo', $guard->getTokenForRequest());
        $guard->user();
        $this->assertSame(['custom_token_field' => 'foo'], $this->provider->lastCredentials);
    }

    public function testUserIsNullWhenThereIsNoToken() {
        $guard = new CAuth_Guard_TokenGuard($this->provider, CHTTP_Request::create('/', 'GET'));

        $this->assertNull($guard->getTokenForRequest());
        $this->assertNull($guard->user());
        $this->assertNull($this->provider->lastCredentials, 'penyedia tidak ditanya tanpa token');
        $this->assertTrue($guard->guest());
    }

    public function testUserIsNullWhenTokenIsUnknown() {
        $guard = new CAuth_Guard_TokenGuard($this->provider, CHTTP_Request::create('/', 'GET', ['api_token' => 'tidak-ada']));

        $this->assertNull($guard->user());
        $this->assertFalse($guard->check());
    }

    public function testUserIsCachedForTheRequest() {
        $guard = new CAuth_Guard_TokenGuard($this->provider, CHTTP_Request::create('/', 'GET', ['api_token' => 'foo']));

        $guard->user();
        $guard->user();
        $this->assertSame(1, $this->provider->retrieveByCredentialsCalls);
    }

    public function testValidate() {
        $guard = new CAuth_Guard_TokenGuard($this->provider, CHTTP_Request::create('/', 'GET'));

        $this->assertTrue($guard->validate(['api_token' => 'foo']));
        $this->assertFalse($guard->validate(['api_token' => 'tidak-ada']));
        $this->assertFalse($guard->validate([]));
        $this->assertNull($guard->user(), 'validate() tidak menyetel pengguna');
    }

    public function testSetRequestChangesTheSource() {
        $guard = new CAuth_Guard_TokenGuard($this->provider, CHTTP_Request::create('/', 'GET'));
        $this->assertNull($guard->getTokenForRequest());

        $this->assertSame($guard, $guard->setRequest(CHTTP_Request::create('/', 'GET', ['api_token' => 'foo'])));
        $this->assertSame('foo', $guard->getTokenForRequest());
    }
}

class AuthTokenGuardTestProvider implements CAuth_UserProviderInterface {
    /**
     * @var CAuth_GenericUser[]
     */
    private $users;

    /**
     * @var null|array
     */
    public $lastCredentials;

    /**
     * @var int
     */
    public $retrieveByCredentialsCalls = 0;

    public function __construct(array $users) {
        $this->users = $users;
    }

    public function retrieveById($identifier) {
        foreach ($this->users as $user) {
            if ($user->getAuthIdentifier() === $identifier) {
                return $user;
            }
        }

        return null;
    }

    public function retrieveByObject($object) {
        return null;
    }

    public function retrieveByToken($identifier, $token) {
        return null;
    }

    public function updateRememberToken($user, $token) {
    }

    public function retrieveByCredentials(array $credentials) {
        $this->retrieveByCredentialsCalls++;
        $this->lastCredentials = $credentials;
        foreach ($this->users as $user) {
            foreach ($credentials as $key => $value) {
                if (isset($user->{$key}) && $user->{$key} === $value) {
                    return $user;
                }
            }
        }

        return null;
    }

    public function validateCredentials(CAuth_AuthenticatableInterface $user, array $credentials) {
        return true;
    }

    public function hasher() {
        return c::hash();
    }
}
