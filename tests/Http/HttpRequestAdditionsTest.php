<?php

use PHPUnit\Framework\TestCase;

/**
 * Tambahan Request: host(), httpHost(), schemeAndHttpHost(), fullUrlWithoutQuery(), mergeIfMissing(),
 * getAcceptableContentTypes() yang mengikuti perubahan header Accept, wantsMarkdown()/acceptsMarkdown(),
 * dan bearerToken() yang tidak peka huruf besar/kecil.
 */
class HttpRequestAdditionsTest extends TestCase {
    protected function request($uri = 'https://contoh.test:8443/halaman?a=1&b=2&c=3', array $server = []) {
        return CHTTP_Request::create($uri, 'GET', [], [], [], $server);
    }

    public function testHostHelpersMirrorTheSymfonyGetters() {
        $request = $this->request();

        $this->assertSame('contoh.test', $request->host());
        $this->assertSame('contoh.test:8443', $request->httpHost());
        $this->assertSame('https://contoh.test:8443', $request->schemeAndHttpHost());
    }

    public function testFullUrlWithoutQueryDropsTheGivenKeys() {
        $request = $this->request();

        $this->assertSame('https://contoh.test:8443/halaman?a=1&c=3', $request->fullUrlWithoutQuery('b'));
        $this->assertSame('https://contoh.test:8443/halaman?c=3', $request->fullUrlWithoutQuery(['a', 'b']));
        $this->assertSame('https://contoh.test:8443/halaman', $request->fullUrlWithoutQuery(['a', 'b', 'c']), 'tanpa sisa query tidak ada tanda tanya');
        $this->assertSame('https://contoh.test:8443/halaman?a=1&b=2&c=3', $request->fullUrlWithoutQuery('tidak-ada'));
    }

    public function testMergeIfMissingOnlyAddsKeysThatAreAbsent() {
        $request = CHTTP_Request::create('/uji', 'POST', ['nama' => 'Budi', 'kosong' => '']);

        $this->assertSame($request, $request->mergeIfMissing(['nama' => 'Lain', 'kota' => 'Bandung', 'kosong' => 'diisi']));

        $this->assertSame('Budi', $request->input('nama'), 'kunci yang sudah ada tidak ditimpa');
        $this->assertSame('Bandung', $request->input('kota'));
        $this->assertSame('', $request->input('kosong'), 'string kosong tetap dianggap ada');
    }

    public function testAcceptableContentTypesFollowAChangedAcceptHeader() {
        $request = $this->request('https://contoh.test/x', ['HTTP_ACCEPT' => 'text/html']);
        $this->assertSame(['text/html'], $request->getAcceptableContentTypes());

        $request->headers->set('Accept', 'application/json');

        $this->assertSame(['application/json'], $request->getAcceptableContentTypes());
        $this->assertTrue($request->wantsJson());
    }

    public function testMarkdownNegotiation() {
        $markdown = $this->request('https://contoh.test/x', ['HTTP_ACCEPT' => 'text/markdown, text/html;q=0.8']);
        $this->assertTrue($markdown->wantsMarkdown());
        $this->assertTrue($markdown->acceptsMarkdown());

        $html = $this->request('https://contoh.test/x', ['HTTP_ACCEPT' => 'text/html, text/markdown;q=0.5']);
        $this->assertFalse($html->wantsMarkdown(), 'markdown bukan pilihan pertama');
        $this->assertTrue($html->acceptsMarkdown());

        $none = $this->request('https://contoh.test/x', ['HTTP_ACCEPT' => 'application/json']);
        $this->assertFalse($none->wantsMarkdown());
        $this->assertFalse($none->acceptsMarkdown());
    }

    public function testBearerTokenIsReadRegardlessOfTheSchemeCase() {
        foreach (['Bearer token-1', 'bearer token-1', 'BEARER token-1'] as $header) {
            $this->assertSame('token-1', $this->request('https://contoh.test/x', ['HTTP_AUTHORIZATION' => $header])->bearerToken(), $header);
        }

        $this->assertSame('token-2', $this->request('https://contoh.test/x', ['HTTP_AUTHORIZATION' => 'bearer token-2, extra'])->bearerToken());
        $this->assertNull($this->request('https://contoh.test/x', ['HTTP_AUTHORIZATION' => 'Basic abc'])->bearerToken());
        $this->assertNull($this->request()->bearerToken());
    }
}
