<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ApiTestSupport.php';

/**
 * CApi_Manager + CApi + CApi_ExceptionHandler: instance per grup, config grup, komponen lazy,
 * dan rendering exception menjadi respons sesuai error_format.
 */
class ApiManagerTest extends TestCase {
    protected function setUp(): void {
        ApiTestSupport::registerGroup();
    }

    public function testManagerIsASingletonPerGroup() {
        $manager = CApi_Manager::instance(ApiTestSupport::GROUP);

        $this->assertSame($manager, CApi_Manager::instance(ApiTestSupport::GROUP));
        $this->assertSame($manager, c::api(ApiTestSupport::GROUP));
        $this->assertSame($manager, CApi::manager(ApiTestSupport::GROUP));
        $this->assertNotSame($manager, CApi_Manager::instance('grup-lain'));
        $this->assertSame(CApi_Manager::instance(CF::config('api.default')), CApi_Manager::instance(), 'tanpa nama → grup default config');
    }

    public function testGetConfigReadsTheGroupWithDefaults() {
        $manager = c::api(ApiTestSupport::GROUP);

        $this->assertSame(ApiTestSupport::PREFIX, $manager->getConfig('prefix'));
        $this->assertSame('v1', $manager->getConfig('version'));
        $this->assertSame(':code', $manager->getConfig('error_format.errCode'));
        $this->assertSame('bawaan', $manager->getConfig('tidak.ada', 'bawaan'));
        $this->assertSame([], CApi_Manager::instance('grup-tanpa-config')->getConfig('x', []));
    }

    public function testComponentsAreLazyAndCached() {
        $manager = c::api(ApiTestSupport::GROUP);

        $this->assertInstanceOf(CApi_HTTP_Response_Format_JsonFormat::class, $manager->resultFormatter());
        $this->assertSame($manager->resultFormatter(), $manager->resultFormatter());
        $this->assertInstanceOf(CApi_Transformer_Factory::class, $manager->transformer());
        $this->assertInstanceOf(CApi_Transformer_Adapter_FractalAdapter::class, $manager->transformer()->getAdapter());
        $this->assertInstanceOf(CApi_ExceptionHandler::class, $manager->exceptionHandler());
        $this->assertSame($manager->exceptionHandler(), $manager->exceptionHandler());
        $this->assertInstanceOf(CApi_HTTP_Parser_Accept::class, $manager->httpParseAccept());
        $this->assertInstanceOf(CApi_Dispatcher::class, $manager->createDispatcher());
        $this->assertNotSame($manager->createDispatcher(), $manager->createDispatcher(), 'dispatcher selalu baru');
        $this->assertSame([], $manager->getMiddleware());
    }

    public function testMethodResolverCanBeStoredOnTheManager() {
        $manager = c::api(ApiTestSupport::GROUP);
        $this->assertNull($manager->getMethodResolver());

        $resolver = function () {
        };
        $this->assertSame($manager, $manager->setMethodResolver($resolver));
        $this->assertSame($resolver, $manager->getMethodResolver());
        $manager->setMethodResolver(null);
    }

    public function testCurrentDispatcherAndRequestAreStaticHooks() {
        $this->assertNull(CApi::currentDispatcher());
        $dispatcher = c::api(ApiTestSupport::GROUP)->createDispatcher();
        CApi::setCurrentDispatcher($dispatcher);
        $this->assertSame($dispatcher, CApi::currentDispatcher());
        CApi::setCurrentDispatcher(null);
        $this->assertNull(CApi::currentDispatcher());

        $request = CApi_HTTP_Request::createFromBaseHttp(CHTTP_Request::create('/'));
        CApi::setRequest($request);
        $this->assertSame($request, CApi::request());
    }

    /**
     * @param array $format
     * @param bool  $debug
     *
     * @return CApi_ExceptionHandler
     */
    private function handler(array $format = null, $debug = false) {
        return new CApi_ExceptionHandler($format ?: [
            'errCode' => ':code',
            'errMessage' => ':message',
            'data' => ['message' => ':message', 'errors' => ':errors', 'code' => ':code', 'status_code' => ':status_code', 'debug' => ':debug'],
        ], $debug);
    }

