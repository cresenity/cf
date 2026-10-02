<?php

use PHPUnit\Framework\TestCase;

/**
 * Pembungkus output echo menjadi respons (CHTTP_Trait_OutputBufferTrait::makeResponseFromOutput), dipakai
 * CHTTP_Kernel dan CRouting_Route::runWithOutputBuffer(): header bernilai ber-":" tidak boleh terpotong.
 */
class HttpOutputBufferResponseHarness {
    use CHTTP_Trait_OutputBufferTrait;
}

class HttpOutputBufferResponseTest extends TestCase {
    /**
     * @param mixed      $output
     * @param null|array $headerLines
     *
     * @return CHTTP_Response
     */
    protected function wrap($output, array $headerLines = [], $statusCode = null) {
        return (new HttpOutputBufferResponseHarness())->makeResponseFromOutput($output, $headerLines, $statusCode);
    }

    public function testHeaderValueContainingColonsIsKeptWhole() {
        $response = $this->wrap('x', [
            'Location: https://example.com/a?b=1',
            'Expires: Thu, 19 Nov 1981 08:52:00 GMT',
            'Refresh: 0;url=https://example.com',
        ]);

        $this->assertSame('https://example.com/a?b=1', $response->headers->get('Location'));
        $this->assertSame('Thu, 19 Nov 1981 08:52:00 GMT', $response->headers->get('Expires'));
        $this->assertSame('0;url=https://example.com', $response->headers->get('Refresh'));
    }

    public function testHeaderNameAndValueAreTrimmed() {
        $response = $this->wrap('x', ['Content-Type:   text/plain; charset=UTF-8  ', ' X-Uji : nilai ']);

        $this->assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('nilai', $response->headers->get('X-Uji'));
    }

    public function testSetCookieIsNotCopiedIntoTheResponseHeaders() {
        $response = $this->wrap('x', ['Set-Cookie: sesi=abc; expires=Fri, 02 Oct 2026 08:39:16 GMT; path=/', 'X-Lain: ya']);

        $this->assertFalse($response->headers->has('Set-Cookie'), 'dibiarkan di header PHP agar atribut cookie tidak rusak');
        $this->assertSame('ya', $response->headers->get('X-Lain'));
    }

    public function testLinesWithoutColonAndEmptyNamesAreIgnored() {
        $response = $this->wrap('x', ['bukan-header', ': tanpa-nama', 'X-Ok: 1']);

        $this->assertSame('1', $response->headers->get('X-Ok'));
        $this->assertFalse($response->headers->has('bukan-header'));
    }

    public function testOutputBecomesBodyAndNonStringOutputBecomesEmpty() {
        $this->assertSame('halo', $this->wrap('halo')->getContent());
        $this->assertSame('', $this->wrap(false)->getContent(), 'ob_get_clean() tanpa buffer mengembalikan false');
        $this->assertSame('', $this->wrap(null)->getContent());
    }

    public function testNativeStatusCodeIsCarriedOverToTheResponse() {
        foreach ([404, 403, 500, 206, 204, 302] as $code) {
            $this->assertSame($code, $this->wrap('x', [], $code)->getStatusCode(), 'status ' . $code);
        }
    }

    public function testDefaultAndInvalidNativeStatusCodesKeepTheResponseAt200() {
        foreach ([null, 200, false, 0, 99, 600, '404', 'abc'] as $code) {
            $this->assertSame(200, $this->wrap('x', [], $code)->getStatusCode(), 'status ' . var_export($code, true));
        }
    }

    /**
     * @param callable $action
     *
     * @return CHTTP_Response
     */
    protected function dispatchRoute($action) {
        $router = new CRouting_Router();
        $router->get('uji/echo', $action);

        return $router->dispatch(CHTTP_Request::create('uji/echo', 'GET'));
    }

    public function testRouteThatOnlyEchoesBecomesTheResponseBody() {
        $response = $this->dispatchRoute(function () {
            echo 'halo echo';
        });

        $this->assertSame('halo echo', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEchoedOutputWinsOverAFalsyReturnValue() {
        foreach ([0, '', [], false] as $returned) {
            $response = $this->dispatchRoute(function () use ($returned) {
                echo 'tetap tampil';

                return $returned;
            });

            $this->assertSame('tetap tampil', $response->getContent(), 'return ' . json_encode($returned) . ' dianggap sama dengan tanpa return');
        }
    }

    public function testNonFalsyReturnValueWinsOverEchoedOutput() {
        $response = $this->dispatchRoute(function () {
            echo 'diabaikan';

            return 'dari return';
        });

        $this->assertSame('dari return', $response->getContent());
    }
}
