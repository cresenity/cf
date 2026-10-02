<?php

use PHPUnit\Framework\TestCase;

/**
 * CHTTP_Middleware_VerifyCsrfToken: tes karakterisasi perilaku saat ini. Middleware ini tidak terdaftar default
 * di bootstrapper, jadi CSRF hanya ditegakkan app yang memasangnya.
 */
class HttpVerifyCsrfTokenEnforcingForTest extends CHTTP_Middleware_VerifyCsrfToken {
    protected function runningUnitTests() {
        return false;
    }
}

class HttpVerifyCsrfTokenWithExceptForTest extends HttpVerifyCsrfTokenEnforcingForTest {
    protected $except = ['webhook/*', 'ping'];
}

class HttpVerifyCsrfTokenWithoutCookieForTest extends HttpVerifyCsrfTokenEnforcingForTest {
    protected $addHttpCookie = false;
}

class HttpVerifyCsrfTokenTest extends TestCase {
    protected $originalToken;

    protected function setUp(): void {
        parent::setUp();
        $this->originalToken = CBase::session()->get('_token');
        CBase::session()->put('_token', 'token-sesi-uji');
    }

    protected function tearDown(): void {
        if ($this->originalToken === null) {
            CBase::session()->forget('_token');
        } else {
            CBase::session()->put('_token', $this->originalToken);
        }
        parent::tearDown();
    }

    /**
     * @param CHTTP_Middleware_VerifyCsrfToken $middleware
     * @param CHTTP_Request                    $request
     *
     * @return CHTTP_Response
     */
    protected function pass($middleware, CHTTP_Request $request) {
        return $middleware->handle($request, function () {
            return new CHTTP_Response('lolos');
        });
    }

    protected function post($uri = '/form', array $data = [], array $server = []) {
        return CHTTP_Request::create($uri, 'POST', $data, [], [], $server);
    }

    public function testReadingMethodsPassWithoutAToken() {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $response = $this->pass(new HttpVerifyCsrfTokenEnforcingForTest(), CHTTP_Request::create('/form', $method));

            $this->assertSame('lolos', $response->getContent(), $method);
        }
    }

    public function testPostWithoutATokenIsRejected() {
        $this->expectException(CSession_Exception_TokenMismatchException::class);

        $this->pass(new HttpVerifyCsrfTokenEnforcingForTest(), $this->post());
    }

    public function testPostWithAWrongTokenIsRejected() {
        $this->expectException(CSession_Exception_TokenMismatchException::class);

        $this->pass(new HttpVerifyCsrfTokenEnforcingForTest(), $this->post('/form', ['_token' => 'salah']));
    }

    public function testPostWithTheSessionTokenInTheBodyPasses() {
        $response = $this->pass(new HttpVerifyCsrfTokenEnforcingForTest(), $this->post('/form', ['_token' => 'token-sesi-uji']));

        $this->assertSame('lolos', $response->getContent());
    }

    public function testPostWithTheSessionTokenInTheCsrfHeaderPasses() {
        $request = $this->post('/form', [], ['HTTP_X_CSRF_TOKEN' => 'token-sesi-uji']);

        $this->assertSame('lolos', $this->pass(new HttpVerifyCsrfTokenEnforcingForTest(), $request)->getContent());
    }

    public function testAnUndecryptableXsrfHeaderIsTreatedAsNoToken() {
        $this->expectException(CSession_Exception_TokenMismatchException::class);

        $this->pass(new HttpVerifyCsrfTokenEnforcingForTest(), $this->post('/form', [], ['HTTP_X_XSRF_TOKEN' => 'bukan-hasil-enkripsi']));
    }

    public function testAMissingSessionTokenNeverMatches() {
        CBase::session()->forget('_token');

        $this->expectException(CSession_Exception_TokenMismatchException::class);

        $this->pass(new HttpVerifyCsrfTokenEnforcingForTest(), $this->post('/form', ['_token' => 'apa-saja']));
    }

    public function testExceptPathsSkipTheCheckOthersStillFail() {
        $middleware = new HttpVerifyCsrfTokenWithExceptForTest();

        $this->assertSame('lolos', $this->pass($middleware, $this->post('/webhook/xendit'))->getContent());
        $this->assertSame('lolos', $this->pass($middleware, $this->post('/ping'))->getContent());

        $this->expectException(CSession_Exception_TokenMismatchException::class);
        $this->pass($middleware, $this->post('/form'));
    }

    public function testASuccessfulResponseGetsAReadableXsrfTokenCookie() {
        $response = $this->pass(new HttpVerifyCsrfTokenEnforcingForTest(), CHTTP_Request::create('/form', 'GET'));

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertSame('XSRF-TOKEN', $cookies[0]->getName());
        $this->assertSame('token-sesi-uji', $cookies[0]->getValue());
        $this->assertFalse($cookies[0]->isHttpOnly(), 'dibaca JavaScript untuk header X-XSRF-TOKEN');
    }

    public function testTheCookieCanBeDisabled() {
        $response = $this->pass(new HttpVerifyCsrfTokenWithoutCookieForTest(), CHTTP_Request::create('/form', 'GET'));

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testEverythingPassesWhenRunningUnderCliTesting() {
        $response = $this->pass(new CHTTP_Middleware_VerifyCsrfToken(), $this->post());

        $this->assertSame('lolos', $response->getContent(), 'CLI + testing melewati pengecekan, seperti pada middleware sejenis');
    }
}