    /**
     * @param CApi_ExceptionHandler $handler
     * @param Throwable             $e
     *
     * @return array
     */
    private function rendered(CApi_ExceptionHandler $handler, $e) {
        $response = $handler->handle(CHTTP_Request::create('/api/x', 'GET'), $e);

        return [$response->getStatusCode(), $response->getOriginalContent(), $response];
    }

    public function testGenericExceptionIs500WithTheStatusAsCode() {
        list($status, $body) = $this->rendered($this->handler(), new RuntimeException('rusak'));

        $this->assertSame(500, $status);
        $this->assertSame(['errCode' => 500, 'errMessage' => 'rusak', 'data' => ['message' => 'rusak', 'code' => 500, 'status_code' => 500]], $body);
    }

    public function testExceptionCodeWinsOverTheStatusWhenSet() {
        list(, $body) = $this->rendered($this->handler(), new RuntimeException('rusak', 77));

        $this->assertSame(77, $body['errCode']);
        $this->assertSame(77, $body['data']['code']);
        $this->assertSame(500, $body['data']['status_code']);
    }

    public function testApiExceptionContractSuppliesTheErrCode() {
        list($status, $body) = $this->rendered($this->handler(), new ApiTestApiException('khusus', 5));

        $this->assertSame(500, $status);
        $this->assertSame(4321, $body['errCode']);
    }

    public function testAnEmptyMessageFallsBackToTheStatusText() {
        list(, $body) = $this->rendered($this->handler(), new Symfony\Component\HttpKernel\Exception\NotFoundHttpException(''));

        $this->assertSame('404 Not Found', $body['errMessage']);
        $this->assertSame(404, $body['errCode']);
    }

    public function testModelNotFoundAndApiMethodNotFoundBecome404() {
        list($status, , $response) = $this->rendered($this->handler(), new CApi_Exception_ApiMethodNotFoundException('x is not found'));
        $this->assertSame(404, $status);
        $this->assertInstanceOf(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class, $response->exception);

        list($status) = $this->rendered($this->handler(), new CModel_Exception_ModelNotFoundException('hilang'));
        $this->assertSame(404, $status);
    }

    public function testAuthenticationExceptionIs401() {
        list($status, $body) = $this->rendered($this->handler(), new CAuth_Exception_AuthenticationException());

        $this->assertSame(401, $status);
        $this->assertSame('Unauthenticated.', $body['errMessage']);
        $this->assertSame(401, $body['data']['status_code']);
    }

    public function testValidationExceptionCarriesTheErrorsAndStatus422() {
        $validator = CValidation::createValidator(['email' => 'x'], ['email' => 'required|email', 'name' => 'required']);
        $validator->fails();
        $exception = new CValidation_Exception($validator);

        list($status, $body) = $this->rendered($this->handler(), $exception);

        $this->assertSame(422, $status);
        $this->assertSame(422, $body['errCode']);
        $this->assertSame(['email', 'name'], array_column($body['data']['errors'], 'key'));
        $this->assertIsArray($body['data']['errors'][0]['messages']);
    }

    public function testDebugModeAddsFileLineClassAndTrace() {
        $previous = new LogicException('akar');
        list(, $body) = $this->rendered($this->handler(null, true), new RuntimeException('luar', 0, $previous));

        $debug = $body['data']['debug'];
        $this->assertSame(RuntimeException::class, $debug['class']);
        $this->assertSame(__FILE__, $debug['file']);
        $this->assertIsInt($debug['line']);
        $this->assertArrayHasKey('previous', $debug['trace'], 'trace exception sebelumnya ikut');
        $this->assertArrayHasKey('current', $debug['trace']);

        list(, $body) = $this->rendered($this->handler(null, true), new RuntimeException('tanpa previous'));
        $this->assertIsArray($body['data']['debug']['trace']);
        $this->assertArrayNotHasKey('previous', $body['data']['debug']['trace']);
    }

