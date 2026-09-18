<?php

class CHTTP_Client_Request implements ArrayAccess {
    use CTrait_Macroable;

    /**
     * The underlying PSR request.
     *
     * @var \Psr\Http\Message\RequestInterface
     */
    protected $request;

    /**
     * The decoded payload for the request.
     *
     * @var null|array
     */
    protected $data;

    /**
     * Create a new request instance.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     *
     * @return void
     */
    public function __construct($request) {
        $this->request = $request;
    }

    /**
     * Get the request method.
     *
     * @return string
     */
    public function method() {
        return $this->request->getMethod();
    }

    /**
     * Get the URL of the request.
     *
     * @return string
     */
    public function url() {
        return (string) $this->request->getUri();
    }

    /**
     * Determine if the request has a given header.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    public function hasHeader($key, $value = null) {
        if (is_null($value)) {
            return !empty($this->request->getHeaders()[$key]);
        }

        $headers = $this->headers();

        if (!carr::has($headers, $key)) {
            return false;
        }

        $value = is_array($value) ? $value : [$value];

        return empty(array_diff($value, $headers[$key]));
    }

    /**
     * Determine if the request has the given headers.
     *
     * @param array|string $headers
     *
     * @return bool
     */
    public function hasHeaders($headers) {
        if (is_string($headers)) {
            $headers = [$headers => null];
        }

        foreach ($headers as $key => $value) {
            if (!$this->hasHeader($key, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the values for the header with the given name.
     *
     * @param string $key
     *
     * @return array
     */
    public function header($key) {
        return carr::get($this->headers(), $key, []);
    }

    /**
     * Get the request headers.
     *
     * @return array
     */
    public function headers() {
        return $this->request->getHeaders();
    }

    /**
     * Get the body of the request.
     *
     * @return string
     */
    public function body() {
        return (string) $this->request->getBody();
    }

    /**
     * Determine if the request contains the given file.
     *
     * @param string      $name
     * @param null|string $value
     * @param null|string $filename
     *
     * @return bool
     */
    public function hasFile($name, $value = null, $filename = null) {
        if (!$this->isMultipart()) {
            return false;
        }

        return c::collect($this->data)->reject(function ($file) use ($name, $value, $filename) {
            return $file['name'] != $name
                || ($value && $file['contents'] != $value)
                || ($filename && $file['filename'] != $filename);
        })->count() > 0;
    }

    /**
     * Get the request's data (form parameters or JSON).
     *
     * @return array
     */
    public function data() {
        if ($this->isForm()) {
            return $this->parameters();
        } elseif ($this->isJson()) {
            return $this->json();
        }

        return $this->data ?? [];
    }

    /**
     * Get the request's form parameters.
     *
     * @return array
     */
    protected function parameters() {
        if (!$this->data) {
            parse_str($this->body(), $parameters);

            $this->data = $parameters;
        }

        return $this->data;
    }

    /**
     * Get the JSON decoded body of the request.
     *
     * @return array
     */
    protected function json() {
        if (!$this->data) {
            $this->data = json_decode($this->body(), true);
        }

        return $this->data;
    }

    /**
     * Determine if the request is simple form data.
     *
     * @return bool
     */
    public function isForm() {
        return $this->hasHeader('Content-Type', 'application/x-www-form-urlencoded');
    }

    /**
     * Determine if the request is JSON.
     *
     * @return bool
     */
    public function isJson() {
        return $this->hasHeader('Content-Type')
               && cstr::contains($this->header('Content-Type')[0], 'json');
    }

    /**
     * Determine if the request is multipart.
     *
     * @return bool
     */
    public function isMultipart() {
        return $this->hasHeader('Content-Type')
               && cstr::contains($this->header('Content-Type')[0], 'multipart');
    }

    /**
     * Set the decoded data on the request.
     *
     * @param array $data
     *
     * @return $this
     */
    public function withData(array $data) {
        $this->data = $data;

        return $this;
    }

    /**
     * Get the underlying PSR compliant request instance.
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    public function toPsrRequest() {
        return $this->request;
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool {
        return isset($this->data()[$offset]);
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
        return $this->data()[$offset];
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
        throw new LogicException('Request data may not be mutated using array access.');
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
        throw new LogicException('Request data may not be mutated using array access.');
    }
}
