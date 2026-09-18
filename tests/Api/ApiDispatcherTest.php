<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ApiTestSupport.php';

/**
 * CApi_Dispatcher + CApi_Kernel - jalur lengkap dari request HTTP ke kelas Method: resolusi
 * nama kelas dari segmen URL, pemilihan method per verb, bentuk respons JSON, middleware,
 * dan rendering exception lewat CApi_ExceptionHandler grup.
 */
class ApiDispatcherTest extends TestCase {
    protected function setUp(): void {
        ApiTestSupport::registerGroup();
        c::api(ApiTestSupport::GROUP)->withMiddleware();
        CApi_Manager::withMiddlewareForAllGroups();
    }

    /**
     * @param string $uri
     * @param string $method
     * @param array  $parameters
     * @param array  $server
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    private function dispatch($uri, $method = 'POST', array $parameters = [], array $server = []) {
        return ApiTestSupport::dispatcher()->dispatch(ApiTestSupport::request($uri, $method, $parameters, $server));
    }

    public function testASimpleMethodIsResolvedFromTheUrlAndAnsweredAsJson() {
        $response = $this->dispatch('api/test/ping', 'POST', ['echo' => 'halo']);

        $this->assertInstanceOf(CApi_HTTP_Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame([
            'errCode' => 0,
            'errMessage' => '',
            'data' => ['pong' => true, 'echo' => 'halo'],
        ], ApiTestSupport::json($response));
    }

    public function testUrlSegmentsAreJoinedOneToOneIntoTheClassName() {
        //api/test/member/profile → ApiTestMethod_Member_Profile; tiap segmen jadi satu bagian nama
        $response = $this->dispatch('api/test/member/profile');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hery', ApiTestSupport::json($response)['data']['name']);
    }

    public function testSegmentsAreCamelCasedAndUcfirsted() {
        //member-profile / member_profile → MemberProfile: bukan kelas yang ada → 404
        $this->assertSame(404, $this->dispatch('api/test/member-profile')->getStatusCode());

        //huruf kecil semua tetap dipetakan ke Member_Profile
        $this->assertSame(200, $this->dispatch('api/test/MEMBER/PROFILE')->getStatusCode(), 'ucfirst(camel()) menormalkan huruf besar');
    }

    public function testThereIsNoImplicitIndexForABareSegment() {
        //api/test/member → ApiTestMethod_Member (tidak ada); Member_Profile hanya untuk /member/profile
        $response = $this->dispatch('api/test/member');

        $this->assertSame(404, $response->getStatusCode());
        $json = ApiTestSupport::json($response);
        $this->assertSame(404, $json['errCode']);
        $this->assertSame('api/test/member is not found', $json['errMessage']);
        $this->assertSame(404, $json['data']['status_code']);
    }

    public function testAnUnknownMethodIsANotFoundJsonResponse() {
        $response = $this->dispatch('api/test/tidak/ada', 'GET');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame(404, ApiTestSupport::json($response)['errCode']);
        $this->assertInstanceOf(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class, $response->exception);
        $this->assertInstanceOf(CApi_Exception_ApiMethodNotFoundException::class, $response->exception->getPrevious());
    }

    public function testThePrefixIsStrippedBeforeResolvingAndTrailingSlashesAreIgnored() {
        $this->assertSame(200, $this->dispatch('/api/test/ping/')->getStatusCode());

        //tanpa prefix, seluruh path dipakai → ApiTestMethod_Api_Test_Ping tidak ada
        $dispatcher = c::api(ApiTestSupport::GROUP)->createDispatcher()->setPrefix('')->setMethodNamespace('ApiTestMethod');
        $this->assertSame(404, $dispatcher->dispatch(ApiTestSupport::request('api/test/ping'))->getStatusCode());
        $this->assertSame(200, $dispatcher->dispatch(ApiTestSupport::request('ping'))->getStatusCode());
    }

    public function testSetPrefixTrimsSlashesAndGetPrefixReturnsIt() {
        $dispatcher = c::api(ApiTestSupport::GROUP)->createDispatcher();
        $this->assertSame(ApiTestSupport::PREFIX, $dispatcher->getPrefix(), 'prefix awal dari config grup');

        $this->assertSame($dispatcher, $dispatcher->setPrefix('/v2/api/'));
        $this->assertSame('v2/api', $dispatcher->getPrefix());
        $this->assertSame(ApiTestSupport::GROUP, $dispatcher->getGroup());
    }

    public function testTheHttpVerbPicksTheMethodBeforeExecute() {
        foreach (['GET' => 'get', 'POST' => 'post', 'PUT' => 'put', 'DELETE' => 'delete', 'PATCH' => 'patch'] as $verb => $expected) {
            $response = $this->dispatch('api/test/verbs', $verb);
            $this->assertSame($expected, ApiTestSupport::json($response)['data']['via'], $verb);
        }

        //verb tanpa method khusus → execute()
        $this->assertSame('execute', ApiTestSupport::json($this->dispatch('api/test/verbs', 'OPTIONS'))['data']['via']);
        //method tanpa verb khusus → execute() apa pun verb-nya
        $this->assertTrue(ApiTestSupport::json($this->dispatch('api/test/ping', 'GET'))['data']['pong']);
    }

    public function testFailKeepsHttp200AndReportsThroughErrCode() {
        $response = $this->dispatch('api/test/fail', 'POST', ['code' => 7]);

        $this->assertSame(200, $response->getStatusCode(), 'kegagalan bisnis bukan kegagalan HTTP');
        $this->assertSame([
            'errCode' => 7,
            'errMessage' => 'gagal terkendali',
            'data' => ['kept' => true],
        ], ApiTestSupport::json($response));
    }

    public function testAMethodMayReturnItsOwnResponse() {
        $response = $this->dispatch('api/test/raw');

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('mentah', $response->getContent());
        $this->assertSame('1', $response->headers->get('X-Raw'));
    }

    public function testAnUncaughtExceptionBecomesA500WithDebugInfo() {
        $response = $this->dispatch('api/test/boom');

        $this->assertSame(500, $response->getStatusCode());
        $json = ApiTestSupport::json($response);
        $this->assertSame(42, $json['errCode'], 'getCode() exception dipakai bila ada');
        $this->assertSame('meledak', $json['errMessage']);
        $this->assertSame(500, $json['data']['status_code']);
        $this->assertSame(RuntimeException::class, $json['data']['debug']['class']);
        $this->assertArrayHasKey('trace', $json['data']['debug']);
        $this->assertInstanceOf(RuntimeException::class, $response->exception);
    }

    public function testDebugInfoIsOmittedWhenTheGroupIsNotInDebug() {
        ApiTestSupport::registerGroup(['debug' => false]);
        $manager = c::api(ApiTestSupport::GROUP);
        $manager->exceptionHandler()->setDebug(false);

        try {
            $json = ApiTestSupport::json($this->dispatch('api/test/boom'));
            $this->assertArrayNotHasKey('debug', $json['data']);
            $this->assertArrayNotHasKey('errors', $json['data'], 'placeholder tanpa nilai dibuang');
            $this->assertSame(['message', 'code', 'status_code'], array_keys($json['data']));
        } finally {
            $manager->exceptionHandler()->setDebug(true);
        }
    }

    public function testAnHttpExceptionKeepsItsStatusAndHeaders() {
        $response = $this->dispatch('api/test/teapot');

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame('yes', $response->headers->get('X-Teapot'));
        $json = ApiTestSupport::json($response);
        $this->assertSame(418, $json['errCode'], 'tanpa getCode(), status HTTP jadi errCode');
        $this->assertSame('saya teko', $json['errMessage']);
    }

    public function testValidationFailureIs422WithTheErrorList() {
        $response = $this->dispatch('api/test/invalid', 'POST', ['email' => 'bukan-email', 'age' => 12]);

        $this->assertSame(422, $response->getStatusCode());
        $json = ApiTestSupport::json($response);
        $this->assertSame(422, $json['errCode']);
        $this->assertSame(422, $json['data']['status_code']);
        $this->assertSame(['email', 'age'], array_column($json['data']['errors'], 'key'));
        $this->assertNotEmpty($json['data']['errors'][0]['messages']);

        $this->assertSame(200, $this->dispatch('api/test/invalid', 'POST', ['email' => 'a@b.co', 'age' => 20])->getStatusCode());
    }

    public function testAuthenticationExceptionIs401() {
        $response = $this->dispatch('api/test/unauthorized');

        $this->assertSame(401, $response->getStatusCode());
        $json = ApiTestSupport::json($response);
        $this->assertSame(401, $json['errCode']);
        $this->assertSame('harus login', $json['errMessage']);
    }

    public function testMethodMiddlewareRunsAroundTheMethodAndReceivesTheGroup() {
        ApiTestMiddleware_Stamp::$group = null;

        $response = $this->dispatch('api/test/guarded');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(ApiTestSupport::json($response)['data']['reached']);
        $this->assertSame('stamped', $response->headers->get('X-Stamp'));
        $this->assertSame(ApiTestSupport::GROUP, ApiTestMiddleware_Stamp::$group, 'middleware ber-ApiGroupMiddlewareInterface diberi grup');

        $response = $this->dispatch('api/test/guarded', 'POST', ['gate' => 'closed']);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('ditolak middleware', $response->getContent());
        $this->assertSame('stamped', $response->headers->get('X-Stamp'), 'middleware luar tetap membungkus respons middleware dalam');
    }

    public function testMiddlewareCanBeSkippedPerGroupOrGlobally() {
        c::api(ApiTestSupport::GROUP)->withoutMiddleware();
        $this->assertTrue(c::api(ApiTestSupport::GROUP)->shouldSkipMiddleware());
        $response = $this->dispatch('api/test/guarded', 'POST', ['gate' => 'closed']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('X-Stamp'));

        c::api(ApiTestSupport::GROUP)->withMiddleware();
        $this->assertFalse(c::api(ApiTestSupport::GROUP)->shouldSkipMiddleware());

        CApi_Manager::withoutMiddlewareForAllGroups();
        $this->assertTrue(c::api(ApiTestSupport::GROUP)->shouldSkipMiddleware());
        $this->assertSame(200, $this->dispatch('api/test/guarded', 'POST', ['gate' => 'closed'])->getStatusCode());
        CApi_Manager::withMiddlewareForAllGroups();
        $this->assertFalse(c::api(ApiTestSupport::GROUP)->shouldSkipMiddleware());
    }

    public function testDispatchMethodBypassesUrlResolution() {
        $dispatcher = ApiTestSupport::dispatcher();

        $response = $dispatcher->dispatchMethod(ApiTestMethod_Ping::class, ApiTestSupport::request('apa/saja', 'POST', ['echo' => 'x']));
        $this->assertSame('x', ApiTestSupport::json($response)['data']['echo']);

        //array mentah diterima sebagai body request
        $response = $dispatcher->dispatchMethod(ApiTestMethod_Ping::class, ['echo' => 'dari-array']);
        $this->assertSame('dari-array', ApiTestSupport::json($response)['data']['echo']);
    }

    public function testTheCurrentDispatcherIsOnlySetWhileDispatching() {
        $dispatcher = ApiTestSupport::dispatcher();
        $this->assertNull(CApi::currentDispatcher());
        $this->assertFalse($dispatcher->isDispatching());

        $seen = null;
        CEvent::dispatcher()->listen(CApi_Event_BeforeDispatch::class, function () use (&$seen) {
            $seen = CApi::currentDispatcher();
        });

        try {
            $dispatcher->dispatch(ApiTestSupport::request('api/test/ping'));
        } finally {
            CEvent::dispatcher()->forget(CApi_Event_BeforeDispatch::class);
        }

        $this->assertSame($dispatcher, $seen);
        $this->assertNull(CApi::currentDispatcher());
        $this->assertFalse($dispatcher->isDispatching());
    }

    public function testKernelEventsAreDispatchedInOrder() {
        $events = [];
        $names = [
            CApi_Event_IncomingRequest::class,
            CApi_Event_BeforeDispatch::class,
            CApi_Event_AfterDispatch::class,
            CApi_Event_RequestHandled::class,
        ];
        foreach ($names as $name) {
            CEvent::dispatcher()->listen($name, function ($event) use (&$events) {
                $events[] = get_class($event);
            });
        }

        try {
            $this->dispatch('api/test/ping');
        } finally {
            foreach ($names as $name) {
                CEvent::dispatcher()->forget($name);
            }
        }

        $this->assertSame($names, $events);
    }

    public function testTheRequestSeenByTheMethodIsAnApiRequestCarryingTheGroup() {
        $captured = null;
        CEvent::dispatcher()->listen(CApi_Event_BeforeDispatch::class, function ($event) use (&$captured) {
            $captured = $event->method;
        });

        try {
            $this->dispatch('api/test/ping', 'POST', ['echo' => 'y']);
        } finally {
            CEvent::dispatcher()->forget(CApi_Event_BeforeDispatch::class);
        }

        $this->assertInstanceOf(ApiTestMethod_Ping::class, $captured);
        $this->assertInstanceOf(CApi_HTTP_Request::class, $captured->getApiRequest());
        $this->assertSame(ApiTestSupport::GROUP, $captured->getApiRequest()->group());
        $this->assertSame(ApiTestSupport::GROUP, $captured->getGroup());
        $this->assertSame('y', $captured->getApiRequest()->input('echo'));
    }
}
