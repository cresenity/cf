<?php

use PHPUnit\Framework\TestCase;

/**
 * TrustProxies: konfigurasi header terpercaya berupa bitmask dihormati apa adanya (kombinasi kustom tidak
 * melebar ke semua header X-Forwarded-*), nama konstanta dan nilai tunggal tetap bekerja.
 */
class HttpTrustProxiesForTest extends CHTTP_Middleware_TrustProxies {
    public function withHeaders($headers) {
        $this->headers = $headers;

        return $this;
    }

    public function names() {
        return $this->getTrustedHeaderNames();
    }
}

class HttpTrustProxyLegacyForTest extends CMiddleware_TrustProxy {
    public function withHeaders($headers) {
        $this->headers = $headers;

        return $this;
    }

    public function names() {
        return $this->getTrustedHeaderNames();
    }
}

class HttpTrustProxiesMiddlewareTest extends TestCase {
    protected function all() {
        return CHTTP_Request::HEADER_X_FORWARDED_FOR | CHTTP_Request::HEADER_X_FORWARDED_HOST | CHTTP_Request::HEADER_X_FORWARDED_PORT | CHTTP_Request::HEADER_X_FORWARDED_PROTO | CHTTP_Request::HEADER_X_FORWARDED_AWS_ELB;
    }

    /**
     * @return array
     */
    public function middlewareProvider() {
        return [
            'CHTTP_Middleware_TrustProxies' => [new HttpTrustProxiesForTest()],
            'CMiddleware_TrustProxy' => [new HttpTrustProxyLegacyForTest()],
        ];
    }

    /**
     * @dataProvider middlewareProvider
     */
    public function testACustomBitmaskIsHonoredAndNotWidenedToAllHeaders($middleware) {
        $forAndProto = CHTTP_Request::HEADER_X_FORWARDED_FOR | CHTTP_Request::HEADER_X_FORWARDED_PROTO;

        $this->assertSame($forAndProto, $middleware->withHeaders($forAndProto)->names());
        $this->assertNotSame($this->all(), $middleware->names());
    }

    /**
     * @dataProvider middlewareProvider
     */
    public function testSingleHeaderConstantsAndTheirNamesStillWork($middleware) {
        $this->assertSame(CHTTP_Request::HEADER_FORWARDED, $middleware->withHeaders(CHTTP_Request::HEADER_FORWARDED)->names());
        $this->assertSame(CHTTP_Request::HEADER_X_FORWARDED_AWS_ELB, $middleware->withHeaders('HEADER_X_FORWARDED_AWS_ELB')->names());
        $this->assertSame(CHTTP_Request::HEADER_X_FORWARDED_PORT, $middleware->withHeaders('HEADER_X_FORWARDED_PORT')->names());
    }

    /**
     * @dataProvider middlewareProvider
     */
    public function testTheFullBitmaskAndUnknownValuesStillMeanAllHeaders($middleware) {
        $this->assertSame($this->all(), $middleware->withHeaders($this->all())->names());
        $this->assertSame($this->all(), $middleware->withHeaders('tidak-dikenal')->names());
    }

    public function testNoExplicitHeadersFallsBackToTheConfigValue() {
        $this->assertSame((int) CF::config('http.trustedproxy.headers'), (new HttpTrustProxiesForTest())->names());
    }
}
