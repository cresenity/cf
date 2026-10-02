<?php

use Symfony\Component\HttpFoundation\HeaderBag;

trait CHTTP_Trait_ResponseTrait {
    /**
     * The original content of the response.
     *
     * @var mixed
     */
    public $original;

    /**
     * The exception that triggered the error response (if applicable).
     *
     * @var null|\Throwable
     */
    public $exception;

    /**
     * Get the status code for the response.
     *
     * @return int
     */
    public function status() {
        return $this->getStatusCode();
    }

    /**
     * Get the content of the response.
     *
     * @return string
     */
    public function content() {
        return $this->getContent();
    }

    /**
     * Get the original response content.
     *
     * @return mixed
     */
    public function getOriginalContent() {
        $original = $this->original;

        return $original instanceof self ? $original->{__FUNCTION__}() : $original;
    }

    /**
     * Set a header on the Response.
     *
     * @param string       $key
     * @param array|string $values
     * @param bool         $replace
     *
     * @return $this
     */
    public function header($key, $values, $replace = true) {
        $this->headers->set($key, $values, $replace);

        return $this;
    }

    /**
     * Add an array of headers to the response.
     *
     * @param \Symfony\Component\HttpFoundation\HeaderBag|array $headers
     *
     * @return $this
     */
    public function withHeaders($headers) {
        if ($headers instanceof HeaderBag) {
            $headers = $headers->all();
        }

        foreach ($headers as $key => $value) {
            $this->headers->set($key, $value);
        }

        return $this;
    }

    /**
     * Add a cookie to the response.
     *
     * @param \Symfony\Component\HttpFoundation\Cookie|mixed $cookie
     *
     * @return $this
     */
    public function cookie($cookie) {
        return call_user_func_array([$this, 'withCookie'], func_get_args());
    }

    /**
     * Add a cookie to the response.
     *
     * @param \Symfony\Component\HttpFoundation\Cookie|mixed $cookie
     *
     * @return $this
     */
    public function withCookie($cookie) {
        if (is_string($cookie) && function_exists('cookie')) {
            $cookie = call_user_func_array('cookie', func_get_args());
        }

        $this->headers->setCookie($cookie);

        return $this;
    }

    /**
     * Remove a header(s) from the response.
     *
     * @param array|string $key
     *
     * @return $this
     */
    public function withoutHeader($key) {
        foreach ((array) $key as $header) {
            $this->headers->remove($header);
        }

        return $this;
    }

    /**
     * Add multiple cookies to the response.
     *
     * @param array $cookies
     *
     * @return $this
     */
    public function withCookies(array $cookies) {
        foreach ($cookies as $cookie) {
            $this->headers->setCookie($cookie);
        }

        return $this;
    }

    /**
     * Expire a cookie when sending the response.
     *
     * @param \Symfony\Component\HttpFoundation\Cookie|string $cookie
     * @param null|string                                       $path
     * @param null|string                                       $domain
     *
     * @return $this
     */
    public function withoutCookie($cookie, $path = null, $domain = null) {
        if (is_string($cookie)) {
            $cookie = CHTTP::cookie()->forget($cookie, $path, $domain);
        }

        $this->headers->setCookie($cookie);

        return $this;
    }

    /**
     * Expire multiple cookies when sending the response.
     *
     * @param array       $cookies
     * @param null|string $path
     * @param null|string $domain
     *
     * @return $this
     */
    public function withoutCookies(array $cookies, $path = null, $domain = null) {
        foreach ($cookies as $cookie) {
            $this->withoutCookie($cookie, $path, $domain);
        }

        return $this;
    }

    /**
     * Get the status text for the response.
     *
     * @return string
     */
    public function statusText() {
        return $this->statusText;
    }

    /**
     * Get the callback of the response.
     *
     * @return null|string
     */
    public function getCallback() {
        return isset($this->callback) ? $this->callback : null;
    }

    /**
     * Set the exception to attach to the response.
     *
     * @param \Throwable $e
     *
     * @return $this
     */
    public function withException($e) {
        $this->exception = $e;

        return $this;
    }

    /**
     * Throws the response in a HttpResponseException instance.
     *
     * @throws CHTTP_Exception_ResponseException
     *
     * @return void
     */
    public function throwResponse() {
        throw new CHTTP_Exception_ResponseException($this);
    }
}
