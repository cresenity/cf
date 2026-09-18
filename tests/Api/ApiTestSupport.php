<?php

/**
 * Fixture bersama suite CApi: grup api uji, method-method contoh, middleware contoh.
 * Bukan *Test.php → tidak ter-autoload, require_once dari tiap test file.
 */
final class ApiTestSupport {
    const GROUP = 'cfapitest';

    const PREFIX = 'api/test';

    /**
     * Daftarkan grup api uji ke konfigurasi (idempoten).
     *
     * @param array $override
     */
    public static function registerGroup(array $override = []) {
        CConfig::repository()->set('api.groups.' . self::GROUP, array_merge([
            'prefix' => self::PREFIX,
            'debug' => true,
            'version' => 'v1',
            'standards_tree' => 'x',
            'subtype' => '',
            'default_format' => 'json',
            'error_format' => [
                'errCode' => ':code',
                'errMessage' => ':message',
                'data' => [
                    'message' => ':message',
                    'errors' => ':errors',
                    'code' => ':code',
                    'status_code' => ':status_code',
                    'debug' => ':debug',
                ],
            ],
        ], $override));
    }

    /**
     * @return CApi_Dispatcher
     */
    public static function dispatcher() {
        self::registerGroup();

        return c::api(self::GROUP)->createDispatcher()
            ->setPrefix(self::PREFIX)
            ->setMethodNamespace('ApiTestMethod');
    }

    /**
     * @param string $uri
     * @param string $method
     * @param array  $parameters
     * @param array  $server
     *
     * @return CHTTP_Request
     */
    public static function request($uri, $method = 'POST', array $parameters = [], array $server = []) {
        return CHTTP_Request::create($uri, $method, $parameters, [], [], $server);
    }

    /**
     * @param Symfony\Component\HttpFoundation\Response $response
     *
     * @return array
     */
    public static function json($response) {
        return json_decode($response->getContent(), true);
    }
}

class ApiTestMethod_Ping extends CApi_MethodAbstract {
    public function execute() {
        $this->data = ['pong' => true, 'echo' => carr::get($this->request(), 'echo')];
    }
}

class ApiTestMethod_Member_Profile extends CApi_MethodAbstract {
    public function execute() {
        $this->data = ['name' => 'Hery', 'orgId' => $this->orgId];
    }
}

class ApiTestMethod_Verbs extends CApi_MethodAbstract {
    public function execute() {
        $this->data = ['via' => 'execute'];
    }

    public function get() {
        $this->data = ['via' => 'get'];
    }

    public function post() {
        $this->data = ['via' => 'post'];
    }

    public function put() {
        $this->data = ['via' => 'put'];
    }

    public function delete() {
        $this->data = ['via' => 'delete'];
    }

    public function patch() {
        $this->data = ['via' => 'patch'];
    }
}

class ApiTestMethod_Fail extends CApi_MethodAbstract {
    public function execute() {
        $this->fail('gagal terkendali', (int) carr::get($this->request(), 'code', 7));
        $this->data = ['kept' => true];
    }
}

class ApiTestMethod_Boom extends CApi_MethodAbstract {
    public function execute() {
        throw new RuntimeException('meledak', 42);
    }
}

class ApiTestMethod_Teapot extends CApi_MethodAbstract {
    public function execute() {
        throw new Symfony\Component\HttpKernel\Exception\HttpException(418, 'saya teko', null, ['X-Teapot' => 'yes']);
    }
}

class ApiTestMethod_Invalid extends CApi_MethodAbstract {
    public function execute() {
        $this->validate(null, ['email' => 'required|email', 'age' => 'required|integer|min:17']);
        $this->data = ['ok' => true];
    }
}

class ApiTestMethod_Unauthorized extends CApi_MethodAbstract {
    public function execute() {
        throw new CAuth_Exception_AuthenticationException('harus login');
    }
}

class ApiTestMethod_Raw extends CApi_MethodAbstract {
    public function execute() {
        return new CHTTP_Response('mentah', 202, ['X-Raw' => '1']);
    }
}

class ApiTestMethod_Guarded extends CApi_MethodAbstract {
    public function __construct($orgId = null, $sessionId = null, $request = null) {
        parent::__construct($orgId, $sessionId, $request);
        $this->middleware(ApiTestMiddleware_Stamp::class);
        $this->middleware([ApiTestMiddleware_Gate::class]);
    }

    public function execute() {
        $this->data = ['reached' => true];
    }
}

class ApiTestMethod_Validator extends CApi_MethodAbstract {
    use CApi_Trait_MethodValidateRequestTrait;

    public function execute() {
    }
}

class ApiTestMiddleware_Stamp implements CApi_Contract_ApiGroupMiddlewareInterface {
    /**
     * @var null|string
     */
    public static $group;

    public function setGroup($group) {
        static::$group = $group;
    }

    public function handle($request, Closure $next) {
        $response = $next($request);
        $response->headers->set('X-Stamp', 'stamped');

        return $response;
    }
}

class ApiTestMiddleware_Gate {
    public function handle($request, Closure $next) {
        if ($request->input('gate') === 'closed') {
            return new CHTTP_Response('ditolak middleware', 403);
        }

        return $next($request);
    }
}

class ApiTestApiException extends Exception implements CApi_Contract_ApiException {
    public function getErrCode() {
        return 4321;
    }
}