    public function testCustomErrorFormatAndReplacements() {
        $handler = $this->handler(['ok' => false, 'error' => ':message', 'kode' => ':code', 'ekstra' => ':custom', 'kosong' => ':errors'], false);
        $handler->setReplacements([':custom' => 'nilai']);

        list(, $body) = $this->rendered($handler, new RuntimeException('salah', 3));

        $this->assertSame(['ok' => false, 'error' => 'salah', 'kode' => 3, 'ekstra' => 'nilai'], $body, 'placeholder tanpa nilai dibuang, nilai non-placeholder dipertahankan');

        $handler->setErrorFormat(['pesan' => ':message']);
        list(, $body) = $this->rendered($handler, new RuntimeException('lagi'));
        $this->assertSame(['pesan' => 'lagi'], $body);
    }

    public function testRegisteredHandlersAreMatchedByTheirTypeHint() {
        $handler = $this->handler();
        $handler->register(function (LogicException $e) {
            return ['custom' => $e->getMessage()];
        });
        $handler->register(function (RangeException $e) {
            return new CHTTP_Response('argumen', 400);
        });

        $this->assertSame([LogicException::class, RangeException::class], array_keys($handler->getHandlers()));

        list($status, $body, $response) = $this->rendered($handler, new LogicException('logika'));
        $this->assertSame(500, $status, 'respons array dibungkus dengan status default exception');
        $this->assertSame(['custom' => 'logika'], $body);
        $this->assertInstanceOf(LogicException::class, $response->exception);

        //DomainException adalah LogicException → handler yang sama
        list(, $body) = $this->rendered($handler, new DomainException('domain'));
        $this->assertSame(['custom' => 'domain'], $body);

        list($status, , $response) = $this->rendered($handler, new RangeException('arg'));
        $this->assertSame(400, $status);
        $this->assertSame('argumen', $response->getContent());

        //handler dicocokkan berurutan sesuai pendaftaran: yang pertama cocok menang
        $handler->register(function (InvalidArgumentException $e) {
            return ['never' => true];
        });
        list(, $body) = $this->rendered($handler, new InvalidArgumentException('arg'));
        $this->assertSame(['custom' => 'arg'], $body, 'InvalidArgumentException adalah LogicException, handler pertama yang cocok');

        list(, $body) = $this->rendered($handler, new RuntimeException('bukan logic'));
        $this->assertSame('bukan logic', $body['errMessage'], 'tanpa handler cocok → generic');
    }

    public function testAHandlerReturningNothingFallsThroughToTheGenericResponse() {
        $handler = $this->handler();
        $handler->register(function (RuntimeException $e) {
            return null;
        });

        list($status, $body) = $this->rendered($handler, new RuntimeException('lewat'));

        $this->assertSame(500, $status);
        $this->assertSame('lewat', $body['errMessage']);
    }

    public function testHttpExceptionHeadersArePassedOn() {
        list($status, , $response) = $this->rendered($this->handler(), new Symfony\Component\HttpKernel\Exception\HttpException(429, 'pelan', null, ['Retry-After' => '30']));

        $this->assertSame(429, $status);
        $this->assertSame('30', $response->headers->get('Retry-After'));
    }

    public function testRateLimitExceededComputesRetryAfter() {
        $e = new CApi_Exception_RateLimitExceededException(null, null, ['X-RateLimit-Reset' => time() + 60]);

        $this->assertSame(429, $e->getStatusCode());
        $this->assertSame('You have exceeded your rate limit.', $e->getMessage());
        $this->assertEqualsWithDelta(60, $e->getHeaders()['Retry-After'], 2);
    }

    public function testInternalHttpExceptionWrapsAResponse() {
        $response = new CHTTP_Response('isi', 403);
        $e = new CApi_Exception_InternalHttpException($response, 'internal');

        $this->assertSame(403, $e->getStatusCode());
        $this->assertSame($response, $e->getResponse());
        $this->assertSame(400, (new CApi_Exception_ApiMethodNotFoundException('x'))->getStatusCode(), 'sebelum dipetakan handler menjadi 404');
    }

    public function testOutOfRangeStatusCodesFallBackTo500() {
        list($status) = $this->rendered($this->handler(), new Symfony\Component\HttpKernel\Exception\HttpException(999, 'aneh'));

        $this->assertSame(500, $status);
    }
}
