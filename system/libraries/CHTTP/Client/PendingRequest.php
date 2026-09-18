<?php

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\UriTemplate\UriTemplate;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\VarDumper\VarDumper;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Contracts\Support\Arrayable;

class CHTTP_Client_PendingRequest {
    use CTrait_Conditionable, CTrait_Macroable;

    /**
     * The factory instance.
     *
     * @var null|\CHTTP_Client
     */
    protected $factory;

    /**
     * The Guzzle client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * The Guzzle HTTP handler.
     *
     * @var callable
     */
    protected $handler;

    /**
     * The base URL for the request.
     *
     * @var string
     */
    protected $baseUrl = '';

    /**
     * The parameters that can be substituted into the URL.
     *
     * @var array
     */
    protected $urlParameters = [];

    /**
     * The request body format.
     *
     * @var string
     */
    protected $bodyFormat;

    /**
     * The raw body for the request.
     *
     * @var null|string
     */
    protected $pendingBody;

    /**
     * The pending files for the request.
     *
     * @var array
     */
    protected $pendingFiles = [];

    /**
     * The request cookies.
     *
     * @var array
     */
    protected $cookies;

    /**
     * The transfer stats for the request.
     *
     * \GuzzleHttp\TransferStats
     */
    protected $transferStats;

    /**
     * The request options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * A callback to run when throwing if a server or client error occurs.
     *
     * @var \Closure
     */
    protected $throwCallback;

    /**
     * A callback to check if an exception should be thrown when a server or client error occurs.
     *
     * @var \Closure
     */
    protected $throwIfCallback;

    /**
     * The number of times to try the request.
     *
     * @var int
     */
    protected $tries = 1;

    /**
     * The number of milliseconds to wait between retries.
     *
     * @var int
     */
    protected $retryDelay = 100;

    /**
     * Whether to throw an exception when all retries fail.
     *
     * @var bool
     */
    protected $retryThrow = true;

    /**
     * The callback that will determine if the request should be retried.
     *
     * @var null|callable
     */
    protected $retryWhenCallback = null;

    /**
     * The callbacks that should execute before the request is sent.
     *
     * @var \CCollection
     */
    protected $beforeSendingCallbacks;

    /**
     * The stub callables that will handle requests.
     *
     * @var null|\CCollection
     */
    protected $stubCallbacks;

    /**
     * Indicates that an exception should be thrown if any request is not faked.
     *
     * @var bool
     */
    protected $preventStrayRequests = false;

    /**
     * The middleware callables added by users that will handle requests.
     *
     * @var \CCollection
     */
    protected $middleware;

    /**
     * Whether the requests should be asynchronous.
     *
     * @var bool
     */
    protected $async = false;

    /**
     * The pending request promise.
     *
     * @var \GuzzleHttp\Promise\PromiseInterface
     */
    protected $promise;

    /**
     * The sent request object, if a request has been made.
     *
     * @var null|\CHTTP_Client_Request
     */
    protected $request;

    /**
     * The Guzzle request options that are mergable via array_merge_recursive.
     *
     * @var array
     */
    protected $mergableOptions = [
        'cookies',
        'form_params',
        'headers',
        'json',
        'multipart',
        'query',
    ];

    /**
     * The length at which request exceptions will be truncated.
     *
     * @var null|int<1, max>|false
     */
    protected $truncateExceptionsAt = null;

    /**
     * Create a new HTTP Client instance.
     *
     * @param null|\CHTTP_Client $factory
     * @param mixed              $middleware
     *
     * @return void
     */
    public function __construct(?CHTTP_Client $factory = null, $middleware = []) {
        $this->factory = $factory;
        $this->middleware = new CCollection($middleware);

        $this->asJson();

        $this->options = [
            'connect_timeout' => 10,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            'http_errors' => false,
            'timeout' => 30,
        ];

        $this->beforeSendingCallbacks = c::collect([function (CHTTP_Client_Request $request, array $options, CHTTP_Client_PendingRequest $pendingRequest) {
            $pendingRequest->request = $request;
            $pendingRequest->cookies = $options['cookies'];

            $pendingRequest->dispatchRequestSendingEvent();
        }]);
    }

    /**
     * Set the base URL for the pending request.
     *
     * @param string $url
     *
     * @return $this
     */
    public function baseUrl(string $url) {
        $this->baseUrl = $url;

        return $this;
    }

