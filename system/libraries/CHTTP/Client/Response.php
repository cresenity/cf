<?php
use GuzzleHttp\Psr7\StreamWrapper;

class CHTTP_Client_Response implements ArrayAccess {
    use CHTTP_Client_Trait_DeterminesStatusCodeTrait;
    use CTrait_Macroable {
        __call as macroCall;
    }

    /**
     * The request cookies.
     *
     * @var \GuzzleHttp\Cookie\CookieJar
     */
    public $cookies;

    /**
     * The transfer stats for the request.
     *
     * @var null|\GuzzleHttp\TransferStats
     */
    public $transferStats;

    /**
     * The underlying PSR response.
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;

    /**
     * The decoded JSON response.
     *
     * @var array
     */
    protected $decoded;

    /**
     * The length at which request exceptions will be truncated.
     *
     * @var null|int<1, max>|false
     */
    protected $truncateExceptionsAt = null;

    /**
     * Create a new response instance.
     *
     * @param \Psr\Http\Message\MessageInterface $response
     *
     * @return void
     */
    public function __construct($response) {
        $this->response = $response;
    }

    /**
     * Get the body of the response.
     *
     * @return string
     */
    public function body() {
        return (string) $this->response->getBody();
    }

    /**
     * Get the JSON decoded body of the response as an array or scalar value.
     *
     * @param null|string $key
     * @param mixed       $default
     *
     * @return mixed
     */
    public function json($key = null, $default = null) {
        if (!$this->decoded) {
            $this->decoded = json_decode($this->body(), true);
        }

        if (is_null($key)) {
            return $this->decoded;
        }

        return c::get($this->decoded, $key, $default);
    }

    /**
     * Get the JSON decoded body of the response as an object.
     *
     * @return object
     */
    public function object() {
        return json_decode($this->body(), false);
    }

    /**
     * Get the JSON decoded body of the response as a collection.
     *
     * @param null|string $key
     *
     * @return \CCollection
     */
    public function collect($key = null) {
        return CCollection::make($this->json($key));
    }

    /**
     * Get the JSON decoded body of the response as a fluent object.
     *
     * @param null|string $key
     *
     * @return \CBase_Fluent
     */
    public function fluent($key = null) {
        return new CBase_Fluent((array) $this->json($key));
    }

    /**
     * Get the body of the response as a PHP resource.
     *
     * @throws \InvalidArgumentException
     *
     * @return resource
     */
    public function resource() {
        return StreamWrapper::getResource($this->response->getBody());
    }

    /**
     * Get a header from the response.
     *
     * @param string $header
     *
     * @return string
     */
    public function header(string $header) {
        return $this->response->getHeaderLine($header);
    }

    /**
     * Get the headers from the response.
     *
     * @return array
     */
    public function headers() {
        return $this->response->getHeaders();
    }

    /**
     * Get the status code of the response.
     *
     * @return int
     */
    public function status() {
        return (int) $this->response->getStatusCode();
    }

    /**
     * Get the reason phrase of the response.
     *
     * @return string
     */
    public function reason() {
        return $this->response->getReasonPhrase();
    }

    /**
     * Get the effective URI of the response.
     *
     * @return null|\Psr\Http\Message\UriInterface
     */
    public function effectiveUri() {
        return c::optional($this->transferStats)->getEffectiveUri();
    }

    /**
     * Determine if the request was successful.
     *
     * @return bool
     */
    public function successful() {
        return $this->status() >= 200 && $this->status() < 300;
    }

    /**
     * Determine if the response was a redirect.
     *
     * @return bool
     */
    public function redirect() {
        return $this->status() >= 300 && $this->status() < 400;
    }

    /**
     * Determine if the response indicates a client or server error occurred.
     *
     * @return bool
     */
    public function failed() {
        return $this->serverError() || $this->clientError();
    }

    /**
     * Determine if the response indicates a client error occurred.
     *
     * @return bool
     */
    public function clientError() {
        return $this->status() >= 400 && $this->status() < 500;
    }

    /**
     * Determine if the response indicates a server error occurred.
     *
     * @return bool
     */
    public function serverError() {
        return $this->status() >= 500;
    }

    /**
     * Execute the given callback if there was a server or client error.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function onError(callable $callback) {
        if ($this->failed()) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Get the response cookies.
     *
     * @return \GuzzleHttp\Cookie\CookieJar
     */
    public function cookies() {
        return $this->cookies;
    }

    /**
     * Get the handler stats of the response.
     *
     * @return array
     */
    public function handlerStats() {
        return c::optional($this->transferStats)->getHandlerStats() ?? [];
    }

    /**
     * Close the stream and any underlying resources.
     *
     * @return $this
     */
    public function close() {
        $this->response->getBody()->close();

        return $this;
    }

