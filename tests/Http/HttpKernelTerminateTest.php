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

    public function testTerminatingEventIsDispatchedBeforeTerminate() {
        $fired = 0;
        CEvent::dispatcher()->listen(CHTTP_Event_Terminating::class, function () use (&$fired) {
            $fired++;
        });

        try {
            (new CHTTP_Kernel())->terminate($this->request(), new CHTTP_Response('x'));
        } finally {
            CEvent::dispatcher()->forget(CHTTP_Event_Terminating::class);
        }

        $this->assertSame(1, $fired);
    }

    public function testRequestStartedAtIsSetByHandleAndClearedByTerminate() {
        $kernel = new CHTTP_Kernel();
        $this->assertNull($kernel->requestStartedAt());

        $request = CHTTP_Request::create('https://contoh.test/jalur-tidak-ada-' . uniqid(), 'GET');
        $response = $kernel->handle($request);

        $this->assertInstanceOf(CCarbon::class, $kernel->requestStartedAt());
        $this->assertLessThanOrEqual(time() + 1, $kernel->requestStartedAt()->getTimestamp());

        $kernel->terminate($request, $response);
        $this->assertNull($kernel->requestStartedAt());
    }

    public function testSlowRequestHandlersRunOnlyWhenTheThresholdIsExceeded() {
        $kernel = new CHTTP_Kernel();
        $calls = [];
        $kernel->whenRequestLifecycleIsLongerThan(60000, function () use (&$calls) {
            $calls[] = 'lama-sekali';
        });
        $kernel->whenRequestLifecycleIsLongerThan(0, function ($startedAt, $request, $response) use (&$calls) {
            $calls[] = [get_class($startedAt), $request->path(), $response->getStatusCode()];
        });
        $request = CHTTP_Request::create('https://contoh.test/jalur-tidak-ada-' . uniqid(), 'GET');
        $response = $kernel->handle($request);
        usleep(2000);

        $kernel->terminate($request, $response);

        $this->assertCount(1, $calls);
        $this->assertSame(CCarbon::class, $calls[0][0]);
        $this->assertSame(404, $calls[0][2]);
    }

    public function testTheDurationThresholdAcceptsAnInterval() {
        $kernel = new CHTTP_Kernel();
        $called = false;
        $kernel->whenRequestLifecycleIsLongerThan(\Carbon\CarbonInterval::milliseconds(1), function () use (&$called) {
            $called = true;
        });
        $request = CHTTP_Request::create('https://contoh.test/jalur-tidak-ada-' . uniqid(), 'GET');
        $response = $kernel->handle($request);
        usleep(3000);

        $kernel->terminate($request, $response);

        $this->assertTrue($called);
    }

    public function testMiddlewareManagerDeclaresItsRouteMiddlewareAndPriorityLists() {
        $this->assertSame([], CMiddleware::manager()->getRouteMiddleware());
    }
}
