<?php

use PHPUnit\Framework\TestCase;

/**
 * CHTTP_Cors_CorsService: tes karakterisasi keputusan origin/method/header dan header respons.
 */
class HttpCorsServiceTest extends TestCase {
    /**
     * @param array  $options
     * @param string $method
     * @param array  $server
     *
     * @return array [CHTTP_Cors_CorsService, CHTTP_Request]
     */
    protected function service(array $options) {
        return new CHTTP_Cors_CorsService($options);
    }

    protected function request($method = 'GET', $origin = 'https://asal.test', array $server = []) {
        $server = $origin === null ? $server : array_merge(['HTTP_ORIGIN' => $origin], $server);

        return CHTTP_Request::create('https://api.test/data', $method, [], [], [], $server);
    }

    public function testRequestsWithAnOriginDifferentFromTheHostAreCorsRequests() {
        $service = $this->service([]);

        $this->assertTrue($service->isCorsRequest($this->request()));
        $this->assertFalse($service->isCorsRequest($this->request('GET', null)));
    }

    public function testPreflightIsAnOptionsRequestWithAnAccessControlRequestMethod() {
        $service = $this->service([]);

        $this->assertTrue($service->isPreflightRequest($this->request('OPTIONS', 'https://asal.test', ['HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST'])));
        $this->assertFalse($service->isPreflightRequest($this->request('OPTIONS')));
        $this->assertFalse($service->isPreflightRequest($this->request('GET', 'https://asal.test', ['HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST'])));
    }

    public function testOriginsAreMatchedExactlyByPatternOrByWildcard() {
        $exact = $this->service(['allowedOrigins' => ['https://asal.test']]);
        $this->assertTrue($exact->isOriginAllowed($this->request()));
        $this->assertFalse($exact->isOriginAllowed($this->request('GET', 'https://lain.test')));

        $pattern = $this->service(['allowedOrigins' => [], 'allowedOriginsPatterns' => ['#^https://.*\.contoh\.test$#']]);
        $this->assertTrue($pattern->isOriginAllowed($this->request('GET', 'https://a.contoh.test')));
        $this->assertFalse($pattern->isOriginAllowed($this->request('GET', 'https://contoh.test.jahat.test')));

        $all = $this->service(['allowedOrigins' => ['*']]);
        $this->assertTrue($all->isOriginAllowed($this->request('GET', 'https://siapa-saja.test')));
    }

    public function testASingleAllowedOriginIsAdvertisedRegardlessOfTheRequestOrigin() {
        $service = $this->service(['allowedOrigins' => ['https://asal.test'], 'exposedHeaders' => ['X-Total']]);

        $allowed = $service->addActualRequestHeaders(new CHTTP_Response('ok'), $this->request());
        $this->assertSame('https://asal.test', $allowed->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('X-Total', $allowed->headers->get('Access-Control-Expose-Headers'));

        $other = $service->addActualRequestHeaders(new CHTTP_Response('ok'), $this->request('GET', 'https://lain.test'));
        $this->assertSame('https://asal.test', $other->headers->get('Access-Control-Allow-Origin'), 'browser yang menolak karena tidak cocok, bukan server');
    }

    public function testSeveralAllowedOriginsEchoOnlyTheMatchingRequestOrigin() {
        $service = $this->service(['allowedOrigins' => ['https://a.test', 'https://b.test']]);

        $match = $service->addActualRequestHeaders(new CHTTP_Response('ok'), $this->request('GET', 'https://b.test'));
        $this->assertSame('https://b.test', $match->headers->get('Access-Control-Allow-Origin'));

        $other = $service->addActualRequestHeaders(new CHTTP_Response('ok'), $this->request('GET', 'https://lain.test'));
        $this->assertNull($other->headers->get('Access-Control-Allow-Origin'));
    }

    public function testWildcardOriginWithoutCredentialsSendsAStar() {
        $service = $this->service(['allowedOrigins' => ['*']]);

        $response = $service->addActualRequestHeaders(new CHTTP_Response('ok'), $this->request());

        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testCredentialsAreAdvertisedWhenSupported() {
        $service = $this->service(['allowedOrigins' => ['https://asal.test'], 'supportsCredentials' => true]);

        $response = $service->addActualRequestHeaders(new CHTTP_Response('ok'), $this->request());

        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function testPreflightAnswersWithAllowedMethodsHeadersAndMaxAge() {
        $service = $this->service([
            'allowedOrigins' => ['https://asal.test'],
            'allowedMethods' => ['get', 'post'],
            'allowedHeaders' => ['X-Token'],
            'maxAge' => 600,
        ]);
        $request = $this->request('OPTIONS', 'https://asal.test', [
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'X-Token',
        ]);

        $response = $service->handlePreflightRequest($request);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('https://asal.test', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('x-token', strtolower($response->headers->get('Access-Control-Allow-Headers')));
        $this->assertSame('600', $response->headers->get('Access-Control-Max-Age'));
    }

    public function testPreflightIsAlwaysAnsweredWith204AndListsOnlyTheAllowedMethods() {
        $service = $this->service(['allowedOrigins' => ['https://asal.test'], 'allowedMethods' => ['GET']]);
        $request = $this->request('OPTIONS', 'https://asal.test', ['HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'DELETE']);

        $response = $service->handlePreflightRequest($request);

        $this->assertSame(204, $response->getStatusCode(), 'server tidak menolak; browser membandingkan daftar method');
        $this->assertSame('GET', $response->headers->get('Access-Control-Allow-Methods'));
    }

    public function testVaryHeaderIsAppendedWithoutDuplicates() {
        $service = $this->service([]);
        $response = new CHTTP_Response('ok');

        $service->varyHeader($response, 'Origin');
        $service->varyHeader($response, 'Origin');
        $service->varyHeader($response, 'Access-Control-Request-Method');

        $this->assertSame('Origin, Access-Control-Request-Method', $response->headers->get('Vary'));
    }
}
