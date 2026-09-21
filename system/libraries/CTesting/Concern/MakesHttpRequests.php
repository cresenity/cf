<?php

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

trait CTesting_Concern_MakesHttpRequests {
    /**
     * Additional headers for the request.
     *
     * @var array
     */
    protected $defaultHeaders = [];

    /**
     * Additional cookies for the request.
     *
     * @var array
     */
    protected $defaultCookies = [];

    /**
     * Additional cookies will not be encrypted for the request.
     *
     * @var array
     */
    protected $unencryptedCookies = [];

    /**
     * Additional server variables for the request.
     *
     * @var array
     */
    protected $serverVariables = [];

    /**
     * Indicates whether redirects should be followed.
     *
     * @var bool
     */
    protected $followRedirects = false;

    /**
     * Indicates whether cookies should be encrypted.
     *
     * @var bool
     */
    protected $encryptCookies = true;

    /**
     * Indicated whether JSON requests should be performed "with credentials" (cookies).
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest/withCredentials
     *
     * @var bool
     */
    protected $withCredentials = false;

    /**
     * Define additional headers to be sent with the request.
     *
     * @return $this
     */
    public function withHeaders(array $headers) {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);

        return $this;
    }

    /**
     * Add a header to be sent with the request.
     *
     * @param string $name
     * @param string $value
     *
     * @return $this
     */
    public function withHeader($name, $value) {
        $this->defaultHeaders[$name] = $value;

        return $this;
    }

    /**
     * Add an authorization token for the request.
     *
     * @param string $token
     * @param string $type
     *
     * @return $this
     */
    public function withToken($token, $type = 'Bearer') {
        return $this->withHeader('Authorization', $type . ' ' . $token);
    }

    /**
     * Flush all the configured headers.
     *
     * @return $this
     */
    public function flushHeaders() {
        $this->defaultHeaders = [];

        return $this;
    }

    /**
     * Define a set of server variables to be sent with the requests.
     *
     * @return $this
     */
    public function withServerVariables(array $server) {
        $this->serverVariables = $server;

        return $this;
    }

    /**
     * Disable middleware for the test.
     *
     * With no argument, disables middleware everywhere (both regular HTTP routes
     * via CHTTP and every configured API group via CApi_Manager). Given one or more
     * middleware class names, only those are turned into a pass-through no-op by
     * overriding their binding in the container that CBase_Pipeline resolves from.
     *
     * @param null|string|array $middleware
     *
     * @return $this
     */
    public function withoutMiddleware($middleware = null) {
        if (is_null($middleware)) {
            CHTTP::withoutMiddleware();
            CApi_Manager::withoutMiddlewareForAllGroups();

            return $this;
        }

        foreach ((array) $middleware as $abstract) {
            $this->app->instance($abstract, new class() {
                /**
                 * Handle the request and return a response.
                 *
                 * @param mixed    $request
                 * @param callable $next
                 *
                 * @return mixed
                 */
                public function handle($request, $next) {
                    return $next($request);
                }
            });
        }

        return $this;
    }

    /**
     * Enable the given middleware for the test.
     *
     * @param null|string|array $middleware
     *
     * @return $this
     */
    public function withMiddleware($middleware = null) {
        if (is_null($middleware)) {
            CHTTP::withMiddleware();
            CApi_Manager::withMiddlewareForAllGroups();

            return $this;
        }

        foreach ((array) $middleware as $abstract) {
            unset($this->app[$abstract]);
        }

        return $this;
    }

    /**
     * Define additional cookies to be sent with the request.
     *
     * @return $this
     */
    public function withCookies(array $cookies) {
        $this->defaultCookies = array_merge($this->defaultCookies, $cookies);

        return $this;
    }

    /**
     * Add a cookie to be sent with the request.
     *
     * @param string $name
     * @param string $value
     *
     * @return $this
     */
    public function withCookie($name, $value) {
        $this->defaultCookies[$name] = $value;

        return $this;
    }

    /**
     * Define additional cookies will not be encrypted before sending with the request.
     *
     * @return $this
     */
    public function withUnencryptedCookies(array $cookies) {
        $this->unencryptedCookies = array_merge($this->unencryptedCookies, $cookies);

        return $this;
    }

    /**
     * Add a cookie will not be encrypted before sending with the request.
     *
     * @param string $name
     * @param string $value
     *
     * @return $this
     */
    public function withUnencryptedCookie($name, $value) {
        $this->unencryptedCookies[$name] = $value;

        return $this;
    }

    /**
     * Automatically follow any redirects returned from the response.
     *
     * @return $this
     */
    public function followingRedirects() {
        $this->followRedirects = true;

        return $this;
    }

    /**
     * Include cookies and authorization headers for JSON requests.
     *
     * @return $this
     */
    public function withCredentials() {
        $this->withCredentials = true;

        return $this;
    }

    /**
     * Disable automatic encryption of cookie values.
     *
     * @return $this
     */
    public function disableCookieEncryption() {
        $this->encryptCookies = false;

        return $this;
    }

    /**
     * Set the referer header and previous URL session value in order to simulate a previous request.
     *
     * @param string $url
     *
     * @return $this
     */
    public function from($url) {
        c::session()->setPreviousUrl($url);

        return $this->withHeader('referer', $url);
    }

    /**
     * Visit the given URI with a GET request.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function get($uri, array $headers = []) {
        $server = $this->transformHeadersToServerVars($headers);
        $cookies = $this->prepareCookiesForRequest();

        return $this->call('GET', $uri, [], $cookies, [], $server);
    }

    /**
     * Visit the given URI with a GET request, expecting a JSON response.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function getJson($uri, array $headers = []) {
        return $this->json('GET', $uri, [], $headers);
    }

    /**
     * Visit the given URI with a POST request.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function post($uri, array $data = [], array $headers = []) {
        $server = $this->transformHeadersToServerVars($headers);
        $cookies = $this->prepareCookiesForRequest();

        return $this->call('POST', $uri, $data, $cookies, [], $server);
    }

    /**
     * Visit the given URI with a POST request, expecting a JSON response.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function postJson($uri, array $data = [], array $headers = []) {
        return $this->json('POST', $uri, $data, $headers);
    }

    /**
     * Visit the given URI with a PUT request.
     *
     * @param string $uri
     *
     * @return \CTesting_TestResponse
     */
    public function put($uri, array $data = [], array $headers = []) {
        $server = $this->transformHeadersToServerVars($headers);
        $cookies = $this->prepareCookiesForRequest();

        return $this->call('PUT', $uri, $data, $cookies, [], $server);
    }

    /**
     * Visit the given URI with a PUT request, expecting a JSON response.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function putJson($uri, array $data = [], array $headers = []) {
        return $this->json('PUT', $uri, $data, $headers);
    }

    /**
     * Visit the given URI with a PATCH request.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function patch($uri, array $data = [], array $headers = []) {
        $server = $this->transformHeadersToServerVars($headers);
        $cookies = $this->prepareCookiesForRequest();

        return $this->call('PATCH', $uri, $data, $cookies, [], $server);
    }

    /**
     * Visit the given URI with a PATCH request, expecting a JSON response.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function patchJson($uri, array $data = [], array $headers = []) {
        return $this->json('PATCH', $uri, $data, $headers);
    }

    /**
     * Visit the given URI with a DELETE request.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function delete($uri, array $data = [], array $headers = []) {
        $server = $this->transformHeadersToServerVars($headers);
        $cookies = $this->prepareCookiesForRequest();

        return $this->call('DELETE', $uri, $data, $cookies, [], $server);
    }

    /**
     * Visit the given URI with a DELETE request, expecting a JSON response.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function deleteJson($uri, array $data = [], array $headers = []) {
        return $this->json('DELETE', $uri, $data, $headers);
    }

    /**
     * Visit the given URI with an OPTIONS request.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function options($uri, array $data = [], array $headers = []) {
        $server = $this->transformHeadersToServerVars($headers);
        $cookies = $this->prepareCookiesForRequest();

        return $this->call('OPTIONS', $uri, $data, $cookies, [], $server);
    }

    /**
     * Visit the given URI with an OPTIONS request, expecting a JSON response.
     *
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function optionsJson($uri, array $data = [], array $headers = []) {
        return $this->json('OPTIONS', $uri, $data, $headers);
    }

    /**
     * Call the given URI with a JSON request.
     *
     * @param string $method
     * @param string $uri
     *
     * @return CTesting_TestResponse
     */
    public function json($method, $uri, array $data = [], array $headers = []) {
        $files = $this->extractFilesFromDataArray($data);

        $content = json_encode($data);

        $headers = array_merge([
            'CONTENT_LENGTH' => mb_strlen($content, '8bit'),
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);

        return $this->call(
            $method,
            $uri,
            [],
            $this->prepareCookiesForJsonRequest(),
            $files,
            $this->transformHeadersToServerVars($headers),
            $content
        );
    }

    /**
     * Call the given URI and return the Response.
     *
     * @param string      $method
     * @param string      $uri
     * @param array       $parameters
     * @param array       $cookies
     * @param array       $files
     * @param array       $server
     * @param null|string $content
     *
     * @return CTesting_TestResponse
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null) {
        // CObserver and CApp are process-wide singletons; in production
        // they're implicitly fresh every request (new PHP process), but this
        // test process handles many simulated requests, so both must be
        // reset here. CObserver tracks rendered element ids to catch
        // duplicates within one page render — leftover state throws "Object
        // :id is exists". CApp::instance() caches one CApp per domain and
        // accumulates widgets/breadcrumbs/title across requests to the same
        // domain if not reset, silently serving stale content from a
        // previous simulated request instead of running the new controller.
        CObserver::reset();
        CApp::resetInstances();

        $kernel = new CHTTP_Kernel();

        $files = array_merge($files, $this->extractFilesFromDataArray($parameters));
        //$uri = 'http://' . CConsole::domain() . $uri;
        $symfonyRequest = SymfonyRequest::create(
            $this->prepareUrlForRequest($uri),
            $method,
            $parameters,
            $cookies,
            $files,
            array_replace($this->serverVariables, $server),
            $content
        );
        $request = CHTTP_Request::createFromBase($symfonyRequest);

        // Controllers that read $_POST/$_GET directly (CApp_Trait_BaseTrait::
        // getRequestPost() and the like) would otherwise see the previous
        // request's data, or nothing; the globals are put back afterwards so
        // one simulated request never leaks into the next.
        $previousPost = $_POST;
        $previousGet = $_GET;
        $_POST = $request->request->all();
        $_GET = $request->query->all();

        try {
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
        } finally {
            $_POST = $previousPost;
            $_GET = $previousGet;
        }

        if ($this->followRedirects) {
            $response = $this->followRedirects($response);
        }

        return $this->createTestResponse($response);
    }

    /**
     * Turn the given URI into a fully qualified URL.
     *
     * @param string $uri
     *
     * @return string
     */
    protected function prepareUrlForRequest($uri) {
        if (cstr::startsWith($uri, '/')) {
            $uri = substr($uri, 1);
        }

        return 'http://' . CConsole::domain() . '/' . $uri;
        //return trim(c::url($uri), '/');
    }

    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     *
     * @return array
     */
    protected function transformHeadersToServerVars(array $headers) {
        return c::collect(array_merge($this->defaultHeaders, $headers))->mapWithKeys(function ($value, $name) {
            $name = strtr(strtoupper($name), '-', '_');

            return [$this->formatServerHeaderKey($name) => $value];
        })->all();
    }

    /**
     * Format the header name for the server array.
     *
     * @param string $name
     *
     * @return string
     */
    protected function formatServerHeaderKey($name) {
        if (!cstr::startsWith($name, 'HTTP_') && $name !== 'CONTENT_TYPE' && $name !== 'REMOTE_ADDR') {
            return 'HTTP_' . $name;
        }

        return $name;
    }

    /**
     * Extract the file uploads from the given data array.
     *
     * @param array $data
     *
     * @return array
     */
    protected function extractFilesFromDataArray(&$data) {
        $files = [];

        foreach ($data as $key => $value) {
            if ($value instanceof SymfonyUploadedFile) {
                $files[$key] = $value;

                unset($data[$key]);
            }

            if (is_array($value)) {
                $files[$key] = $this->extractFilesFromDataArray($value);

                $data[$key] = $value;
            }
        }

        return $files;
    }

    /**
     * If enabled, encrypt cookie values for request.
     *
     * @return array
     */
    protected function prepareCookiesForRequest() {
        if (!$this->encryptCookies) {
            return array_merge($this->defaultCookies, $this->unencryptedCookies);
        }

        return c::collect($this->defaultCookies)->map(function ($value, $key) {
            return c::encrypt(CHTTP_Cookie_CookieValuePrefix::create($key, CCrypt::encrypter()->getKey()) . $value, false);
        })->merge($this->unencryptedCookies)->all();
    }

    /**
     * If enabled, add cookies for JSON requests.
     *
     * @return array
     */
    protected function prepareCookiesForJsonRequest() {
        return $this->withCredentials ? $this->prepareCookiesForRequest() : [];
    }

    /**
     * Follow a redirect chain until a non-redirect is received.
     *
     * @param CHTTP_Response $response
     *
     * @return CHttp_Response|CTesting_TestResponse
     */
    protected function followRedirects($response) {
        $this->followRedirects = false;

        while ($response->isRedirect()) {
            $response = $this->get($response->headers->get('Location'));
        }

        return $response;
    }

    /**
     * Create the test response instance from the given response.
     *
     * @param CHTTP_Response $response
     *
     * @return CTesting_TestResponse
     */
    protected function createTestResponse($response) {
        return c::tap(CTesting_TestResponse::fromBaseResponse($response), function ($response) {
            $response->withExceptions(CTesting::loggedExceptionCollection());
        });
    }
}