    /**
     * Attach a raw body to the request.
     *
     * @param string|\Psr\Http\Message\StreamInterface $content
     * @param string                                       $contentType
     *
     * @return $this
     */
    public function withBody($content, $contentType = 'application/json') {
        $this->bodyFormat('body');

        $this->pendingBody = $content;

        $this->contentType($contentType);

        return $this;
    }

    /**
     * Indicate the request contains JSON.
     *
     * @return $this
     */
    public function asJson() {
        return $this->bodyFormat('json')->contentType('application/json');
    }

    /**
     * Indicate the request contains form parameters.
     *
     * @return $this
     */
    public function asForm() {
        return $this->bodyFormat('form_params')->contentType('application/x-www-form-urlencoded');
    }

    /**
     * Attach a file to the request.
     *
     * @param string|array    $name
     * @param string|resource $contents
     * @param null|string     $filename
     * @param array           $headers
     *
     * @return $this
     */
    public function attach($name, $contents = '', $filename = null, array $headers = []) {
        if (is_array($name)) {
            foreach ($name as $file) {
                $this->attach(...$file);
            }

            return $this;
        }

        $this->asMultipart();

        $this->pendingFiles[] = array_filter([
            'name' => $name,
            'contents' => $contents,
            'headers' => $headers,
            'filename' => $filename,
        ]);

        return $this;
    }

    /**
     * Indicate the request is a multi-part form request.
     *
     * @return $this
     */
    public function asMultipart() {
        return $this->bodyFormat('multipart');
    }

    /**
     * Specify the body format of the request.
     *
     * @param string $format
     *
     * @return $this
     */
    public function bodyFormat(string $format) {
        return c::tap($this, function () use ($format) {
            $this->bodyFormat = $format;
        });
    }

    /**
     * Specify the request's content type.
     *
     * @param string $contentType
     *
     * @return $this
     */
    public function contentType(string $contentType) {
        $this->options['headers']['Content-Type'] = $contentType;

        return $this;
    }

    /**
     * Indicate that JSON should be returned by the server.
     *
     * @return $this
     */
    public function acceptJson() {
        return $this->accept('application/json');
    }

    /**
     * Indicate the type of content that should be returned by the server.
     *
     * @param string $contentType
     *
     * @return $this
     */
    #[\ReturnTypeWillChange]
    public function accept($contentType) {
        return $this->withHeaders(['Accept' => $contentType]);
    }

    /**
     * Add the given headers to the request.
     *
     * @param array $headers
     *
     * @return $this
     */
    public function withHeaders(array $headers) {
        return c::tap($this, function () use ($headers) {
            return $this->options = array_merge_recursive($this->options, [
                'headers' => $headers,
            ]);
        });
    }

    /**
     * Add the given header to the request.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return $this
     */
    public function withHeader($name, $value) {
        return $this->withHeaders([$name => $value]);
    }

    /**
     * Replace the given headers on the request.
     *
     * @param array $headers
     *
     * @return $this
     */
    public function replaceHeaders(array $headers) {
        $this->options['headers'] = array_merge($this->options['headers'] ?? [], $headers);

        return $this;
    }

    /**
     * Specify the basic authentication username and password for the request.
     *
     * @param string $username
     * @param string $password
     *
     * @return $this
     */
    public function withBasicAuth(string $username, string $password) {
        return c::tap($this, function () use ($username, $password) {
            return $this->options['auth'] = [$username, $password];
        });
    }

    /**
     * Specify the digest authentication username and password for the request.
     *
     * @param string $username
     * @param string $password
     *
     * @return $this
     */
    public function withDigestAuth($username, $password) {
        return c::tap($this, function () use ($username, $password) {
            return $this->options['auth'] = [$username, $password, 'digest'];
        });
    }

    /**
     * Specify an authorization token for the request.
     *
     * @param string $token
     * @param string $type
     *
     * @return $this
     */
    public function withToken($token, $type = 'Bearer') {
        return c::tap($this, function () use ($token, $type) {
            return $this->options['headers']['Authorization'] = trim($type . ' ' . $token);
        });
    }

    /**
     * Specify the user agent for the request.
     *
     * @param string $userAgent
     *
     * @return $this
     */
    public function withUserAgent($userAgent) {
        return c::tap($this, function () use ($userAgent) {
            return $this->options['headers']['User-Agent'] = trim($userAgent);
        });
    }

    /**
     * Specify the URL parameters that can be substituted into the request URL.
     *
     * @param array $parameters
     *
     * @return $this
     */
    public function withUrlParameters(array $parameters = []) {
        return c::tap($this, function () use ($parameters) {
            $this->urlParameters = $parameters;
        });
    }

