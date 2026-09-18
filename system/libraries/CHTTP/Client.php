<?php

use GuzzleHttp\Middleware;
use GuzzleHttp\TransferStats;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\Assert as PHPUnit;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response as Psr7Response;

/**
 * @method \CHTTP_Client_PendingRequest accept(string $contentType)
 * @method \CHTTP_Client_PendingRequest acceptJson()
 * @method \CHTTP_Client_PendingRequest asForm()
 * @method \CHTTP_Client_PendingRequest asJson()
 * @method \CHTTP_Client_PendingRequest asMultipart()
 * @method \CHTTP_Client_PendingRequest async()
 * @method \CHTTP_Client_PendingRequest attach(string|array $name, string|resource $contents = '', null|string $filename = null, array $headers = [])
 * @method \CHTTP_Client_PendingRequest baseUrl(string $url)
 * @method \CHTTP_Client_PendingRequest beforeSending(callable $callback)
 * @method \CHTTP_Client_PendingRequest bodyFormat(string $format)
 * @method \CHTTP_Client_PendingRequest connectTimeout(int $seconds)
 * @method \CHTTP_Client_PendingRequest contentType(string $contentType)
 * @method \CHTTP_Client_PendingRequest dd()
 * @method \CHTTP_Client_PendingRequest dump()
 * @method \CHTTP_Client_PendingRequest retry(int $times, int $sleep = 0, ?callable $when = null, bool $throw = true)
 * @method \CHTTP_Client_PendingRequest sink(string|resource $to)
 * @method \CHTTP_Client_PendingRequest stub(callable $callback)
 * @method \CHTTP_Client_PendingRequest timeout(int $seconds)
 * @method \CHTTP_Client_PendingRequest withBasicAuth(string $username, string $password)
 * @method \CHTTP_Client_PendingRequest withBody(resource|string $content, string $contentType)
 * @method \CHTTP_Client_PendingRequest withCookies(array $cookies, string $domain)
 * @method \CHTTP_Client_PendingRequest withDigestAuth(string $username, string $password)
 * @method \CHTTP_Client_PendingRequest withHeaders(array $headers)
 * @method \CHTTP_Client_PendingRequest withMiddleware(callable $middleware)
 * @method \CHTTP_Client_PendingRequest withOptions(array $options)
 * @method \CHTTP_Client_PendingRequest withToken(string $token, string $type = 'Bearer')
 * @method \CHTTP_Client_PendingRequest withUserAgent(string $userAgent)
 * @method \CHTTP_Client_PendingRequest withoutRedirecting()
 * @method \CHTTP_Client_PendingRequest withoutVerifying()
 * @method array                        pool(callable $callback)
 * @method \CHTTP_Client_Response       delete(string $url, array $data = [])
 * @method \CHTTP_Client_Response       get(string $url, null|array|string $query = null)
 * @method \CHTTP_Client_Response       head(string $url, null|array|string $query = null)
 * @method \CHTTP_Client_Response       patch(string $url, array $data = [])
 * @method \CHTTP_Client_Response       post(string $url, array $data = [])
 * @method \CHTTP_Client_Response       put(string $url, array $data = [])
 * @method \CHTTP_Client_Response       send(string $method, string $url, array $options = [])
 *
 * @see \CHTTP_Client_PendingRequest
 */
final class CHTTP_Client {
    use CTrait_Macroable {
        __call as macroCall;
    }

    /**
     * The event dispatcher implementation.
     *
     * @var null|\CEvent_DispatcherInterface
     */
    protected $dispatcher;

    /**
     * The middleware to apply to every request.
     *
     * @var array
     */
    protected $globalMiddleware = [];

    /**
     * The options to apply to every request.
     *
     * @var \Closure|array
     */
    protected $globalOptions = [];

    /**
     * The stub callables that will handle requests.
     *
     * @var \CCollection
     */
    protected $stubCallbacks;

    /**
     * Indicates if the factory is recording requests and responses.
     *
     * @var bool
     */
    protected $recording = false;

    /**
     * The recorded response array.
     *
     * @var array
     */
    protected $recorded = [];

    /**
     * All created response sequences.
     *
     * @var array
     */
    protected $responseSequences = [];

    /**
     * Indicates that an exception should be thrown if any request is not faked.
     *
     * @var bool
     */
    protected $preventStrayRequests = false;

    private static $instance;

    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Create a new factory instance.
     *
     * @return void
     */
    public function __construct() {
        $this->dispatcher = CEvent::dispatcher();

        $this->stubCallbacks = c::collect();
    }

    /**
     * Add middleware to apply to every request.
     *
     * @param callable $middleware
     *
     * @return $this
     */
    public function globalMiddleware($middleware) {
        $this->globalMiddleware[] = $middleware;

        return $this;
    }

