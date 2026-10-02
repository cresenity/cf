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

class HttpResponseCacheGadgetForTest {
    public static $woken = false;

    public function __wakeup() {
        static::$woken = true;
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

    public function testClearOnAnUntaggedStoreOnlyInvalidatesResponseCacheEntries() {
        $store = c::cache()->store('array');
        $store->put('milik-lain-' . __FUNCTION__, 'tetap', 60);
        $cache = new CHTTP_ResponseCache();
        $cache->useCache($store);
        $cache->enable();
        $a = CHTTP_Request::create('https://contoh.test/' . __FUNCTION__ . '/a', 'GET');
        $b = CHTTP_Request::create('https://contoh.test/' . __FUNCTION__ . '/b', 'GET');
        $cache->cacheResponse($a, new CHTTP_Response('A'));
        $cache->cacheResponse($b, new CHTTP_Response('B'));
        $this->assertTrue($cache->hasBeenCached($a));

        $cache->clear();

        $this->assertFalse($cache->hasBeenCached($a));
        $this->assertFalse($cache->hasBeenCached($b));
        $this->assertSame('tetap', $store->get('milik-lain-' . __FUNCTION__), 'kunci lain di store yang sama tidak ikut terhapus');

        $cache->cacheResponse($a, new CHTTP_Response('A2'));
        $this->assertSame('A2', $cache->getCachedResponseFor($a)->getContent(), 'setelah clear() cache bisa dipakai lagi');
    }

    public function testClearIsSeenByAnotherInstanceOnTheSameStore() {
        $store = c::cache()->store('array');
        $request = CHTTP_Request::create('https://contoh.test/' . __FUNCTION__, 'GET');
        $first = new CHTTP_ResponseCache();
        $first->useCache($store);
        $first->cacheResponse($request, new CHTTP_Response('x'));
        $second = new CHTTP_ResponseCache();
        $second->useCache($store);
        $this->assertTrue($second->hasBeenCached($request));

        $first->clear();

        $freshInstance = new CHTTP_ResponseCache();
        $freshInstance->useCache($store);
        $this->assertFalse($freshInstance->hasBeenCached($request), 'proses/request lain membaca generasi baru');
    }

    public function testForgetHonorsTags() {
        $cache = $this->cache();
        $request = CHTTP_Request::create('https://contoh.test/' . __FUNCTION__, 'GET');
        $cache->cacheResponse($request, new CHTTP_Response('x'));

        $cache->forget('https://contoh.test/' . __FUNCTION__, ['tag-lain']);
        $this->assertTrue($cache->hasBeenCached($request), 'forget dengan tag hanya menyentuh entri bertag itu');

        $cache->forget('https://contoh.test/' . __FUNCTION__);
        $this->assertFalse($cache->hasBeenCached($request));
    }

    public function testUnserializeDoesNotInstantiateUnexpectedClasses() {
        $payload = serialize([
            'statusCode' => 200,
            'headers' => $this->responseWithCookie()->headers,
            'content' => 'x',
            'type' => 'normal',
            'gadget' => new HttpResponseCacheGadgetForTest(),
        ]);
        HttpResponseCacheGadgetForTest::$woken = false;

        (new CHTTP_ResponseCache_Serializer_DefaultSerializer())->unserialize($payload);

        $this->assertFalse(HttpResponseCacheGadgetForTest::$woken, 'kelas di luar daftar izin tidak boleh dibangkitkan dari store cache');
    }

    public function testPayloadWithoutAHeaderBagIsRejected() {
        $this->expectException(CHTTP_ResponseCache_Exception_CouldNotUnserializeException::class);

        (new CHTTP_ResponseCache_Serializer_DefaultSerializer())->unserialize(serialize([
            'statusCode' => 200,
            'headers' => new HttpResponseCacheGadgetForTest(),
            'content' => 'x',
        ]));
    }

    public function testGetReturnsNullWhenTheEntryExpiredBetweenHasAndGet() {
        $repository = new CHTTP_ResponseCache_Repository(c::cache()->store('array'));

        $this->assertNull($repository->get('responsecache-tidak-ada-' . __FUNCTION__));
    }

    public function testForgetAcceptsAnArrayOfUris() {
        $cache = $this->cache();
        $a = CHTTP_Request::create('https://contoh.test/' . __FUNCTION__ . '/a', 'GET');
        $b = CHTTP_Request::create('https://contoh.test/' . __FUNCTION__ . '/b', 'GET');
        $cache->cacheResponse($a, new CHTTP_Response('A'));
        $cache->cacheResponse($b, new CHTTP_Response('B'));

        $cache->forget(['https://contoh.test/' . __FUNCTION__ . '/a', 'https://contoh.test/' . __FUNCTION__ . '/b']);

        $this->assertFalse($cache->hasBeenCached($a));
        $this->assertFalse($cache->hasBeenCached($b));
    }
}