    /**
     * Specify the cookies that should be included with the request.
     *
     * @param array  $cookies
     * @param string $domain
     *
     * @return $this
     */
    public function withCookies(array $cookies, string $domain) {
        return c::tap($this, function () use ($cookies, $domain) {
            return $this->options = array_merge_recursive($this->options, [
                'cookies' => CookieJar::fromArray($cookies, $domain),
            ]);
        });
    }

    /**
     * Specify the maximum number of redirects to allow.
     *
     * @param int $max
     *
     * @return $this
     */
    public function maxRedirects(int $max) {
        return c::tap($this, function () use ($max) {
            $this->options['allow_redirects']['max'] = $max;
        });
    }

    /**
     * Indicate that redirects should not be followed.
     *
     * @return $this
     */
    public function withoutRedirecting() {
        return c::tap($this, function () {
            return $this->options['allow_redirects'] = false;
        });
    }

    /**
     * Indicate that TLS certificates should not be verified.
     *
     * @return $this
     */
    public function withoutVerifying() {
        return c::tap($this, function () {
            return $this->options['verify'] = false;
        });
    }

    /**
     * Specify the path where the body of the response should be stored.
     *
     * @param string|resource $to
     *
     * @return $this
     */
    public function sink($to) {
        return c::tap($this, function () use ($to) {
            return $this->options['sink'] = $to;
        });
    }

    /**
     * Specify the timeout (in seconds) for the request.
     *
     * @param int $seconds
     *
     * @return $this
     */
    public function timeout(int $seconds) {
        return c::tap($this, function () use ($seconds) {
            $this->options['timeout'] = $seconds;
        });
    }

    /**
     * Specify the connect timeout (in seconds) for the request.
     *
     * @param int $seconds
     *
     * @return $this
     */
    public function connectTimeout(int $seconds) {
        return c::tap($this, function () use ($seconds) {
            $this->options['connect_timeout'] = $seconds;
        });
    }

    /**
     * Specify the number of times the request should be attempted.
     *
     * @param int           $times
     * @param int           $sleep
     * @param null|callable $when
     * @param bool          $throw
     *
     * @return $this
     */
    public function retry(int $times, int $sleep = 0, ?callable $when = null, bool $throw = true) {
        $this->tries = $times;
        $this->retryDelay = $sleep;
        $this->retryThrow = $throw;
        $this->retryWhenCallback = $when;

        return $this;
    }

    /**
     * Replace the specified options on the request.
     *
     * @param array $options
     *
     * @return $this
     */
    public function withOptions(array $options) {
        return c::tap($this, function () use ($options) {
            return $this->options = array_replace_recursive(
                array_merge_recursive($this->options, carr::only($options, $this->mergableOptions)),
                $options
            );
        });
    }

    /**
     * Add new middleware the client handler stack.
     *
     * @param callable $middleware
     *
     * @return $this
     */
    public function withMiddleware(callable $middleware) {
        $this->middleware->push($middleware);

        return $this;
    }

    /**
     * Add new request middleware the client handler stack.
     *
     * @param callable $middleware
     *
     * @return $this
     */
    public function withRequestMiddleware(callable $middleware) {
        $this->middleware->push(Middleware::mapRequest($middleware));

        return $this;
    }

    /**
     * Add new response middleware the client handler stack.
     *
     * @param callable $middleware
     *
     * @return $this
     */
    public function withResponseMiddleware(callable $middleware) {
        $this->middleware->push(Middleware::mapResponse($middleware));

        return $this;
    }

    /**
     * Add a new "before sending" callback to the request.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function beforeSending($callback) {
        return c::tap($this, function () use ($callback) {
            $this->beforeSendingCallbacks[] = $callback;
        });
    }

    /**
     * Throw an exception if a server or client error occurs.
     *
     * @param null|callable $callback
     *
     * @return $this
     */
    public function throw($callback = null) {
        $this->throwCallback = $callback ?: function () {
            return null;
        };

        return $this;
    }

    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to true.
     *
     * @param callable|bool $condition
     *
     * @return $this
     */
    public function throwIf($condition) {
        if (is_callable($condition)) {
            $this->throwIfCallback = $condition;
        }

        return $condition ? $this->throw(func_get_args()[1] ?? null) : $this;
    }

    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to false.
     *
     * @param callable|bool $condition
     *
     * @return $this
     */
    public function throwUnless($condition) {
        return $this->throwIf(!$condition);
    }

