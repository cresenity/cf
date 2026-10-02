<?php

use PHPUnit\Framework\TestCase;

/**
 * CHTTP_Middleware_ThrottleRequest: tes karakterisasi perilaku saat ini (batas, header, kunci per IP/prefix,
 * limiter bernama). Limiter diganti dengan store array agar tidak menyentuh cache app.
 */
class HttpThrottleRequestTest extends TestCase {
    /**
     * @var CCache_RateLimiter
     */
    protected $limiter;

    protected function setUp(): void {
        parent::setUp();
        $this->limiter = new CCache_RateLimiter(c::cache()->store('array'));
    }

    /**
     * @return CHTTP_Middleware_ThrottleRequest
     */
    protected function middleware() {
        $middleware = new CHTTP_Middleware_ThrottleRequest();
        $property = new ReflectionProperty($middleware, 'limiter');
        $property->setAccessible(true);
        $property->setValue($middleware, $this->limiter);

        return $middleware;
    }

    /**
     * @param string $ip
     *
     * @return CHTTP_Request
     */
    protected function request($ip = '10.0.0.1') {
        return CHTTP_Request::create('https://contoh.test/api', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
    }

    /**
     * @param CHTTP_Middleware_ThrottleRequest $middleware
     * @param CHTTP_Request                    $request
     * @param array                            $arguments
     *
     * @return CHTTP_Response
     */
    protected function hit($middleware, $request, array $arguments) {
        return $middleware->handle($request, function () {
            return new CHTTP_Response('ok');
        }, ...$arguments);
    }

    public function testRequestsUpToTheLimitPassAndTheNextOneIsRejectedWithRetryHeaders() {
        $middleware = $this->middleware();
        $prefix = __FUNCTION__;

        $this->assertSame('ok', $this->hit($middleware, $this->request(), [2, 1, $prefix])->getContent());
        $this->assertSame('ok', $this->hit($middleware, $this->request(), [2, 1, $prefix])->getContent());

        try {
            $this->hit($middleware, $this->request(), [2, 1, $prefix]);
            $this->fail('permintaan ketiga seharusnya ditolak');
        } catch (CHTTP_Exception_ThrottleRequestException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $headers = $e->getHeaders();
            $this->assertSame(2, $headers['X-RateLimit-Limit']);
            $this->assertSame(0, $headers['X-RateLimit-Remaining']);
            $this->assertArrayHasKey('Retry-After', $headers);
            $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        }
    }

    public function testSuccessfulResponsesCarryTheRemainingAttemptsHeaders() {
        $middleware = $this->middleware();
        $prefix = __FUNCTION__;

        $first = $this->hit($middleware, $this->request(), [3, 1, $prefix]);
        $second = $this->hit($middleware, $this->request(), [3, 1, $prefix]);

        $this->assertSame('3', $first->headers->get('X-RateLimit-Limit'));
        $this->assertSame('2', $first->headers->get('X-RateLimit-Remaining'));
        $this->assertSame('1', $second->headers->get('X-RateLimit-Remaining'));
        $this->assertNull($first->headers->get('Retry-After'));
    }

    public function testEachIpAndEachPrefixHasItsOwnCounter() {
        $middleware = $this->middleware();
        $prefix = __FUNCTION__;

        $this->hit($middleware, $this->request('10.0.0.1'), [1, 1, $prefix]);

        $this->assertSame('ok', $this->hit($middleware, $this->request('10.0.0.2'), [1, 1, $prefix])->getContent(), 'IP lain punya hitungan sendiri');
        $this->assertSame('ok', $this->hit($middleware, $this->request('10.0.0.1'), [1, 1, $prefix . '-lain'])->getContent(), 'prefix lain punya hitungan sendiri');

        $this->expectException(CHTTP_Exception_ThrottleRequestException::class);
        $this->hit($middleware, $this->request('10.0.0.1'), [1, 1, $prefix]);
    }

    public function testGuestAndUserLimitsCanBeGivenAsAPipeSeparatedPair() {
        $middleware = $this->middleware();
        $prefix = __FUNCTION__;

        $this->hit($middleware, $this->request(), ['1|10', 1, $prefix]);

        $this->expectException(CHTTP_Exception_ThrottleRequestException::class);
        $this->hit($middleware, $this->request(), ['1|10', 1, $prefix]);
    }

    public function testANonNumericLimitForAGuestBecomesZeroSoOnlyTheFirstRequestSlipsThrough() {
        $middleware = $this->middleware();
        $prefix = __FUNCTION__;

        $this->assertSame('ok', $this->hit($middleware, $this->request(), ['atribut-user', 1, $prefix])->getContent(), 'belum ada timer, hitungan direset');

        $this->expectException(CHTTP_Exception_ThrottleRequestException::class);
        $this->hit($middleware, $this->request(), ['atribut-user', 1, $prefix]);
    }

    public function testANamedLimiterIsUsedWhenOnlyItsNameIsGiven() {
        $name = 'uji-' . __FUNCTION__;
        $this->limiter->aliasFor($name, function () {
            return CCache_RateLimiting_Limit::perMinute(1)->by('kunci-tetap');
        });
        $middleware = $this->middleware();

        $this->assertSame('ok', $middleware->handle($this->request(), function () {
            return new CHTTP_Response('ok');
        }, $name)->getContent());

        $this->expectException(CHTTP_Exception_ThrottleRequestException::class);
        $middleware->handle($this->request(), function () {
            return new CHTTP_Response('ok');
        }, $name);
    }

    public function testANamedLimiterThatReturnsNoneLetsEverythingThrough() {
        $name = 'uji-' . __FUNCTION__;
        $this->limiter->aliasFor($name, function () {
            return CCache_RateLimiting_Limit::none();
        });
        $middleware = $this->middleware();

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('ok', $middleware->handle($this->request(), function () {
                return new CHTTP_Response('ok');
            }, $name)->getContent());
        }
    }

    public function testAResponseCallbackOnANamedLimiterBuildsTheRejection() {
        $name = 'uji-' . __FUNCTION__;
        $this->limiter->aliasFor($name, function () {
            return CCache_RateLimiting_Limit::perMinute(1)->by('kunci-callback')->response(function ($request, $headers) {
                return new CHTTP_Response('terlalu sering', 429);
            });
        });
        $middleware = $this->middleware();
        $run = function () use ($middleware, $name) {
            return $middleware->handle($this->request(), function () {
                return new CHTTP_Response('ok');
            }, $name);
        };
        $run();

        try {
            $run();
            $this->fail('seharusnya ditolak');
        } catch (CHTTP_Exception_ResponseException $e) {
            $this->assertSame('terlalu sering', $e->getResponse()->getContent());
        }
    }
}
