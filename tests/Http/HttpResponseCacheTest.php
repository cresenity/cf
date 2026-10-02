<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * CHTTP_ResponseCache: cookie pengunjung pertama tidak boleh tersimpan maupun diputar ulang ke pengunjung lain,
 * dan respons yang menyatakan dirinya per-pengguna tidak ikut di-cache.
 */
class HttpResponseCacheProfileForTest extends CHTTP_ResponseCache_CacheProfile {
    public function isRunningInConsole() {
        return false;
    }
}

class HttpResponseCacheTest extends TestCase {
    /**
     * @return CHTTP_Response
     */
    protected function responseWithCookie($body = 'halaman') {
        $response = new CHTTP_Response($body);
        $response->headers->setCookie(new Cookie('sesi', 'milik-pengunjung-pertama', time() + 3600, '/', null, false, true));
        $response->headers->set('X-Lain', 'tetap-ada');

        return $response;
    }

    /**
     * @return CHTTP_ResponseCache
     */
    protected function cache() {
        $cache = new CHTTP_ResponseCache();
        $cache->useCache(c::cache()->store('array'));
        $cache->enable();

        return $cache;
    }

    public function testSerializedResponseKeepsBodyAndHeadersButNotCookies() {
        $serializer = new CHTTP_ResponseCache_Serializer_DefaultSerializer();

        $restored = $serializer->unserialize($serializer->serialize($this->responseWithCookie('isi')));

        $this->assertSame('isi', $restored->getContent());
        $this->assertSame('tetap-ada', $restored->headers->get('X-Lain'));
        $this->assertSame([], $restored->headers->getCookies());
        $this->assertFalse($restored->headers->has('Set-Cookie'));
    }

    public function testSerializingDoesNotStripCookiesFromTheOriginalResponse() {
        $serializer = new CHTTP_ResponseCache_Serializer_DefaultSerializer();
        $response = $this->responseWithCookie();

        $serializer->serialize($response);

        $this->assertCount(1, $response->headers->getCookies(), 'pengunjung pertama tetap menerima cookie-nya sendiri');
    }

    public function testEntriesStoredBeforeTheFixAreStrippedWhenRead() {
        $poisoned = serialize([
            'statusCode' => 200,
            'headers' => $this->responseWithCookie()->headers,
            'content' => 'lama',
            'type' => 'normal',
        ]);

        $restored = (new CHTTP_ResponseCache_Serializer_DefaultSerializer())->unserialize($poisoned);

        $this->assertSame('lama', $restored->getContent());
        $this->assertSame([], $restored->headers->getCookies(), 'cache lama yang sudah teracuni berhenti membocorkan tanpa perlu flush');
    }

    public function testSecondVisitorDoesNotReceiveTheFirstVisitorsCookie() {
        $cache = $this->cache();
        $request = CHTTP_Request::create('https://contoh.test/halaman', 'GET');

        $cache->makeReplacementsAndCacheResponse($request, $this->responseWithCookie());

        $this->assertTrue($cache->hasBeenCached($request));
        $hit = $cache->getCachedResponseFor($request);
        $this->assertSame('halaman', $hit->getContent());
        $this->assertSame([], $hit->headers->getCookies());
    }

    public function testHasherIsCreatedLazilyWhenOnlyUseCacheWasCalled() {
        $cache = new CHTTP_ResponseCache();
        $cache->useCache(c::cache()->store('array'));
        $request = CHTTP_Request::create('https://contoh.test/lazy', 'GET');

        $cache->cacheResponse($request, new CHTTP_Response('x'));

        $this->assertSame('x', $cache->getCachedResponseFor($request)->getContent());
    }

    public function testPrivateNoStoreAndVaryCookieResponsesAreNotCacheable() {
        $profile = new HttpResponseCacheProfileForTest();

        $plain = new CHTTP_Response('x');
        $this->assertTrue($profile->shouldCacheResponse($plain));

        foreach (['Cache-Control' => ['private', 'no-store', 'public, private, max-age=0'], 'Vary' => ['Cookie', 'Accept-Encoding, Cookie']] as $header => $values) {
            foreach ($values as $value) {
                $response = new CHTTP_Response('x');
                $response->headers->set($header, $value);
                $this->assertFalse($profile->shouldCacheResponse($response), $header . ': ' . $value);
            }
        }

        $harmless = new CHTTP_Response('x');
        $harmless->headers->set('Cache-Control', 'no-cache');
        $harmless->headers->set('Vary', 'Accept-Encoding');
        $this->assertTrue($profile->shouldCacheResponse($harmless), 'no-cache dan Vary tanpa Cookie tetap boleh');
    }

    public function testRequestsWithAuthorizationAjaxOrNonGetAreNotCacheable() {
        $profile = new HttpResponseCacheProfileForTest();

        $this->assertTrue($profile->shouldCacheRequest(CHTTP_Request::create('https://contoh.test/a', 'GET')));

        $withAuthorization = CHTTP_Request::create('https://contoh.test/a', 'GET');
        $withAuthorization->headers->set('Authorization', 'Bearer rahasia');
        $this->assertFalse($profile->shouldCacheRequest($withAuthorization));

        $ajax = CHTTP_Request::create('https://contoh.test/a', 'GET');
        $ajax->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->assertFalse($profile->shouldCacheRequest($ajax));

        $this->assertFalse($profile->shouldCacheRequest(CHTTP_Request::create('https://contoh.test/a', 'POST')));
    }
}
