<?php

use PHPUnit\Framework\TestCase;

/**
 * CHTTP_Middleware_HandleCors: path yang dilewatkan ke alur CORS dibaca dari config http.cors.paths
 * (kunci lama http.cors.path tetap dihormati), atau dari properti $paths milik subclass.
 */
class HttpCorsMiddlewareForTest extends CHTTP_Middleware_HandleCors {
    public function setPaths($paths) {
        $this->paths = $paths;
    }

    public function matches(CHTTP_Request $request) {
        return $this->shouldRun($request);
    }
}

class HttpCorsMiddlewareTest extends TestCase {
    protected $original = [];

    protected function setUp(): void {
        parent::setUp();
        foreach (['http.cors.paths', 'http.cors.path'] as $key) {
            $this->original[$key] = CConfig::repository()->get($key);
        }
    }

    protected function tearDown(): void {
        foreach ($this->original as $key => $value) {
            CConfig::repository()->set($key, $value);
        }
        parent::tearDown();
    }

    protected function request($path) {
        return CHTTP_Request::create('https://contoh.test/' . $path, 'GET');
    }

    public function testPathsFromTheConfigPathsKeyEnableCors() {
        CConfig::repository()->set('http.cors.paths', ['api/*']);
        CConfig::repository()->set('http.cors.path', []);
        $middleware = new HttpCorsMiddlewareForTest();

        $this->assertTrue($middleware->matches($this->request('api/pengguna')));
        $this->assertFalse($middleware->matches($this->request('halaman/biasa')));
    }

    public function testLegacyPathKeyIsStillHonoredWhenPathsIsEmpty() {
        CConfig::repository()->set('http.cors.paths', []);
        CConfig::repository()->set('http.cors.path', ['lama/*']);
        $middleware = new HttpCorsMiddlewareForTest();

        $this->assertTrue($middleware->matches($this->request('lama/x')));
        $this->assertFalse($middleware->matches($this->request('api/x')));
    }

    public function testNoConfiguredPathsMeansCorsDoesNotRun() {
        CConfig::repository()->set('http.cors.paths', []);
        CConfig::repository()->set('http.cors.path', []);

        $this->assertFalse((new HttpCorsMiddlewareForTest())->matches($this->request('api/x')));
    }

    public function testPathsPropertyOfASubclassWinsOverConfig() {
        CConfig::repository()->set('http.cors.paths', ['api/*']);
        $middleware = new HttpCorsMiddlewareForTest();
        $middleware->setPaths(['khusus/*']);

        $this->assertTrue($middleware->matches($this->request('khusus/x')));
        $this->assertFalse($middleware->matches($this->request('api/x')));
    }
}
