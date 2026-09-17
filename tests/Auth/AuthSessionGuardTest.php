<?php

use PHPUnit\Framework\TestCase;

/**
 * CAuth_Guard_SessionGuard - padanan suite hulu untuk SessionGuard, dengan penyedia pengguna
 * tiruan in-memory dan sesi array supaya tidak menyentuh basis data maupun cookie asli.
 *
 * `login($user, true)` (remember me) tidak diuji: implementasi CF memanggil `setcookie()`
 * langsung, yang di CLI PHPUnit memicu peringatan header sudah terkirim - lihat docs/NOTES.md.
 */
class AuthSessionGuardTest extends TestCase {
    /**
     * @var AuthSessionGuardTestProvider
     */
    private $provider;

    /**
     * @var CSession_Store
     */
    private $session;

    /**
     * @var CEvent_Dispatcher
     */
    private $events;

    /**
     * @var array
     */
    private $fired = [];

    protected function setUp(): void {
        $this->provider = new AuthSessionGuardTestProvider([
            new CAuth_GenericUser(['id' => 1, 'email' => 'hery@example.com', 'password' => 'rahasia', 'remember_token' => null]),
            new CAuth_GenericUser(['id' => 2, 'email' => 'budi@example.com', 'password' => 'lain', 'remember_token' => 'token-budi']),
        ]);
        $this->session = new CSession_Store('cf-test', new CSession_Handler_ArraySessionHandler(120));
        $this->session->start();
        $this->events = new CEvent_Dispatcher();
        $this->fired = [];
        foreach (['Attempting', 'Validated', 'Login', 'Authenticated', 'Failed', 'Logout', 'CurrentDeviceLogout'] as $event) {
            $this->events->listen('CAuth_Event_' . $event, function ($e) use ($event) {
                $this->fired[] = $event;
            });
        }
    }

    /**
     * @param null|CHTTP_Request $request
     *
     * @return CAuth_Guard_SessionGuard
     */
    private function guard($request = null) {
        $guard = new CAuth_Guard_SessionGuard('default', $this->provider, $this->session, $request);
        $guard->setDispatcher($this->events);
        $guard->setCookieJar(new CHTTP_Cookie());

        return $guard;
    }

    public function testUserIsNullWhenNothingIsInTheSession() {
        $guard = $this->guard();
        $this->assertNull($guard->user());
        $this->assertNull($guard->id());
        $this->assertFalse($guard->check());
        $this->assertTrue($guard->guest());
        $this->assertFalse($guard->hasUser());
    }

    public function testUserIsLoadedFromTheSessionIdentifier() {
        $this->session->put('login_default_' . sha1(CAuth_Guard_SessionGuard::class), 2);
        $guard = $this->guard();

        $user = $guard->user();
        $this->assertNotNull($user);
        $this->assertSame(2, $user->getAuthIdentifier());
        $this->assertSame(2, $guard->id());
        $this->assertTrue($guard->check());
        $this->assertSame(['Authenticated'], $this->fired);
    }

    public function testUserMethodReturnsCachedUser() {
        $this->session->put('login_default_' . sha1(CAuth_Guard_SessionGuard::class), 1);
        $guard = $this->guard();

        $guard->user();
        $guard->user();
        $this->assertSame(1, $this->provider->retrieveByIdCalls, 'penyedia hanya ditanya sekali per request');
    }

    public function testGetNameUsesTheGuardName() {
        $this->assertSame('login_default_' . sha1(CAuth_Guard_SessionGuard::class), $this->guard()->getName());
        $this->assertSame('remember_default_' . sha1(CAuth_Guard_SessionGuard::class), $this->guard()->getRecallerName());
    }

    public function testAttemptCallsRetrieveByCredentialsAndLogsIn() {
        $guard = $this->guard();

        $this->assertTrue($guard->attempt(['email' => 'hery@example.com', 'password' => 'rahasia']));
        $this->assertSame(['email' => 'hery@example.com', 'password' => 'rahasia'], $this->provider->lastCredentials);
        $this->assertSame(1, $guard->user()->getAuthIdentifier());
        $this->assertSame(1, $this->session->get($guard->getName()));
        $this->assertSame(['Attempting', 'Validated', 'Login', 'Authenticated'], $this->fired);
    }

    public function testAttemptReturnsFalseIfUserNotFound() {
        $guard = $this->guard();

        $this->assertFalse($guard->attempt(['email' => 'tidak@ada.com', 'password' => 'x']));
        $this->assertNull($guard->user());
        $this->assertNull($guard->getLastAttempted());
        $this->assertSame(['Attempting', 'Failed'], $this->fired);
    }

