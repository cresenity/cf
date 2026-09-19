<?php
/**
 * CTesting_Concern_MakesHttpRequests: pembentukan header/cookie/URL sebelum request dikirim,
 * dan saklar middleware CHTTP/CApi_Manager yang dipakai withoutMiddleware().
 */
class MakesHttpRequestsTest extends CTesting_TestCase {
    /**
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    protected function invoke($method, ...$args) {
        $reflection = new ReflectionMethod($this, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this, $args);
    }

    /**
     * @param string $property
     *
     * @return mixed
     */
    protected function prop($property) {
        $reflection = new ReflectionProperty(CTesting_TestCase::class, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($this);
    }

    protected function tearDown(): void {
        $this->withMiddleware();
        parent::tearDown();
    }

    public function testHeadersBecomeHttpServerVariables() {
        $this->withHeaders(['X-Uji' => 'a', 'Accept' => 'application/json'])->withHeader('X-Lain', 'b')->withToken('rahasia');
        $server = $this->invoke('transformHeadersToServerVars', ['Content-Type' => 'text/plain', 'Remote-Addr' => '10.0.0.1']);
        $this->assertSame('a', $server['HTTP_X_UJI']);
        $this->assertSame('application/json', $server['HTTP_ACCEPT']);
        $this->assertSame('b', $server['HTTP_X_LAIN']);
        $this->assertSame('Bearer rahasia', $server['HTTP_AUTHORIZATION']);
        $this->assertSame('text/plain', $server['CONTENT_TYPE'], 'CONTENT_TYPE tanpa awalan HTTP_');
        $this->assertSame('10.0.0.1', $server['REMOTE_ADDR']);
        $this->flushHeaders();
        $this->assertSame([], $this->invoke('transformHeadersToServerVars', []));
    }

    public function testWithTokenTypeAndFormatServerHeaderKey() {
        $this->withToken('abc', 'Basic');
        $this->assertSame('Basic abc', $this->prop('defaultHeaders')['Authorization']);
        $this->assertSame('HTTP_X_FOO', $this->invoke('formatServerHeaderKey', 'X_FOO'));
        $this->assertSame('HTTP_X_FOO', $this->invoke('formatServerHeaderKey', 'HTTP_X_FOO'));
        $this->assertSame('CONTENT_TYPE', $this->invoke('formatServerHeaderKey', 'CONTENT_TYPE'));
        $this->flushHeaders();
    }

    public function testPrepareUrlUsesTheCliDomain() {
        $this->assertSame('http://' . CConsole::domain() . '/masuk', $this->invoke('prepareUrlForRequest', '/masuk'));
        $this->assertSame('http://' . CConsole::domain() . '/api/x?y=1', $this->invoke('prepareUrlForRequest', 'api/x?y=1'));
    }

    public function testCookiesAreEncryptedUnlessDisabled() {
        $this->withCookies(['a' => '1'])->withCookie('b', '2')->withUnencryptedCookie('c', '3');
        $cookies = $this->invoke('prepareCookiesForRequest');
        $this->assertSame(['a', 'b', 'c'], array_keys($cookies));
        $this->assertSame('3', $cookies['c'], 'cookie tanpa enkripsi apa adanya');
        $this->assertNotSame('1', $cookies['a']);
        $decrypted = c::decrypt($cookies['a'], false);
        $this->assertStringEndsWith('1', $decrypted);
        $this->assertSame('1', CHTTP_Cookie_CookieValuePrefix::remove($decrypted));

        $this->disableCookieEncryption();
        $this->assertSame(['a' => '1', 'b' => '2', 'c' => '3'], $this->invoke('prepareCookiesForRequest'));
        $this->assertSame([], $this->invoke('prepareCookiesForJsonRequest'), 'request JSON tanpa withCredentials() tidak membawa cookie');
        $this->withCredentials();
        $this->assertSame(['a' => '1', 'b' => '2', 'c' => '3'], $this->invoke('prepareCookiesForJsonRequest'));
    }

    public function testFilesAreExtractedFromNestedData() {
        $file = CHTTP_UploadedFile::fake()->create('a.txt', 1);
        $data = ['nama' => 'x', 'lampiran' => $file, 'nested' => ['dalam' => $file, 'teks' => 'y']];
        $files = $this->extractFilesFromDataArray($data);
        $this->assertSame(['nama' => 'x', 'nested' => ['teks' => 'y']], $data, 'berkas dicabut dari data');
        $this->assertSame($file, $files['lampiran']);
        $this->assertSame($file, $files['nested']['dalam']);
    }

    public function testWithoutMiddlewareTogglesTheGlobalSwitches() {
        $this->assertFalse(CHTTP::shouldSkipMiddleware());
        $this->withoutMiddleware();
        $this->assertTrue(CHTTP::shouldSkipMiddleware(), 'CHTTP::shouldSkipMiddleware() adalah saklar yang benar-benar dibaca kernel');
        $this->assertTrue(CApi_Manager::instance('uji')->shouldSkipMiddleware(), 'semua grup API ikut dimatikan');
        $this->withMiddleware();
        $this->assertFalse(CHTTP::shouldSkipMiddleware());
        $this->assertFalse(CApi_Manager::instance('uji')->shouldSkipMiddleware());
    }

    public function testWithoutSpecificMiddlewareBindsAPassThrough() {
        $this->withoutMiddleware('UjiMiddleware_Tidak_Ada');
        $stub = $this->app->make('UjiMiddleware_Tidak_Ada');
        $this->assertSame('lewat', $stub->handle('req', function ($request) {
            return 'lewat';
        }));
        $this->withMiddleware('UjiMiddleware_Tidak_Ada');
        $this->assertFalse($this->app->bound('UjiMiddleware_Tidak_Ada'));
    }

    public function testFollowingRedirectsFlag() {
        $this->assertFalse($this->prop('followRedirects'));
        $this->followingRedirects();
        $this->assertTrue($this->prop('followRedirects'));
    }

    public function testCreateTestResponseWrapsBaseResponse() {
        $response = $this->invoke('createTestResponse', new CHTTP_Response('halo', 201));
        $this->assertInstanceOf(CTesting_TestResponse::class, $response);
        $response->assertCreated()->assertSee('halo');
    }
}
