<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AuthSessionGuardTest.php';

/**
 * CAuth_Middleware_AuthenticateSession: hash password disimpan di sesi pada request pertama, sesi dikeluarkan
 * begitu hash user berubah, tamu dan sesi yang belum dimulai dilewati begitu saja.
 */
class AuthenticateSessionMiddlewareTest extends TestCase {
    /** @var AuthSessionGuardTestProvider */
    private $provider;

    /** @var CSession_Store */
    private $session;

    /** @var CAuth_Guard_SessionGuard */
    private $guard;

    /** @var CAuth_GenericUser */
    private $user;

    protected function setUp(): void {
        $this->user = new CAuth_GenericUser(['id' => 1, 'email' => 'hery@example.com', 'password' => 'hash-lama', 'remember_token' => null]);
        $this->provider = new AuthSessionGuardTestProvider([$this->user]);
        $this->session = new CSession_Store('cf-test', new CSession_Handler_ArraySessionHandler(120));
        $this->session->start();
        $this->guard = new CAuth_Guard_SessionGuard('default', $this->provider, $this->session, CHTTP_Request::create('/dashboard', 'GET'));
        $this->guard->setDispatcher(new CEvent_Dispatcher());
        $this->guard->setCookieJar(new CHTTP_Cookie());
    }

    private function middleware() {
        return new CAuth_Middleware_AuthenticateSession($this->guard, $this->session, 'default');
    }

    private function handle($request = null) {
        $request = $request ?: CHTTP_Request::create('/dashboard', 'GET');
        $called = false;
        $response = $this->middleware()->handle($request, function () use (&$called) {
            $called = true;

            return 'lanjut';
        });

        return [$called, $response];
    }

    public function testGuestPassesThroughUntouched() {
        list($called, $response) = $this->handle();
        $this->assertTrue($called);
        $this->assertSame('lanjut', $response);
        $this->assertFalse($this->session->has('password_hash_default'));
    }

    public function testFirstRequestStoresTheHashInsteadOfLoggingOut() {
        $this->guard->login($this->user);
        list($called, $response) = $this->handle();
        $this->assertTrue($called, 'sesi lama tanpa hash tidak dikeluarkan');
        $this->assertSame('hash-lama', $this->session->get('password_hash_default'));
        $this->assertTrue($this->guard->check());
    }

    public function testChangedPasswordLogsTheSessionOutAndRedirects() {
        $this->guard->login($this->user);
        $this->handle();
        $this->user->password = 'hash-baru'; // ganti password dari perangkat lain

        list($called, $response) = $this->handle(CHTTP_Request::create('https://app.test/dashboard?tab=1', 'GET'));
        $this->assertFalse($called, 'request tidak diteruskan');
        $this->assertInstanceOf(CHTTP_RedirectResponse::class, $response);
        $this->assertSame('https://app.test/dashboard?tab=1', $response->getTargetUrl(), 'kembali ke URL yang sama → gerbang login app');
        $this->assertFalse($this->guard->check());
        $this->assertFalse($this->session->has('password_hash_default'), 'sesi di-flush');
    }

    public function testChangedPasswordOnJsonRequestGives401() {
        $this->guard->login($this->user);
        $this->handle();
        $this->user->password = 'hash-baru';
        $request = CHTTP_Request::create('/api/me', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        list($called, $response) = $this->handle($request);
        $this->assertFalse($called);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testUnchangedPasswordKeepsTheSessionAlive() {
        $this->guard->login($this->user);
        $this->handle();
        list($called) = $this->handle();
        $this->assertTrue($called);
        $this->assertTrue($this->guard->check());
    }

    public function testEveryHandlerValidatesAndTouchesIds() {
        $handler = new CSession_Handler_ArraySessionHandler(10);
        $this->assertInstanceOf(SessionUpdateTimestampHandlerInterface::class, $handler);
        $this->assertFalse($handler->validateId('tidak-ada'));
        $handler->write('sid-1', 'data');
        $this->assertTrue($handler->validateId('sid-1'));
        CCarbon::setTestNow(CCarbon::now()->addMinutes(9));
        $this->assertTrue($handler->updateTimestamp('sid-1', 'data'));
        CCarbon::setTestNow(CCarbon::now()->addMinutes(9));
        $this->assertTrue($handler->validateId('sid-1'), 'updateTimestamp memperpanjang umur sesi');
        CCarbon::setTestNow();

        foreach (['CSession_Handler_CacheBasedSessionHandler', 'CSession_Handler_CookieSessionHandler', 'CSession_Handler_DatabaseSessionHandler', 'CSession_Handler_FileSessionHandler', 'CSession_Handler_NullSessionHandler', 'CSession_Handler_RedisSessionHandler'] as $class) {
            $this->assertTrue((new ReflectionClass($class))->implementsInterface(SessionUpdateTimestampHandlerInterface::class), $class);
        }
        $null = new CSession_Handler_NullSessionHandler();
        $this->assertFalse($null->validateId('x'));
        $this->assertTrue($null->updateTimestamp('x', ''));
    }
}