    public function testAttemptReturnsFalseOnWrongPasswordAndKeepsLastAttempted() {
        $guard = $this->guard();

        $this->assertFalse($guard->attempt(['email' => 'hery@example.com', 'password' => 'salah']));
        $this->assertNull($guard->user());
        $this->assertSame(1, $guard->getLastAttempted()->getAuthIdentifier());
        $this->assertSame(['Attempting', 'Failed'], $this->fired);
    }

    /**
     * attemptWhen() memanggil $this->shouldLogin() yang tidak ada di kelas manapun, jadi
     * begitu kredensialnya valid ia meledak lewat __call Macroable. Test ini mengunci keadaan
     * rusak itu supaya perbaikannya kelihatan - lihat docs/NOTES.md.
     */
    public function testAttemptWhenIsBrokenBecauseShouldLoginIsMissing() {
        $guard = $this->guard();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('shouldLogin');
        $guard->attemptWhen(['email' => 'hery@example.com', 'password' => 'rahasia'], function ($user) {
            return true;
        });
    }

    public function testValidateChecksCredentialsWithoutLoggingIn() {
        $guard = $this->guard();

        $this->assertTrue($guard->validate(['email' => 'hery@example.com', 'password' => 'rahasia']));
        $this->assertFalse($guard->validate(['email' => 'hery@example.com', 'password' => 'salah']));
        $this->assertNull($guard->user());
        $this->assertNull($this->session->get($guard->getName()));
    }

    public function testLoginStoresIdentifierInSessionAndFiresEvents() {
        $guard = $this->guard();
        $user = $this->provider->retrieveById(2);

        $guard->login($user);

        $this->assertSame(2, $this->session->get($guard->getName()));
        $this->assertSame($user, $guard->user());
        $this->assertSame(['Login', 'Authenticated'], $this->fired);
    }

    public function testLoginUsingId() {
        $guard = $this->guard();

        $user = $guard->loginUsingId(2);
        $this->assertSame(2, $user->getAuthIdentifier());
        $this->assertSame(2, $this->session->get($guard->getName()));

        $this->assertFalse($this->guard()->loginUsingId(99));
    }

    public function testOnceSetsTheUserWithoutTouchingTheSession() {
        $guard = $this->guard();

        $this->assertTrue($guard->once(['email' => 'hery@example.com', 'password' => 'rahasia']));
        $this->assertSame(1, $guard->user()->getAuthIdentifier());
        $this->assertNull($this->session->get($guard->getName()));

        $this->assertFalse($this->guard()->once(['email' => 'hery@example.com', 'password' => 'salah']));
    }

    public function testOnceUsingId() {
        $guard = $this->guard();

        $user = $guard->onceUsingId(1);
        $this->assertSame(1, $user->getAuthIdentifier());
        $this->assertSame($user, $guard->user());
        $this->assertNull($this->session->get($guard->getName()));

        $this->assertFalse($this->guard()->onceUsingId(99));
    }

    public function testSetUserFiresAuthenticatedEvent() {
        $guard = $this->guard();
        $user = $this->provider->retrieveById(1);

        $this->assertSame($guard, $guard->setUser($user));
        $this->assertSame($user, $guard->user());
        $this->assertTrue($guard->hasUser());
        $this->assertSame(['Authenticated'], $this->fired);
    }

    public function testAuthenticateReturnsUserOrThrows() {
        $guard = $this->guard();
        $guard->setUser($this->provider->retrieveById(1));
        $this->assertSame(1, $guard->authenticate()->getAuthIdentifier());

        $this->expectException(CAuth_Exception_AuthenticationException::class);
        $this->guard()->authenticate();
    }

    public function testLogoutRemovesTheSessionKeyAndFiresLogoutEvent() {
        $guard = $this->guard();
        $guard->login($this->provider->retrieveById(2));
        $this->fired = [];

        $guard->logout();

        $this->assertNull($this->session->get($guard->getName()));
        $this->assertNull($guard->user());
        $this->assertFalse($guard->check());
        $this->assertSame(['Logout'], $this->fired);
    }

    public function testLogoutCyclesTheRememberTokenWhenOneWasSet() {
        $guard = $this->guard();
        $user = $this->provider->retrieveById(2);
        $guard->login($user);

        $guard->logout();

        $this->assertNotSame('token-budi', $user->getRememberToken());
        $this->assertSame(60, strlen($user->getRememberToken()));
        $this->assertSame([2], array_keys($this->provider->updatedTokens));
    }

    public function testLogoutDoesNotSetRememberTokenIfNotPreviouslySet() {
        $guard = $this->guard();
        $guard->login($this->provider->retrieveById(1));

        $guard->logout();

        $this->assertNull($this->provider->retrieveById(1)->getRememberToken());
        $this->assertSame([], $this->provider->updatedTokens);
    }

