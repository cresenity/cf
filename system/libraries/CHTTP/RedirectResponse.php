<?php

/**
 * Description of RedirectResponse.
 */
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse as BaseRedirectResponse;

class CHTTP_RedirectResponse extends BaseRedirectResponse {
    use CTrait_ForwardsCalls;
    use CHTTP_Trait_ResponseTrait;
    use CTrait_Macroable {
        CTrait_Macroable::__call as macroCall;
    }

    /**
     * The request instance.
     *
     * @var CHTTP_Request
     */
    protected $request;

    /**
     * Flash a piece of data to the session.
     *
     * @param string|array $key
     * @param mixed        $value
     *
     * @return $this
     */
    public function with($key, $value = null) {
        $key = is_array($key) ? $key : [$key => $value];

        foreach ($key as $k => $v) {
            $this->session()->flash($k, $v);
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
     * Flash an array of input to the session.
     *
     * @param null|array $input
     *
     * @return $this
     */
    public function withInput(?array $input = null) {
        $this->session()->flashInput($this->removeFilesFromInput(
            !is_null($input) ? $input : $this->request->input()
        ));

        return $this;
    }

    /**
     * Remove all uploaded files form the given input array.
     *
     * @param array $input
     *
     * @return array
     */
    protected function removeFilesFromInput(array $input) {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->removeFilesFromInput($value);
            }

            if ($value instanceof SymfonyUploadedFile) {
                unset($input[$key]);
            }
        }

        return $input;
    }

    /**
     * Flash an array of input to the session.
     *
     * @return $this
     */
    public function onlyInput() {
        return $this->withInput($this->request->only(func_get_args()));
    }

    /**
     * Flash an array of input to the session.
     *
     * @return $this
     */
    public function exceptInput() {
        return $this->withInput($this->request->except(func_get_args()));
    }

    /**
     * Flash a container of errors to the session.
     *
     * @param CBase_MessageProviderInterface|array|string $provider
     * @param string                                      $key
     *
     * @return $this
     */
    public function withErrors($provider, $key = 'default') {
        $value = $this->parseErrors($provider);

        $errors = $this->session()->get('errors', new CBase_ViewErrorBag());

        if (!$errors instanceof CBase_ViewErrorBag) {
            $errors = new CBase_ViewErrorBag();
        }

        $this->session()->flash(
            'errors',
            $errors->put($key, $value)
        );

        return $this;
    }

    /**
     * Add a fragment identifier to the URL.
     *
     * @param string $fragment
     *
     * @return $this
     */
    public function withFragment($fragment) {
        return $this->withoutFragment()
            ->setTargetUrl($this->getTargetUrl() . '#' . cstr::after($fragment, '#'));
    }

    /**
     * Remove any fragment identifier from the response URL.
     *
     * @return $this
     */
    public function withoutFragment() {
        return $this->setTargetUrl(cstr::before($this->getTargetUrl(), '#'));
    }

    /**
     * Parse the given errors into an appropriate value.
     *
     * @param CBase_MessageProviderInterface|array|string $provider
     *
     * @return CBase_MessageBag
     */
    protected function parseErrors($provider) {
        if ($provider instanceof CBase_MessageProviderInterface) {
            return $provider->getMessageBag();
        }

        return new CBase_MessageBag((array) $provider);
    }

    /**
     * Get the original response content.
     *
     * @return null
     */
    public function getOriginalContent() {
        return null;
    }

    /**
     * Get the request instance.
     *
     * @return null|CHTTP_Request
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * Set the request instance.
     *
     * @param CHTTP_Request $request
     *
     * @return void
     */
    public function setRequest(CHTTP_Request $request) {
        $this->request = $request;
    }

    /**
     * Dynamically bind flash data in the session.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (cstr::startsWith($method, 'with')) {
            return $this->with(cstr::snake(substr($method, 4)), $parameters[0]);
        }

        static::throwBadMethodCallException($method);
    }

    /**
     * Get session instance.
     *
     * @return CSession_Store
     */
    public function session() {
        return c::session();
    }
}
