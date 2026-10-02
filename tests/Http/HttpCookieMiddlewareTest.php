<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * EncryptCookies dan AddQueuedCookiesToResponse: tes karakterisasi perilaku saat ini.
 */
class HttpEncryptCookiesDisabledForTest extends CHTTP_Cookie_Middleware_EncryptCookies {
    protected $except = ['polos'];
}

class HttpCookieMiddlewareTest extends TestCase {
    /**
     * @param string $name
     * @param string $value
     *
     * @return string nilai terenkripsi seperti yang dikirim ke browser
     */
    protected function encryptedValue($name, $value, $middleware = null) {
        $middleware = $middleware ?: new CHTTP_Cookie_Middleware_EncryptCookies();
        $response = $middleware->handle(CHTTP_Request::create('/x'), function () use ($name, $value) {
            $response = new CHTTP_Response('ok');
            $response->headers->setCookie(new Cookie($name, $value, time() + 3600, '/rute', 'contoh.test', true, true, false, 'lax'));

            return $response;
        });
        $cookies = $response->headers->getCookies();

        return $cookies[0]->getValue();
    }

    /**
     * @param array $cookies
     *
     * @return array cookie yang dilihat controller setelah dekripsi
     */
    protected function seenByController(array $cookies, $middleware = null) {
        $middleware = $middleware ?: new CHTTP_Cookie_Middleware_EncryptCookies();
        $seen = [];
        $request = CHTTP_Request::create('/x', 'GET', [], $cookies);
        $middleware->handle($request, function ($request) use (&$seen) {
            $seen = $request->cookies->all();

            return new CHTTP_Response('ok');
        });

        return $seen;
    }

    public function testResponseCookiesAreEncryptedAndKeepTheirAttributes() {
        $middleware = new CHTTP_Cookie_Middleware_EncryptCookies();
        $response = $middleware->handle(CHTTP_Request::create('/x'), function () {
            $response = new CHTTP_Response('ok');
            $response->headers->setCookie(new Cookie('sesi', 'rahasia', time() + 3600, '/rute', 'contoh.test', true, true, false, 'lax'));

            return $response;
        });

        $cookie = $response->headers->getCookies()[0];
        $this->assertNotSame('rahasia', $cookie->getValue());
        $this->assertStringNotContainsString('rahasia', $cookie->getValue());
        $this->assertSame('/rute', $cookie->getPath());
        $this->assertSame('contoh.test', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function testRequestCookiesAreDecryptedForTheController() {
        $encrypted = $this->encryptedValue('sesi', 'rahasia');

        $this->assertSame('rahasia', $this->seenByController(['sesi' => $encrypted])['sesi']);
    }

    public function testAPlainOrTamperedCookieBecomesNull() {
        $encrypted = $this->encryptedValue('sesi', 'rahasia');

        $seen = $this->seenByController(['polos' => 'tidak-dienkripsi', 'rusak' => substr($encrypted, 0, -4) . 'AAAA']);

        $this->assertNull($seen['polos']);
        $this->assertNull($seen['rusak']);
    }

    public function testACookieEncryptedForOneNameIsRejectedUnderAnotherName() {
        $encrypted = $this->encryptedValue('sesi', 'rahasia');

        $this->assertNull($this->seenByController(['lain' => $encrypted])['lain'], 'nilai terikat nama cookie lewat prefix');
    }

    public function testDisabledCookiesAreNeitherEncryptedNorDecrypted() {
        $middleware = new HttpEncryptCookiesDisabledForTest();

        $this->assertSame('apa-adanya', $this->encryptedValue('polos', 'apa-adanya', $middleware));
        $this->assertSame('apa-adanya', $this->seenByController(['polos' => 'apa-adanya'], $middleware)['polos']);
    }

    public function testDisableForAddsToTheExceptList() {
        $middleware = new CHTTP_Cookie_Middleware_EncryptCookies();
        $middleware->disableFor(['a', 'b']);
        $middleware->disableFor('c');

        $this->assertTrue($middleware->isDisabled('a'));
        $this->assertTrue($middleware->isDisabled('c'));
        $this->assertFalse($middleware->isDisabled('d'));
    }

    public function testArrayCookiesFromTheClientAreLeftUntouched() {
        $seen = $this->seenByController(['daftar' => ['a' => 'mentah', 'b' => 'mentah2']]);

        $this->assertSame(['a' => 'mentah', 'b' => 'mentah2'], $seen['daftar'], 'nilai array dari klien lolos tanpa dekripsi');
    }

    public function testThePartitionedFlagIsLostWhenACookieIsEncrypted() {
        $middleware = new CHTTP_Cookie_Middleware_EncryptCookies();
        $response = $middleware->handle(CHTTP_Request::create('/x'), function () {
            $response = new CHTTP_Response('ok');
            $response->headers->setCookie(new Cookie('sesi', 'v', time() + 3600, '/', null, true, true, false, 'none', true));

            return $response;
        });

        $this->assertFalse($response->headers->getCookies()[0]->isPartitioned(), 'duplicate() tidak membawa partitioned');
    }

    public function testQueuedCookiesAreAddedToTheResponse() {
        $jar = CHTTP::cookie();
        $jar->queue('antre-uji', 'nilai-uji', 5);

        try {
            $response = (new CHTTP_Cookie_Middleware_AddQueuedCookiesToResponse())->handle(CHTTP_Request::create('/x'), function () {
                return new CHTTP_Response('ok');
            });

            $names = array_map(function ($cookie) {
                return $cookie->getName();
            }, $response->headers->getCookies());
            $this->assertContains('antre-uji', $names);
        } finally {
            $jar->unqueue('antre-uji');
        }
    }
}