    /**
     * Dump the request before sending.
     *
     * @return $this
     */
    public function dump() {
        $values = func_get_args();

        return $this->beforeSending(function (CHTTP_Client_Request $request, array $options) use ($values) {
            foreach (array_merge($values, [$request, $options]) as $value) {
                VarDumper::dump($value);
            }
        });
    }

    /**
     * Dump the request before sending and end the script.
     *
     * @return $this
     */
    public function dd() {
        $values = func_get_args();

        return $this->beforeSending(function (CHTTP_Client_Request $request, array $options) use ($values) {
            foreach (array_merge($values, [$request, $options]) as $value) {
                VarDumper::dump($value);
            }

            exit(1);
        });
    }

    /**
     * Issue a GET request to the given URL.
     *
     * @param string            $url
     * @param null|array|string $query
     *
     * @return \CHTTP_Client_Response
     */
    public function get(string $url, $query = null) {
        return $this->send('GET', $url, func_num_args() === 1 ? [] : [
            'query' => $query,
        ]);
    }

    /**
     * Issue a HEAD request to the given URL.
     *
     * @param string            $url
     * @param null|array|string $query
     *
     * @return \CHTTP_Client_Response
     */
    public function head(string $url, $query = null) {
        return $this->send('HEAD', $url, func_num_args() === 1 ? [] : [
            'query' => $query,
        ]);
    }