    /**
     * Get the underlying PSR response for the response.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function toPsrResponse() {
        return $this->response;
    }

    /**
     * Create an exception if a server or client error occurred.
     *
     * @return null|\CHTTP_Client_Exception_RequestException
     */
    public function toException() {
        if ($this->failed()) {
            $originalTruncateAt = CHTTP_Client_Exception_RequestException::$truncateAt;

            try {
                if ($this->truncateExceptionsAt !== null) {
                    $this->truncateExceptionsAt === false
                        ? CHTTP_Client_Exception_RequestException::dontTruncate()
                        : CHTTP_Client_Exception_RequestException::truncateAt($this->truncateExceptionsAt);
                }

                return new CHTTP_Client_Exception_RequestException($this);
            } finally {
                CHTTP_Client_Exception_RequestException::$truncateAt = $originalTruncateAt;
            }
        }
    }

    /**
     * Throw an exception if a server or client error occurred.
     *
     * //@param null|\Closure $callback
     *
     * @throws \CHTTP_Client_Exception_RequestException
     *
     * @return $this
     */
    public function throw() {
        $callback = func_get_args()[0] ?? null;

        if ($this->failed()) {
            throw c::tap($this->toException(), function ($exception) use ($callback) {
                if ($callback && is_callable($callback)) {
                    $callback($this, $exception);
                }
            });
        }

        return $this;
    }

    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to true.
     *
     * @param bool $condition
     *
     * @throws \CHTTP_Client_Exception_RequestException
     *
     * @return $this
     */
    public function throwIf($condition) {
        return c::value($condition, $this) ? $this->throw(func_get_args()[1] ?? null) : $this;
    }

    /**
     * Throw an exception if the response status code matches the given code.
     *
     * @param callable|int $statusCode
     *
     * @throws \CHTTP_Client_Exception_RequestException
     *
     * @return $this
     */
    public function throwIfStatus($statusCode) {
        if (is_callable($statusCode) && $statusCode($this->status(), $this)) {
            return $this->throw();
        }

        return $this->status() === $statusCode ? $this->throw() : $this;
    }

    /**
     * Throw an exception unless the response status code matches the given code.
     *
     * @param callable|int $statusCode
     *
     * @throws \CHTTP_Client_Exception_RequestException
     *
     * @return $this
     */
    public function throwUnlessStatus($statusCode) {
        if (is_callable($statusCode)) {
            return $statusCode($this->status(), $this) ? $this : $this->throw();
        }

        return $this->status() === $statusCode ? $this : $this->throw();
    }

    /**
     * Throw an exception if the response status code is a 4xx level code.
     *
     * @throws \CHTTP_Client_Exception_RequestException
     *
     * @return $this
     */
    public function throwIfClientError() {
        return $this->clientError() ? $this->throw() : $this;
    }

    /**
     * Throw an exception if the response status code is a 5xx level code.
     *
     * @throws \CHTTP_Client_Exception_RequestException
     *
     * @return $this
     */
    public function throwIfServerError() {
        return $this->serverError() ? $this->throw() : $this;
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
     * Dump the content from the response.
     *
     * @param null|string $key
     *
     * @return $this
     */
    public function dump($key = null) {
        $content = $this->body();

        $json = json_decode($content);

        if (json_last_error() === JSON_ERROR_NONE) {
            $content = $json;
        }

        if (!is_null($key)) {
            c::dump(c::get($content, $key));
        } else {
            c::dump($content);
        }

        return $this;
    }

    /**
     * Dump the content from the response and end the script.
     *
     * @param null|string $key
     *
     * @return never
     */
    public function dd($key = null) {
        $this->dump($key);

        exit(1);
    }

    /**
     * Dump the headers from the response.
     *
     * @return $this
     */
    public function dumpHeaders() {
        c::dump($this->headers());

        return $this;
    }

    /**
     * Dump the headers from the response and end the script.
     *
     * @return never
     */
    public function ddHeaders() {
        $this->dumpHeaders();

        exit(1);
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool {
        return isset($this->json()[$offset]);
    }

    /**
     * Get the value for a given offset.
     *
     * @param string $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->json()[$offset];
    }

    /**
     * Set the value at the given offset.
     *
     * @param string $offset
     * @param mixed  $value
     *
     * @throws \LogicException
     *
     * @return void
     */
    public function offsetSet($offset, $value): void {
        throw new LogicException('Response data may not be mutated using array access.');
    }

    /**
     * Unset the value at the given offset.
     *
     * @param string $offset
     *
     * @throws \LogicException
     *
     * @return void
     */
    public function offsetUnset($offset): void {
        throw new LogicException('Response data may not be mutated using array access.');
    }

    /**
     * Get the body of the response.
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString() {
        return $this->body();
    }

    /**
     * Dynamically proxy other methods to the underlying response.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        return static::hasMacro($method)
                    ? $this->macroCall($method, $parameters)
                    : $this->response->{$method}(...$parameters);
    }
}
