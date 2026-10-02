<?php

use PHPUnit\Framework\TestCase;
use Illuminate\Contracts\Support\Jsonable;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Tambahan Response: withoutHeader, withCookies, withoutCookie(s), statusText, Cookie::expire/flushQueuedCookies,
 * RedirectResponse::enforceSameOrigin/getSession/setSession, Redirector::getIntendedUrl, ResponseException $previous,
 * JsonResponse::setData membersihkan status galat json.
 */
class HttpResponseAdditionsJsonableForTest implements Jsonable {
    public function toJson($options = 0) {
        return '{"a":1}';
    }
}

class HttpResponseAdditionsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        CHTTP::cookie()->flushQueuedCookies();
    }

    protected function tearDown(): void {
        CHTTP::cookie()->flushQueuedCookies();
        parent::tearDown();
    }

    public function testWithoutHeaderRemovesOneOrManyHeaders() {
        $response = (new CHTTP_Response('x'))->header('X-A', '1')->header('X-B', '2')->header('X-C', '3');

        $this->assertSame($response, $response->withoutHeader('X-A'));
        $response->withoutHeader(['X-B', 'X-C']);

        $this->assertFalse($response->headers->has('X-A'));
        $this->assertFalse($response->headers->has('X-B'));
        $this->assertFalse($response->headers->has('X-C'));
    }

    public function testWithCookiesAddsEveryCookie() {
        $response = (new CHTTP_Response('x'))->withCookies([new Cookie('a', '1'), new Cookie('b', '2')]);

        $names = array_map(function ($cookie) {
            return $cookie->getName();
        }, $response->headers->getCookies());
        $this->assertSame(['a', 'b'], $names);
    }

    public function testWithoutCookieExpiresACookieByNameOrByInstance() {
        $response = (new CHTTP_Response('x'))->withoutCookie('lama', '/rute', 'contoh.test');

        $cookie = $response->headers->getCookies()[0];
        $this->assertSame('lama', $cookie->getName());
        $this->assertNull($cookie->getValue());
        $this->assertLessThan(time(), $cookie->getExpiresTime());
        $this->assertSame('/rute', $cookie->getPath());
        $this->assertSame('contoh.test', $cookie->getDomain());

        $instance = (new CHTTP_Response('x'))->withoutCookie(new Cookie('b', 'v', 1));
        $this->assertSame('b', $instance->headers->getCookies()[0]->getName());
    }

    public function testWithoutCookiesExpiresEachOne() {
        $response = (new CHTTP_Response('x'))->withoutCookies(['a', 'b']);

        $this->assertCount(2, $response->headers->getCookies());
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertLessThan(time(), $cookie->getExpiresTime());
        }
    }

    public function testStatusTextIsTheReasonPhrase() {
        $this->assertSame('Not Found', (new CHTTP_Response('x', 404))->statusText());
        $this->assertSame('OK', (new CHTTP_JsonResponse([]))->statusText());
        $this->assertSame('Found', (new CHTTP_RedirectResponse('https://contoh.test'))->statusText());
    }

    public function testCookieJarExpireQueuesAnExpiredCookieAndFlushClearsTheQueue() {
        $jar = CHTTP::cookie();

        $jar->expire('basi', '/rute');

        $this->assertTrue($jar->hasQueued('basi', '/rute'));
        $this->assertLessThan(time(), $jar->queued('basi', null, '/rute')->getExpiresTime());

        $this->assertSame($jar, $jar->flushQueuedCookies());
        $this->assertSame([], $jar->getQueuedCookies());
    }

    /**
     * @return CHTTP_Request
     */
    protected function request($url = 'https://contoh.test/halaman') {
        return CHTTP_Request::create($url, 'GET');
    }

    public function testEnforceSameOriginKeepsSameOriginTargets() {
        $response = (new CHTTP_RedirectResponse('https://contoh.test/tujuan?x=1'))->setRequest($this->request());

        $this->assertSame($response, $response->enforceSameOrigin('/beranda'));
        $this->assertSame('https://contoh.test/tujuan?x=1', $response->getTargetUrl());
    }

    public function testEnforceSameOriginFallsBackForOtherHostsSchemesAndPorts() {
        foreach (['https://jahat.test/x', 'http://contoh.test/x', 'https://contoh.test:8443/x', '//jahat.test/x'] as $target) {
            $response = (new CHTTP_RedirectResponse($target))->setRequest($this->request());

            $response->enforceSameOrigin('https://contoh.test/beranda');

            $this->assertSame('https://contoh.test/beranda', $response->getTargetUrl(), $target);
        }
    }

    public function testEnforceSameOriginCanIgnoreSchemeAndPortAndIsCaseInsensitiveOnHosts() {
        $scheme = (new CHTTP_RedirectResponse('http://contoh.test/x'))->setRequest($this->request())->enforceSameOrigin('/f', false);
        $this->assertSame('http://contoh.test/x', $scheme->getTargetUrl());

        $port = (new CHTTP_RedirectResponse('https://contoh.test:8443/x'))->setRequest($this->request())->enforceSameOrigin('/f', true, false);
        $this->assertSame('https://contoh.test:8443/x', $port->getTargetUrl());

        $case = (new CHTTP_RedirectResponse('https://CONTOH.test/x'))->setRequest($this->request())->enforceSameOrigin('/f');
        $this->assertSame('https://CONTOH.test/x', $case->getTargetUrl());
    }

    public function testEnforceSameOriginTreatsRelativePathsAsSameOriginAndExplicitDefaultPortsAsEqual() {
        $relative = (new CHTTP_RedirectResponse('/dalam/situs'))->setRequest($this->request())->enforceSameOrigin('/f');
        $this->assertSame('/dalam/situs', $relative->getTargetUrl());

        $port = (new CHTTP_RedirectResponse('https://contoh.test:443/x'))->setRequest($this->request())->enforceSameOrigin('/f');
        $this->assertSame('https://contoh.test:443/x', $port->getTargetUrl());
    }

    public function testRedirectResponseSessionCanBeInjected() {
        $session = new CSession_Store('uji', new CSession_Handler_ArraySessionHandler(120));
        $response = new CHTTP_RedirectResponse('https://contoh.test');

        $this->assertSame($response, $response->setSession($session));
        $this->assertSame($session, $response->getSession());
        $response->with('pesan', 'tersimpan');

        $this->assertSame('tersimpan', $session->get('pesan'));
    }

    public function testSetRequestIsFluent() {
        $response = new CHTTP_RedirectResponse('https://contoh.test');
        $request = $this->request();

        $this->assertSame($response, $response->setRequest($request));
        $this->assertSame($request, $response->getRequest());
    }

    public function testRedirectorIntendedUrlCanBeSetAndRead() {
        $redirector = CHTTP_Redirector::instance();

        $this->assertSame($redirector, $redirector->setIntendedUrl('https://contoh.test/tujuan-awal'));
        $this->assertSame('https://contoh.test/tujuan-awal', $redirector->getIntendedUrl());

        $redirector->intended('/default');
        $this->assertNull($redirector->getIntendedUrl(), 'intended() mengambil lalu menghapusnya');
    }

    public function testResponseExceptionKeepsThePreviousExceptionAndItsMessage() {
        $previous = new RuntimeException('penyebab asli', 7);

        $e = new CHTTP_Exception_ResponseException(new CHTTP_Response('x', 418), $previous);

        $this->assertSame($previous, $e->getPrevious());
        $this->assertSame('penyebab asli', $e->getMessage());
        $this->assertSame(7, $e->getCode());
        $this->assertSame('', (new CHTTP_Exception_ResponseException(new CHTTP_Response('x')))->getMessage());
    }

    public function testJsonResponseIgnoresAStaleJsonErrorFromAnEarlierCall() {
        json_decode('{rusak');
        $this->assertNotSame(JSON_ERROR_NONE, json_last_error());

        $response = new CHTTP_JsonResponse(new HttpResponseAdditionsJsonableForTest());

        $this->assertSame('{"a":1}', $response->getContent());
    }

    public function testJsonResponseStillThrowsForGenuineEncodingErrors() {
        $this->expectException(InvalidArgumentException::class);

        new CHTTP_JsonResponse(["\xB1\x31"]);
    }
}