    /**
     * Add request middleware to apply to every request.
     *
     * @param callable $middleware
     *
     * @return $this
     */
    public function globalRequestMiddleware($middleware) {
        $this->globalMiddleware[] = Middleware::mapRequest($middleware);

        return $this;
    }

    /**
     * Add response middleware to apply to every request.
     *
     * @param callable $middleware
     *
     * @return $this
     */
    public function globalResponseMiddleware($middleware) {
        $this->globalMiddleware[] = Middleware::mapResponse($middleware);

        return $this;
    }

    /**
     * Set the options to apply to every request.
     *
     * @param \Closure|array $options
     *
     * @return $this
     */
    public function globalOptions($options) {
        $this->globalOptions = $options;

        return $this;
    }

    /**
     * Create a new response instance for use during stubbing.
     *
     * @param array|string $body
     * @param int          $status
     * @param array        $headers
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function response($body = null, $status = 200, $headers = []) {
        return Create::promiseFor(
            static::psr7Response($body, $status, $headers)
        );
    }

    /**
     * Create a new PSR-7 response instance for use during stubbing.
     *
     * @param null|array|string    $body
     * @param int                  $status
     * @param array<string, mixed> $headers
     *
     * @return \GuzzleHttp\Psr7\Response
     */
    public static function psr7Response($body = null, $status = 200, $headers = []) {
        if (is_array($body)) {
            $body = json_encode($body);

            $headers['Content-Type'] = 'application/json';
        }

        return new Psr7Response($status, $headers, $body);
    }

    /**
     * Create a new RequestException instance for use during stubbing.
     *
     * @param null|array|string    $body
     * @param int                  $status
     * @param array<string, mixed> $headers
     *
     * @return \CHTTP_Client_Exception_RequestException
     */
    public static function failedRequest($body = null, $status = 200, $headers = []) {
        return new CHTTP_Client_Exception_RequestException(new CHTTP_Client_Response(static::psr7Response($body, $status, $headers)));
    }

    /**
     * Create a new connection exception for use during stubbing.
     *
     * @param null|string $message
     *
     * @return \Closure(\Illuminate\Http\Client\Request): \GuzzleHttp\Promise\PromiseInterface
     */
    public static function failedConnection($message = null) {
        return function ($request) use ($message) {
            return Create::rejectionFor(new ConnectException(
                $message ?? "cURL error 6: Could not resolve host: {$request->toPsrRequest()->getUri()->getHost()} (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for {$request->toPsrRequest()->getUri()}.",
                $request->toPsrRequest(),
            ));
        };
    }

    /**
     * Get an invokable object that returns a sequence of responses in order for use during stubbing.
     *
     * @param array $responses
     *
     * @return \CHTTP_Client_ResponseSequence
     */
    public function sequence(array $responses = []) {
        return $this->responseSequences[] = new CHTTP_Client_ResponseSequence($responses);
    }

    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     *
     * @param callable|array $callback
     *
     * @return $this
     */
    public function fake($callback = null) {
        $this->record();

        $this->recorded = [];

        if (is_null($callback)) {
            $callback = function () {
                return static::response();
            };
        }

        if (is_array($callback)) {
            foreach ($callback as $url => $callable) {
                $this->stubUrl($url, $callable);
            }

            return $this;
        }

        $this->stubCallbacks = $this->stubCallbacks->merge(c::collect([
            function ($request, $options) use ($callback) {
                $response = $callback;
                while ($response instanceof Closure) {
                    $response = $response($request, $options);
                }

                if ($response instanceof PromiseInterface) {
                    $options['on_stats'](new TransferStats(
                        $request->toPsrRequest(),
                        $response->wait(),
                    ));
                }

                return $response;
            },
        ]));

        return $this;
    }

    /**
     * Register a response sequence for the given URL pattern.
     *
     * @param string $url
     *
     * @return \CHTTP_Client_ResponseSequence
     */
    public function fakeSequence($url = '*') {
        return c::tap($this->sequence(), function ($sequence) use ($url) {
            $this->fake([$url => $sequence]);
        });
    }

    /**
     * Stub the given URL using the given callback.
     *
     * @param string                                                               $url
     * @param \CHTTP_Client_Response|\GuzzleHttp\Promise\PromiseInterface|callable $callback
     *
     * @return $this
     */
    public function stubUrl($url, $callback) {
        return $this->fake(function ($request, $options) use ($url, $callback) {
            if (!cstr::is(cstr::start($url, '*'), $request->url())) {
                return;
            }
            if (is_int($callback) && $callback >= 100 && $callback < 600) {
                return static::response(null, $callback);
            }

            if (is_int($callback) || is_string($callback)) {
                return static::response($callback);
            }

            if ($callback instanceof Closure || $callback instanceof CHTTP_Client_ResponseSequence) {
                return $callback($request, $options);
            }

            return $callback;
        });
    }