    public function testLogoutCurrentDeviceKeepsTheRememberToken() {
        $guard = $this->guard();
        $user = $this->provider->retrieveById(2);
        $guard->login($user);
        $this->fired = [];

        $guard->logoutCurrentDevice();

        $this->assertNull($this->session->get($guard->getName()));
        $this->assertNull($guard->user());
        $this->assertSame('token-budi', $user->getRememberToken());
        $this->assertSame(['CurrentDeviceLogout'], $this->fired);
    }

    public function testUserUsesRememberCookieIfItExists() {
        $request = CHTTP_Request::create('/', 'GET');
        $guard = $this->guard($request);
        $request->cookies->set($guard->getRecallerName(), '2|token-budi|lain');

        $user = $guard->user();
        $this->assertNotNull($user);
        $this->assertSame(2, $user->getAuthIdentifier());
        $this->assertTrue($guard->viaRemember());
        $this->assertSame(2, $this->session->get($guard->getName()), 'sesi diisi ulang dari cookie');
        $this->assertSame(['Login'], $this->fired);
    }

    public function testUserReturnsNullWhenRememberCookieTokenDoesNotMatch() {
        $request = CHTTP_Request::create('/', 'GET');
        $guard = $this->guard($request);
        $request->cookies->set($guard->getRecallerName(), '2|token-salah|lain');

        $this->assertNull($guard->user());
        $this->assertFalse($guard->viaRemember());
    }

    public function testUserReturnsNullWhenRememberCookieIsMalformed() {
        $request = CHTTP_Request::create('/', 'GET');
        $guard = $this->guard($request);
        $request->cookies->set($guard->getRecallerName(), 'bukan-format-recaller');

        $this->assertNull($guard->user());
    }

    public function testLogoutForgetsTheRememberCookie() {
        $request = CHTTP_Request::create('/', 'GET');
        $guard = $this->guard($request);
        $request->cookies->set($guard->getRecallerName(), '2|token-budi|lain');
        $guard->user();

        $guard->logout();

        $this->assertTrue($guard->getCookieJar()->hasQueued($guard->getRecallerName()));
        $this->assertLessThan(time(), $guard->getCookieJar()->queued($guard->getRecallerName())->getExpiresTime());
    }

    public function testBasicAuthenticationFromRequestHeaders() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'hery@example.com', 'PHP_AUTH_PW' => 'rahasia']);
        $guard = $this->guard($request);

        $this->assertNull($guard->basic('email'));
        $this->assertSame(1, $guard->user()->getAuthIdentifier());
    }

    public function testBasicThrowsUnauthorizedOnFailure() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'hery@example.com', 'PHP_AUTH_PW' => 'salah']);
        $guard = $this->guard($request);

        $this->expectException(Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException::class);
        $guard->basic('email');
    }

    public function testOnceBasicDoesNotTouchTheSession() {
        $request = CHTTP_Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'hery@example.com', 'PHP_AUTH_PW' => 'rahasia']);
        $guard = $this->guard($request);

        $this->assertNull($guard->onceBasic('email'));
        $this->assertSame(1, $guard->user()->getAuthIdentifier());
        $this->assertNull($this->session->get($guard->getName()));
    }

    public function testGuardIsMacroable() {
        CAuth_Guard_SessionGuard::macro('foo', function () {
            return 'bar';
        });
        $this->assertSame('bar', $this->guard()->foo());
    }
}

class AuthSessionGuardTestProvider implements CAuth_UserProviderInterface {
    /**
     * @var CAuth_GenericUser[]
     */
    private $users;

    /**
     * @var int
     */
    public $retrieveByIdCalls = 0;

    /**
     * @var null|array
     */
    public $lastCredentials;

    /**
     * @var array
     */
    public $updatedTokens = [];

    public function __construct(array $users) {
        foreach ($users as $user) {
            $this->users[$user->getAuthIdentifier()] = $user;
        }
    }

    public function retrieveById($identifier) {
        $this->retrieveByIdCalls++;

        return isset($this->users[$identifier]) ? $this->users[$identifier] : null;
    }

    public function retrieveByObject($object) {
        return null;
    }

    public function retrieveByToken($identifier, $token) {
        $user = isset($this->users[$identifier]) ? $this->users[$identifier] : null;

        return $user && $user->getRememberToken() === $token ? $user : null;
    }

    public function updateRememberToken($user, $token) {
        $user->setRememberToken($token);
        $this->updatedTokens[$user->getAuthIdentifier()] = $token;
    }

    public function retrieveByCredentials(array $credentials) {
        $this->lastCredentials = $credentials;
        foreach ($this->users as $user) {
            if ($user->email === carr::get($credentials, 'email')) {
                return $user;
            }
        }

        return null;
    }

    public function validateCredentials(CAuth_AuthenticatableInterface $user, array $credentials) {
        return $user->getAuthPassword() === carr::get($credentials, 'password');
    }

    public function hasher() {
        return c::hash();
    }
}
