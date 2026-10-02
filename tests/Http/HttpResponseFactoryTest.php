<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CHTTP_ResponseFactory dan CHTTP_Redirector: tes karakterisasi perilaku saat ini (sebelumnya 0 tes).
 */
class HttpResponseFactoryTest extends TestCase {
    protected $tempFile;

    protected function setUp(): void {
        parent::setUp();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'respfactory');
        file_put_contents($this->tempFile, 'isi berkas');
    }

    protected function tearDown(): void {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    /**
     * @return CHTTP_ResponseFactory
     */
    protected function factory() {
        return CHTTP_ResponseFactory::instance();
    }

    /**
     * @return CHTTP_Redirector
     */
    protected function redirector() {
        return CHTTP_Redirector::instance();
    }

    /**
     * Tangkap output yang dialirkan (ob_flush di dalam stream tidak sampai ke ob_get_clean biasa).
     *
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return string
     */
    protected function capture($response) {
        $collected = '';
        ob_start(function ($chunk) use (&$collected) {
            $collected .= $chunk;

            return '';
        });
        $response->sendContent();
        ob_end_flush();

        return $collected;
    }

    public function testMakeAndNoContentSetStatusHeadersAndBody() {
        $response = $this->factory()->make('halo', 201, ['X-Uji' => 'ya']);

        $this->assertSame('halo', $response->getContent());
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('ya', $response->headers->get('X-Uji'));

        $empty = $this->factory()->noContent();
        $this->assertSame(204, $empty->getStatusCode());
        $this->assertSame('', $empty->getContent());
        $this->assertSame(205, $this->factory()->noContent(205)->getStatusCode());
    }

    public function testJsonEncodesDataAndKeepsStatusAndHeaders() {
        $response = $this->factory()->json(['nama' => 'Budi', 'umur' => 30], 202, ['X-Uji' => 'ya']);

        $this->assertInstanceOf(CHTTP_JsonResponse::class, $response);
        $this->assertSame('{"nama":"Budi","umur":30}', $response->getContent());
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('ya', $response->headers->get('X-Uji'));
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function testJsonpWrapsTheDataInTheCallback() {
        $response = $this->factory()->jsonp('panggil', ['a' => 1]);

        $this->assertStringStartsWith('/**/panggil(', $response->getContent());
        $this->assertStringContainsString('{"a":1}', $response->getContent());
    }

    public function testStreamReturnsAStreamedResponseThatRunsTheCallback() {
        $response = $this->factory()->stream(function () {
            echo 'mengalir';
        }, 200, ['X-Uji' => 'ya']);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        ob_start();
        $response->sendContent();
        $this->assertSame('mengalir', ob_get_clean());
        $this->assertSame('ya', $response->headers->get('X-Uji'));
    }

    public function testStreamDownloadSetsAnAttachmentDispositionWithAnAsciiFallback() {
        $response = $this->factory()->streamDownload(function () {
            echo 'x';
        }, 'laporan-é.pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertStringContainsString('filename="laporan-e.pdf"', $disposition);
        $this->assertStringContainsString("filename*=utf-8''laporan-%C3%A9.pdf", $disposition);
    }

    public function testStreamDownloadWithoutANameLeavesTheDispositionUnset() {
        $response = $this->factory()->streamDownload(function () {
        });

        $this->assertFalse($response->headers->has('Content-Disposition'));
    }

    public function testDownloadReturnsABinaryFileResponseWithTheGivenName() {
        $response = $this->factory()->download($this->tempFile, 'unduhan.txt', ['X-Uji' => 'ya']);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame('attachment; filename="unduhan.txt"', $response->headers->get('Content-Disposition'));
        $this->assertSame('ya', $response->headers->get('X-Uji'));
    }

    public function testDownloadCanBeInline() {
        $response = $this->factory()->download($this->tempFile, 'lihat.txt', [], 'inline');

        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
    }

    public function testDownloadWithoutANameUsesTheFileDefault() {
        $response = $this->factory()->download($this->tempFile);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(realpath($this->tempFile), $response->getFile()->getRealPath());
    }

    public function testADownloadNameContainingASlashThrows() {
        $this->expectException(InvalidArgumentException::class);

        $this->factory()->download($this->tempFile, '../rahasia.txt');
    }

    public function testFileReturnsABinaryFileResponseWithoutADisposition() {
        $response = $this->factory()->file($this->tempFile, ['X-Uji' => 'ya']);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertFalse($response->headers->has('Content-Disposition'));
        $this->assertSame('ya', $response->headers->get('X-Uji'));
    }

    public function testRedirectToBuildsARedirectWithStatusAndHeaders() {
        $response = $this->redirector()->to('/tujuan', 301, ['X-Uji' => 'ya']);

        $this->assertInstanceOf(CHTTP_RedirectResponse::class, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringEndsWith('/tujuan', $response->headers->get('Location'));
        $this->assertSame('ya', $response->headers->get('X-Uji'));
    }

    public function testAwayAndAbsoluteUrlsAreUsedAsGiven() {
        $this->assertSame('https://luar.test/a?b=1', $this->redirector()->away('https://luar.test/a?b=1')->headers->get('Location'));
        $this->assertSame('https://luar.test/x', $this->redirector()->to('https://luar.test/x')->headers->get('Location'), 'URL absolut diteruskan apa adanya (open redirect jika berasal dari input)');
    }

    public function testSecureForcesHttps() {
        $this->assertStringStartsWith('https://', $this->redirector()->secure('/aman')->headers->get('Location'));
    }

    public function testBackFollowsTheRefererAndFallsBackOtherwise() {
        $request = CHTTP_Request::create('/x', 'GET', [], [], [], ['HTTP_REFERER' => 'https://contoh.test/sebelumnya']);
        $previous = CHTTP::request();
        CHTTP::setRequest($request);

        try {
            $this->assertSame('https://contoh.test/sebelumnya', $this->redirector()->back()->headers->get('Location'));
        } finally {
            CHTTP::setRequest($previous);
        }
    }

    public function testRedirectHelperWithoutArgumentsReturnsTheRedirector() {
        $this->assertSame($this->redirector(), c::redirect());
        $this->assertSame(302, c::redirect('/ke-sana')->getStatusCode());
    }

    public function testFactoryAndRedirectorAreSingletons() {
        $this->assertSame(CHTTP_ResponseFactory::instance(), CHTTP_ResponseFactory::instance());
        $this->assertSame(CHTTP_Redirector::instance(), CHTTP_Redirector::instance());
    }

    public function testStreamWithAGeneratorFlushesChunksAndDisablesProxyBuffering() {
        $response = $this->factory()->stream(function () {
            yield 'satu';
            yield 'dua';
        }, 200, ['X-Uji' => 'ya']);

        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
        $this->assertSame('ya', $response->headers->get('X-Uji'));
        $this->assertSame('satudua', $this->capture($response));
    }

    public function testStreamWithAPlainCallbackIsLeftUntouched() {
        $response = $this->factory()->stream(function () {
            echo 'biasa';
        });

        $this->assertNull($response->headers->get('X-Accel-Buffering'));
    }

    public function testEventStreamWritesServerSentEventsAndAnEndMarker() {
        $response = $this->factory()->eventStream(function () {
            yield 'halo';
            yield 7;
            yield ['a' => 1];
            yield new CHTTP_StreamedEvent('selesai', 'ok');
        });

        $this->assertSame('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
        $output = $this->capture($response);

        $this->assertSame(
            "event: update\ndata: halo\n\nevent: update\ndata: 7\n\nevent: update\ndata: {\"a\":1}\n\nevent: selesai\ndata: ok\n\nevent: update\ndata: </stream>\n\n",
            $output
        );
    }

    public function testEventStreamEndMarkerCanBeAnEventOrOmitted() {
        $withEvent = $this->factory()->eventStream(function () {
            yield 'x';
        }, [], new CHTTP_StreamedEvent('fin', 'bye'));
        $this->assertStringEndsWith("event: fin\ndata: bye\n\n", $this->capture($withEvent));

        $without = $this->factory()->eventStream(function () {
            yield 'x';
        }, [], null);
        $this->assertSame("event: update\ndata: x\n\n", $this->capture($without));
    }

    public function testViewWithAnArrayOfNamesFailsWhenNoneExists() {
        $this->expectException(InvalidArgumentException::class);

        $this->factory()->view(['tidak.ada.satu', 'tidak.ada.dua']);
    }

    public function testEventStreamEscapesMarkupInEncodedPayloads() {
        $response = $this->factory()->eventStream(function () {
            yield ['html' => '<b>"x" & \'y\'</b>'];
        }, [], null);

        $this->assertSame(
            "event: update\ndata: {\"html\":\"\\u003Cb\\u003E\\u0022x\\u0022 \\u0026 \\u0027y\\u0027\\u003C\\/b\\u003E\"}\n\n",
            $this->capture($response)
        );
    }
}