    /**
     * Indicate that an exception should be thrown if any request is not faked.
     *
     * @param bool $prevent
     *
     * @return $this
     */
    public function preventStrayRequests($prevent = true) {
        $this->preventStrayRequests = $prevent;

        return $this;
    }

    /**
     * Determine if stray requests are being prevented.
     *
     * @return bool
     */
    public function preventingStrayRequests() {
        return $this->preventStrayRequests;
    }

    /**
     * Indicate that an exception should not be thrown if any request is not faked.
     *
     * @return $this
     */
    public function allowStrayRequests() {
        return $this->preventStrayRequests(false);
    }

    /**
     * Begin recording request / response pairs.
     *
     * @return $this
     */
    protected function record() {
        $this->recording = true;

        return $this;
    }

    /**
     * Record a request response pair.
     *
     * @param \CHTTP_Client_Request  $request
     * @param \CHTTP_Client_Response $response
     *
     * @return void
     */
    public function recordRequestResponsePair($request, $response) {
        if ($this->recording) {
            $this->recorded[] = [$request, $response];
        }
    }

    /**
     * Assert that a request / response pair was recorded matching a given truth test.
     *
     * @param callable $callback
     *
     * @return void
     */
    public function assertSent($callback) {
        PHPUnit::assertTrue(
            $this->recorded($callback)->count() > 0,
            'An expected request was not recorded.'
        );
    }

    /**
     * Assert that the given request was sent in the given order.
     *
     * @param array $callbacks
     *
     * @return void
     */
    public function assertSentInOrder($callbacks) {
        $this->assertSentCount(count($callbacks));

        foreach ($callbacks as $index => $url) {
            $callback = is_callable($url) ? $url : function ($request) use ($url) {
                return $request->url() == $url;
            };

            PHPUnit::assertTrue($callback(
                $this->recorded[$index][0],
                $this->recorded[$index][1]
            ), 'An expected request (#' . ($index + 1) . ') was not recorded.');
        }
    }

    /**
     * Assert that a request / response pair was not recorded matching a given truth test.
     *
     * @param callable $callback
     *
     * @return void
     */
    public function assertNotSent($callback) {
        PHPUnit::assertFalse(
            $this->recorded($callback)->count() > 0,
            'Unexpected request was recorded.'
        );
    }

    /**
     * Assert that no request / response pair was recorded.
     *
     * @return void
     */
    public function assertNothingSent() {
        PHPUnit::assertEmpty(
            $this->recorded,
            'Requests were recorded.'
        );
    }

    /**
     * Assert how many requests have been recorded.
     *
     * @param int $count
     *
     * @return void
     */
    public function assertSentCount($count) {
        PHPUnit::assertCount($count, $this->recorded);
    }

    /**
     * Assert that every created response sequence is empty.
     *
     * @return void
     */
    public function assertSequencesAreEmpty() {
        foreach ($this->responseSequences as $responseSequence) {
            PHPUnit::assertTrue(
                $responseSequence->isEmpty(),
                'Not all response sequences are empty.'
            );
        }
    }

    /**
     * Get a collection of the request / response pairs matching the given truth test.
     *
     * @param callable $callback
     *
     * @return \CCollection
     */
    public function recorded($callback = null) {
        if (empty($this->recorded)) {
            return c::collect();
        }
        $collect = new CCollection($this->recorded);
        if ($callback) {
            return $collect->filter(function ($pair) use ($callback) {
                return $callback($pair[0], $pair[1]);
            });
        }

        return $collect;
    }

    /**
     * Create a new pending request instance for this factory.
     *
     * @return \CHTTP_Client_PendingRequest
     */
    public function createPendingRequest() {
        return c::tap($this->newPendingRequest(), function ($request) {
            $request->stub($this->stubCallbacks)->preventStrayRequests($this->preventStrayRequests);
        });
    }

    /**
     * Create a new pending request instance for this factory.
     *
     * @return \CHTTP_Client_PendingRequest
     */
    protected function newPendingRequest() {
        return (new CHTTP_Client_PendingRequest($this, $this->globalMiddleware))->withOptions(c::value($this->globalOptions));
    }

    /**
     * Get the current event dispatcher implementation.
     *
     * @return null|\CEvent_DispatcherInterface
     */
    public function getDispatcher() {
        return $this->dispatcher;
    }

    /**
     * Get the array of global middleware.
     *
     * @return array
     */
    public function getGlobalMiddleware() {
        return $this->globalMiddleware;
    }

    /**
     * Execute a method against a new pending request instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return $this->createPendingRequest()->{$method}(...$parameters);
    }
}