    /**
     * Issue a POST request to the given URL.
     *
     * @param string $url
     * @param array  $data
     *
     * @return \CHTTP_Client_Response
     */
    public function post(string $url, $data = []) {
        return $this->send('POST', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Issue a PATCH request to the given URL.
     *
     * @param string $url
     * @param array  $data
     *
     * @return \CHTTP_Client_Response
     */
    public function patch($url, $data = []) {
        return $this->send('PATCH', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Issue a PUT request to the given URL.
     *
     * @param string $url
     * @param array  $data
     *
     * @return \CHTTP_Client_Response
     */
    public function put($url, $data = []) {
        return $this->send('PUT', $url, [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Issue a DELETE request to the given URL.
     *
     * @param string $url
     * @param array  $data
     *
     * @return \CHTTP_Client_Response
     */
    public function delete($url, $data = []) {
        return $this->send('DELETE', $url, empty($data) ? [] : [
            $this->bodyFormat => $data,
        ]);
    }

    /**
     * Send a pool of asynchronous requests concurrently.
     *
     * @param callable $callback
     *
     * @return array
     */
    public function pool(callable $callback) {
        $results = [];

        $requests = c::tap(new CHTTP_Client_Pool($this->factory), $callback)->getRequests();

        foreach ($requests as $key => $item) {
            $results[$key] = $item instanceof static ? $item->getPromise()->wait() : $item->wait();
        }

        return $results;
    }

    /**
     * Send the request to the given URL.
     *
     * @param string $method
     * @param string $url
     * @param array  $options
     *
     * @throws \Exception
     *
     * @return \CHTTP_Client_Response|\GuzzleHttp\Promise\PromiseInterface
     */
    public function send(string $method, string $url, array $options = []) {
        if (!cstr::startsWith($url, ['http://', 'https://'])) {
            $url = ltrim(rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/'), '/');
        }

        $url = $this->expandUrlParameters($url);

        $options = $this->parseHttpOptions($options);

        list($this->pendingBody, $this->pendingFiles) = [null, []];

        if ($this->async) {
            return $this->makePromise($method, $url, $options);
        }
        $shouldRetry = null;

        return c::retry($this->tries ?: 1, function ($attempt) use ($method, $url, $options, &$shouldRetry) {
            try {
                return c::tap($this->newResponse($this->sendRequest($method, $url, $options)), function ($response) use ($attempt, &$shouldRetry) {
                    $this->populateResponse($response);
                    $this->dispatchResponseReceivedEvent($response);
                    if (!$response->successful()) {
                        try {
                            $shouldRetry = $this->retryWhenCallback ? call_user_func($this->retryWhenCallback, $response->toException(), $this, $this->request->toPsrRequest()->getMethod()) : true;
                        } catch (Exception $exception) {
                            $shouldRetry = false;

                            throw $exception;
                        }

                        if ($this->throwCallback
                            && ($this->throwIfCallback === null
                             || call_user_func($this->throwIfCallback, $response))
                        ) {
                            $response->throw($this->throwCallback);
                        }

                        $potentialTries = is_array($this->tries)
                            ? count($this->tries) + 1
                            : $this->tries;

                        if ($attempt < $potentialTries && $shouldRetry) {
                            $response->throw();
                        }

                        if ($potentialTries > 1 && $this->retryThrow) {
                            $response->throw();
                        }
                    }
                });
            } catch (TransferException $e) {
                if ($e instanceof ConnectException) {
                    $this->marshalConnectionException($e);
                }

                if ($e instanceof RequestException && !$e->hasResponse()) {
                    $this->marshalRequestExceptionWithoutResponse($e);
                }

                if ($e instanceof RequestException && $e->hasResponse()) {
                    $this->marshalRequestExceptionWithResponse($e);
                }

                throw $e;
            }
        }, $this->retryDelay ?? 100, function ($exception) use (&$shouldRetry) {
            $result = $shouldRetry ?? ($this->retryWhenCallback ? call_user_func($this->retryWhenCallback, $exception, $this, c::optional($this->request)->toPsrRequest()->getMethod()) : true);

            $shouldRetry = null;

            return $result;
        });
    }

    /**
     * Substitute the URL parameters in the given URL.
     *
     * @param string $url
     *
     * @return string
     */
    protected function expandUrlParameters(string $url) {
        return UriTemplate::expand($url, $this->urlParameters);
    }

    /**
     * Parse the given HTTP options and set the appropriate additional options.
     *
     * @param array $options
     *
     * @return array
     */
    protected function parseHttpOptions(array $options) {
        if (isset($options[$this->bodyFormat])) {
            if ($this->bodyFormat === 'multipart') {
                $options[$this->bodyFormat] = $this->parseMultipartBodyFormat($options[$this->bodyFormat]);
            } elseif ($this->bodyFormat === 'body') {
                $options[$this->bodyFormat] = $this->pendingBody;
            }

            if (is_array($options[$this->bodyFormat])) {
                $options[$this->bodyFormat] = array_merge(
                    $options[$this->bodyFormat],
                    $this->pendingFiles
                );
            }
        } else {
            $options[$this->bodyFormat] = $this->pendingBody;
        }

        return c::collect($options)->map(function ($value, $key) {
            if ($key === 'json' && $value instanceof JsonSerializable) {
                return $value;
            }

            return $value instanceof Arrayable ? $value->toArray() : $value;
        })->all();
    }

    /**
     * Parse multi-part form data.
     *
     * @param array $data
     *
     * @return array|array[]
     */
    protected function parseMultipartBodyFormat(array $data) {
        return c::collect($data)->map(function ($value, $key) {
            return is_array($value) ? $value : ['name' => $key, 'contents' => $value];
        })->values()->all();
    }

    /**
     * Send an asynchronous request to the given URL.
     *
     * @param string $method
     * @param string $url
     * @param array  $options
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    protected function makePromise(string $method, string $url, array $options = [], int $attempt = 1) {
        return $this->promise = $this->sendRequest($method, $url, $options)
            ->then(function (MessageInterface $message) {
                return c::tap($this->newResponse($message), function ($response) {
                    $this->populateResponse($response);
                    $this->dispatchResponseReceivedEvent($response);
                });
            })
            ->otherwise(function ($e) {
                if ($e instanceof CHTTP_Client_Exception_StrayRequestException) {
                    throw $e;
                }

                if ($e instanceof ConnectException || ($e instanceof RequestException && !$e->hasResponse())) {
                    $exception = new CHTTP_Client_Exception_ConnectionException($e->getMessage(), 0, $e);

                    $this->dispatchConnectionFailedEvent(new CHTTP_Client_Request($e->getRequest()), $exception);

                    return $exception;
                }

                return $e instanceof RequestException && $e->hasResponse() ? $this->populateResponse($this->newResponse($e->getResponse())) : $e;
            })
            ->then(function ($response) use ($method, $url, $options, $attempt) {
                return $this->handlePromiseResponse($response, $method, $url, $options, $attempt);
            });
    }

    /**
     * Handle the response of an asynchronous request.
     *
     * @param \CHTTP_Client_Response $response
     * @param string                 $method
     * @param string                 $url
     * @param array                  $options
     * @param int                    $attempt
     *
     * @return mixed
     */
    protected function handlePromiseResponse($response, $method, $url, $options, $attempt) {
        if ($response instanceof CHTTP_Client_Response && $response->successful()) {
            return $response;
        }

        if ($response instanceof RequestException) {
            $response = $this->populateResponse($this->newResponse($response->getResponse()));
        }

        try {
            $shouldRetry = $this->retryWhenCallback ? call_user_func(
                $this->retryWhenCallback,
                $response instanceof CHTTP_Client_Response ? $response->toException() : $response,
                $this
            ) : true;
        } catch (Exception $exception) {
            return $exception;
        }

        if ($attempt < $this->tries && $shouldRetry) {
            $options['delay'] = c::value(
                $this->retryDelay,
                $attempt,
                $response instanceof CHTTP_Client_Response ? $response->toException() : $response
            );

            return $this->makePromise($method, $url, $options, $attempt + 1);
        }

        if ($response instanceof CHTTP_Client_Response
            && $this->throwCallback
            && ($this->throwIfCallback === null || call_user_func($this->throwIfCallback, $response))
        ) {
            try {
                $response->throw($this->throwCallback);
            } catch (Exception $exception) {
                return $exception;
            }
        }

        if ($this->tries > 1 && $this->retryThrow) {
            return $response instanceof CHTTP_Client_Response ? $response->toException() : $response;
        }

        return $response;
    }

    /**
     * Send a request either synchronously or asynchronously.
     *
     * @param string $method
     * @param string $url
     * @param array  $options
     *
     * @throws \Exception
     *
     * @return \Psr\Http\Message\MessageInterface|\GuzzleHttp\Promise\PromiseInterface
     */
    protected function sendRequest(string $method, string $url, array $options = []) {
        $clientMethod = $this->async ? 'requestAsync' : 'request';

        $cfData = $this->parseRequestData($method, $url, $options);
        $onStats = function ($transferStats) {
            if (($callback = ($this->options['on_stats'] ?? false)) instanceof Closure) {
                $transferStats = $callback($transferStats) ?: $transferStats;
            }

            $this->transferStats = $transferStats;
        };

        $mergedOptions = $this->normalizeRequestOptions($this->mergeOptions([
            'cf_data' => $cfData,
            'on_stats' => $onStats,
        ], $options));

        return $this->buildClient()->$clientMethod($method, $url, $mergedOptions);
    }

    /**
     * Get the request data as an array so that we can attach it to the request for convenient assertions.
     *
     * @param string $method
     * @param string $url
     * @param array  $options
     *
     * @return array
     */
    protected function parseRequestData($method, $url, array $options) {
        if ($this->bodyFormat === 'body') {
            return [];
        }
        $cfData = $options[$this->bodyFormat] ?? $options['query'] ?? [];

        $urlString = cstr::of($url);

        if (empty($cfData) && $method === 'GET' && $urlString->contains('?')) {
            $cfData = (string) $urlString->after('?');
        }

        if (is_string($cfData)) {
            parse_str($cfData, $parsedData);

            $cfData = is_array($parsedData) ? $parsedData : [];
        }

        if ($cfData instanceof JsonSerializable) {
            $cfData = $cfData->jsonSerialize();
        }

        return is_array($cfData) ? $cfData : [];
    }

    /**
     * Normalize the given request options.
     *
     * @param array $options
     *
     * @return array
     */
    protected function normalizeRequestOptions(array $options) {
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $options[$key] = $this->normalizeRequestOptions($value);
            } elseif ($value instanceof CBase_String) {
                $options[$key] = $value->toString();
            }
        }

        return $options;
    }

    /**
     * Populate the given response with additional data.
     *
     * @param \CHTTP_Client_Response $response
     *
     * @return \CHTTP_Client_Response
     */
    protected function populateResponse(CHTTP_Client_Response $response) {
        $response->cookies = $this->cookies;

        $response->transferStats = $this->transferStats;

        return $response;
    }

    /**
     * Build the Guzzle client.
     *
     * @return \GuzzleHttp\Client
     */
    public function buildClient() {
        return $this->client ?? $this->createClient($this->buildHandlerStack());
    }

    /**
     * Determine if a reusable client is required.
     *
     * @return bool
     */
    protected function requestsReusableClient() {
        return !is_null($this->client) || $this->async;
    }

    /**
     * Retrieve a reusable Guzzle client.
     *
     * @return \GuzzleHttp\Client
     */
    protected function getReusableClient() {
        return $this->client = ($this->client ?: $this->createClient($this->buildHandlerStack()));
    }

    /**
     * Create new Guzzle client.
     *
     * @param \GuzzleHttp\HandlerStack $handlerStack
     *
     * @return \GuzzleHttp\Client
     */
    public function createClient($handlerStack) {
        return new Client([
            'handler' => $handlerStack,
            'cookies' => true,
        ]);
    }

    /**
     * Build the Guzzle client handler stack.
     *
     * @return \GuzzleHttp\HandlerStack
     */
    public function buildHandlerStack() {
        return $this->pushHandlers(HandlerStack::create($this->handler));
    }

    /**
     * Add the necessary handlers to the given handler stack.
     *
     * @param \GuzzleHttp\HandlerStack $handlerStack
     *
     * @return \GuzzleHttp\HandlerStack
     */
    public function pushHandlers($handlerStack) {
        return c::tap($handlerStack, function ($stack) {
            $this->middleware->each(function ($middleware) use ($stack) {
                $stack->push($middleware);
            });
            $stack->push($this->buildBeforeSendingHandler());
            $stack->push($this->buildRecorderHandler());

            $stack->push($this->buildStubHandler());
        });
    }

    /**
     * Build the before sending handler.
     *
     * @return \Closure
     */
    public function buildBeforeSendingHandler() {
        return function ($handler) {
            return function ($request, $options) use ($handler) {
                return $handler($this->runBeforeSendingCallbacks($request, $options), $options);
            };
        };
    }

    /**
     * Build the recorder handler.
     *
     * @return \Closure
     */
    public function buildRecorderHandler() {
        return function ($handler) {
            return function ($request, $options) use ($handler) {
                $promise = $handler($request, $options);

                return $promise->then(function ($response) use ($request, $options) {
                    c::optional($this->factory)->recordRequestResponsePair(
                        (new CHTTP_Client_Request($request))->withData($options['cf_data']),
                        $this->newResponse($response)
                    );

                    return $response;
                });
            };
        };
    }

    /**
     * Build the stub handler.
     *
     * @return \Closure
     */
    public function buildStubHandler() {
        return function ($handler) {
            return function ($request, $options) use ($handler) {
                $response = ($this->stubCallbacks ?: c::collect())
                    ->map
                    ->__invoke((new CHTTP_Client_Request($request))->withData($options['cf_data']), $options)
                    ->filter()
                    ->first();

                if (is_null($response)) {
                    if ($this->preventStrayRequests) {
                        throw new CHTTP_Client_Exception_StrayRequestException((string) $request->getUri());
                    }

                    return $handler($request, $options);
                }

                $response = is_array($response) ? CHTTP_Client::response($response) : $response;

                $sink = $options['sink'] ?? null;

                if ($sink) {
                    $response->then($this->sinkStubHandler($sink));
                }

                return $response;
            };
        };
    }

    /**
     * Get the sink stub handler callback.
     *
     * @param string $sink
     *
     * @return \Closure
     */
    protected function sinkStubHandler($sink) {
        return function ($response) use ($sink) {
            $body = $response->getBody()->getContents();

            if (is_string($sink)) {
                file_put_contents($sink, $body);

                return;
            }

            fwrite($sink, $body);
            rewind($sink);
        };
    }

    /**
     * Execute the "before sending" callbacks.
     *
     * @param \GuzzleHttp\Psr7\RequestInterface $request
     * @param array                             $options
     *
     * @return \Closure
     */
    public function runBeforeSendingCallbacks($request, array $options) {
        return c::tap($request, function (&$request) use ($options) {
            $this->beforeSendingCallbacks->each(function ($callback) use (&$request, $options) {
                $callbackResult = call_user_func(
                    $callback,
                    (new CHTTP_Client_Request($request))->withData($options['cf_data']),
                    $options,
                    $this
                );

                if ($callbackResult instanceof RequestInterface) {
                    $request = $callbackResult;
                } elseif ($callbackResult instanceof CHTTP_Client_Request) {
                    $request = $callbackResult->toPsrRequest();
                }
            });
        });
    }

    /**
     * Replace the given options with the current request options.
     *
     * @param array $options
     *
     * @return array
     */
    public function mergeOptions(...$options) {
        return array_replace_recursive(
            array_merge_recursive($this->options, carr::only($options, $this->mergableOptions)),
            ...$options
        );
    }

    /**
     * Create a new response instance using the given PSR response.
     *
     * @param \Psr\Http\Message\MessageInterface $response
     *
     * @return CHTTP_Client_Response
     */
    protected function newResponse($response) {
        return c::tap(new CHTTP_Client_Response($response), function (CHTTP_Client_Response $cfResponse) {
            if ($this->truncateExceptionsAt === null) {
                return;
            }

            $this->truncateExceptionsAt === false
                ? $cfResponse->dontTruncateExceptions()
                : $cfResponse->truncateExceptionsAt($this->truncateExceptionsAt);
        });
    }

    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function stub($callback) {
        $this->stubCallbacks = c::collect($callback);

        return $this;
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
     * Toggle asynchronicity in requests.
     *
     * @param bool $async
     *
     * @return $this
     */
    public function async(bool $async = true) {
        $this->async = $async;

        return $this;
    }

    /**
     * Retrieve the pending request promise.
     *
     * @return null|\GuzzleHttp\Promise\PromiseInterface
     */
    public function getPromise() {
        return $this->promise;
    }

    /**
     * Dispatch the RequestSending event if a dispatcher is available.
     *
     * @return void
     */
    protected function dispatchRequestSendingEvent() {
        if ($dispatcher = c::optional($this->factory)->getDispatcher()) {
            $dispatcher->dispatch(new CHTTP_Client_Event_RequestSending($this->request));
        }
    }

    /**
     * Dispatch the ResponseReceived event if a dispatcher is available.
     *
     * @param \CHTTP_Client_Response $response
     *
     * @return void
     */
    protected function dispatchResponseReceivedEvent(CHTTP_Client_Response $response) {
        if (!($dispatcher = c::optional($this->factory)->getDispatcher())
            || !$this->request
        ) {
            return;
        }

        $dispatcher->dispatch(new CHTTP_Client_Event_ResponseReceived($this->request, $response));
    }

    /**
     * Dispatch the ConnectionFailed event if a dispatcher is available.
     *
     * @return void
     */
    protected function dispatchConnectionFailedEvent() {
        if ($dispatcher = c::optional($this->factory)->getDispatcher()) {
            $dispatcher->dispatch(new CHTTP_Client_Event_ConnectionFailed($this->request));
        }
    }

    /**
     * Indicate that request exceptions should be truncated to the given length.
     *
     * @param int<1, max> $length
     *
     * @return $this
     */
    public function truncateExceptionsAt(int $length) {
        $this->truncateExceptionsAt = $length;

        return $this;
    }

    /**
     * Indicate that request exceptions should not be truncated.
     *
     * @return $this
     */
    public function dontTruncateExceptions() {
        $this->truncateExceptionsAt = false;

        return $this;
    }

    /**
     * Handle the given connection exception.
     *
     * @param \GuzzleHttp\Exception\ConnectException $e
     *
     * @return void
     */
    protected function marshalConnectionException(ConnectException $e) {
        $exception = new CHTTP_Client_Exception_ConnectionException($e->getMessage(), 0, $e);

        $request = new CHTTP_Client_Request($e->getRequest());

        c::optional($this->factory)->recordRequestResponsePair(
            $request,
            null
        );

        $this->dispatchConnectionFailedEvent($request, $exception);

        throw $exception;
    }

    /**
     * Handle the given request exception.
     *
     * @param \GuzzleHttp\Exception\RequestException $e
     *
     * @return void
     */
    protected function marshalRequestExceptionWithoutResponse(RequestException $e) {
        $exception = new CHTTP_Client_Exception_ConnectionException($e->getMessage(), 0, $e);

        $request = new CHTTP_Client_Request($e->getRequest());

        c::optional($this->factory)->recordRequestResponsePair(
            $request,
            null
        );

        $this->dispatchConnectionFailedEvent($request, $exception);

        throw $exception;
    }

    /**
     * Handle the given request exception.
     *
     * @param \GuzzleHttp\Exception\RequestException $e
     *
     * @return void
     */
    protected function marshalRequestExceptionWithResponse(RequestException $e) {
        $response = $this->populateResponse($this->newResponse($e->getResponse()));

        c::optional($this->factory)->recordRequestResponsePair(
            new CHTTP_Client_Request($e->getRequest()),
            $response
        );

        throw $response->toException() ?? new CHTTP_Client_Exception_ConnectionException($e->getMessage(), 0, $e);
    }

    /**
     * Set the client instance.
     *
     * @param \GuzzleHttp\Client $client
     *
     * @return $this
     */
    public function setClient(Client $client) {
        $this->client = $client;

        return $this;
    }

    /**
     * Create a new client instance using the given handler.
     *
     * @param callable $handler
     *
     * @return $this
     */
    public function setHandler($handler) {
        $this->handler = $handler;

        return $this;
    }

    /**
     * Get the pending request options.
     *
     * @return array
     */
    public function getOptions() {
        return $this->options;
    }

    public function getBodyFormat() {
        return $this->bodyFormat;
    }
}
