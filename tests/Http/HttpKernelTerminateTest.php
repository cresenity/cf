<?php

use PHPUnit\Framework\TestCase;

/**
 * CHTTP_Kernel::terminate(): terminate() middleware global ikut dipanggil dan callback c::defer()
 * dijalankan setelah respons (dilewati pada respons gagal kecuali ditandai "always").
 */
class HttpKernelTerminableMiddlewareForTest {
    public static $terminated = 0;

    public function handle($request, $next) {
        return $next($request);
    }

    public function terminate($request, $response) {
        static::$terminated++;
    }
}

class HttpKernelPlainMiddlewareForTest {
    public function handle($request, $next) {
        return $next($request);
    }
}

class HttpKernelTerminateTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        HttpKernelTerminableMiddlewareForTest::$terminated = 0;
        CBase_Defer_DeferredCallbackCollection::instance()->invokeWhen(function () {
            return false;
        });
    }

    /**
     * @return CHTTP_Request
     */
    protected function request() {
        return CHTTP_Request::create('https://contoh.test/uji', 'GET');
    }

    public function testTerminableMiddlewareListedOnTheKernelIsTerminated() {
        $kernel = new class() extends CHTTP_Kernel {
            protected $middleware = [HttpKernelTerminableMiddlewareForTest::class, HttpKernelPlainMiddlewareForTest::class];
        };

        $kernel->terminate($this->request(), new CHTTP_Response('x'));

        $this->assertSame(1, HttpKernelTerminableMiddlewareForTest::$terminated);
    }

    public function testGlobalMiddlewareFromTheMiddlewareManagerIsTerminated() {
        CMiddleware::manager()->pushMiddleware(HttpKernelTerminableMiddlewareForTest::class);

        try {
            (new CHTTP_Kernel())->terminate($this->request(), new CHTTP_Response('x'));
        } finally {
            $this->removeGlobalMiddleware(HttpKernelTerminableMiddlewareForTest::class);
        }

        $this->assertSame(1, HttpKernelTerminableMiddlewareForTest::$terminated);
    }

    protected function removeGlobalMiddleware($class) {
        $property = new ReflectionProperty(CMiddleware_Manager::class, 'middleware');
        $property->setAccessible(true);
        $manager = CMiddleware::manager();
        $property->setValue($manager, array_values(array_diff($property->getValue($manager), [$class])));
    }

    public function testDeferredCallbacksRunAfterASuccessfulResponse() {
        $ran = [];
        c::defer(function () use (&$ran) {
            $ran[] = 'biasa';
        });
        c::defer(function () use (&$ran) {
            $ran[] = 'selalu';
        }, null, true);

        (new CHTTP_Kernel())->terminate($this->request(), new CHTTP_Response('x', 200));

        $this->assertSame(['biasa', 'selalu'], $ran);
    }

    public function testDeferredCallbacksAreSkippedOnFailedResponsesUnlessAlways() {
        $ran = [];
        c::defer(function () use (&$ran) {
            $ran[] = 'biasa';
        });
        c::defer(function () use (&$ran) {
            $ran[] = 'selalu';
        }, null, true);

        (new CHTTP_Kernel())->terminate($this->request(), new CHTTP_Response('x', 500));

        $this->assertSame(['selalu'], $ran);
    }

    public function testDeferCollectsIntoOneSharedCollection() {
        c::defer(function () {
        }, 'satu');
        c::defer(function () {
        }, 'dua');

        $this->assertCount(2, CBase_Defer_DeferredCallbackCollection::instance());
        $this->assertSame(CBase_Defer_DeferredCallbackCollection::instance(), CBase_Defer_DeferredCallbackCollection::instance());
    }
}
