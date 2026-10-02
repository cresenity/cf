<?php

use PHPUnit\Framework\TestCase;

/**
 * CHTTP_Pipeline: exception dari sebuah middleware dirender menjadi respons di dalam onion, sehingga
 * middleware yang lebih luar tetap ikut memproses respons error itu (cookie, header CORS, dst.).
 */
class HttpPipelineTest extends TestCase {
    /**
     * @param CHTTP_Request $request
     * @param array         $pipes
     * @param Closure       $destination
     *
     * @return mixed
     */
    protected function runThrough($request, array $pipes, Closure $destination = null) {
        return (new CHTTP_Pipeline())->send($request)->through($pipes)->then($destination ?: function ($request) {
            return new CHTTP_Response('tujuan');
        });
    }

    protected function request() {
        return CHTTP_Request::create('https://contoh.test/uji', 'GET');
    }

    public function testExceptionFromAMiddlewareBecomesAResponseInsideTheOnion() {
        $outer = function ($request, $next) {
            return $next($request)->header('X-Luar', 'ikut');
        };
        $failing = function ($request, $next) {
            throw new CHTTP_Exception_ResponseException(new CHTTP_Response('ditolak', 419));
        };

        $response = $this->runThrough($this->request(), [$outer, $failing]);

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame('ditolak', $response->getContent());
        $this->assertSame('ikut', $response->headers->get('X-Luar'), 'middleware luar ikut memproses respons error');
    }

    public function testMiddlewareAfterTheFailingOneIsNotReached() {
        $reached = false;
        $failing = function ($request, $next) {
            throw new CHTTP_Exception_ResponseException(new CHTTP_Response('stop', 429));
        };
        $inner = function ($request, $next) use (&$reached) {
            $reached = true;

            return $next($request);
        };

        $this->runThrough($this->request(), [$failing, $inner]);

        $this->assertFalse($reached);
    }

    public function testExceptionFromTheDestinationIsStillRenderedInsideTheOnion() {
        $outer = function ($request, $next) {
            return $next($request)->header('X-Luar', 'ikut');
        };

        $response = $this->runThrough($this->request(), [$outer], function ($request) {
            throw new CHTTP_Exception_ResponseException(new CHTTP_Response('dari tujuan', 403));
        });

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('ikut', $response->headers->get('X-Luar'));
    }

    public function testNonRequestPassableStillRethrows() {
        $failing = function ($passable, $next) {
            throw new RuntimeException('gagal');
        };

        $this->expectException(RuntimeException::class);

        (new CHTTP_Pipeline())->send('bukan-request')->through([$failing])->then(function ($passable) {
            return $passable;
        });
    }

    public function testBasePipelineStillRethrowsExceptionsFromPipes() {
        $failing = function ($passable, $next) {
            throw new RuntimeException('gagal');
        };

        $this->expectException(RuntimeException::class);

        (new CBase_Pipeline())->send('x')->through([$failing])->then(function ($passable) {
            return $passable;
        });
    }
}
