<?php

/**
 * Description of Redirector.
 */
final class CHTTP_Redirector {
    use CTrait_Macroable;

    /**
     * The URL generator instance.
     *
     * @var CRouting_UrlGenerator
     */
    protected $generator;

    /**
     * The session store instance.
     *
     * @var CSession_Store
     */
    protected $session;

    protected static $instance;

    private function __construct() {
        $this->generator = CRouting::urlGenerator();
        $this->session = CSession::instance()->store();
    }

    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Create a new redirect response to the "home" route.
     *
     * @param int $status
     *
     * @return CHTTP_RedirectResponse
     */
    public function home($status = 302) {
        return $this->to($this->generator->route('home'), $status);
    }

    /**
     * Create a new redirect response to the previous location.
     *
     * @param int   $status
     * @param array $headers
     * @param mixed $fallback
     *
     * @return CHTTP_RedirectResponse
     */
    public function back($status = 302, $headers = [], $fallback = false) {
        return $this->createRedirect($this->generator->previous($fallback), $status, $headers);
    }

    /**
     * Create a new redirect response to the current URI.
     *
     * @param int   $status
     * @param array $headers
     *
     * @return CHTTP_RedirectResponse
     */
    public function refresh($status = 302, $headers = []) {
        return $this->to($this->generator->getRequest()->path(), $status, $headers);
    }

    /**
     * Create a new redirect response, while putting the current URL in the session.
     *
     * @param string    $path
     * @param int       $status
     * @param array     $headers
     * @param null|bool $secure
     *
     * @return CHTTP_RedirectResponse
     */
    public function guest($path, $status = 302, $headers = [], $secure = null) {
        $request = $this->generator->getRequest();

        $intended = $request->method() === 'GET' && $request->route() && !$request->expectsJson() ? $this->generator->full() : $this->generator->previous();

        if ($intended) {
            $this->setIntendedUrl($intended);
        }

        return $this->to($path, $status, $headers, $secure);
    }

    /**
     * Create a new redirect response to the previously intended location.
     *
     * @param string    $default
     * @param int       $status
     * @param array     $headers
     * @param null|bool $secure
     *
     * @return CHTTP_RedirectResponse
     */
    public function intended($default = '/', $status = 302, $headers = [], $secure = null) {
        $path = $this->session->pull('url.intended', $default);

        return $this->to($path, $status, $headers, $secure);
    }

    /**
     * Set the intended url.
     *
     * @param string $url
     *
     * @return void
     */
    public function setIntendedUrl($url) {
        $this->session->put('url.intended', $url);
    }

    /**
     * Create a new redirect response to the given path.
     *
     * @param string    $path
     * @param int       $status
     * @param array     $headers
     * @param null|bool $secure
     *
     * @return CHTTP_RedirectResponse
     */
    public function to($path, $status = 302, $headers = [], $secure = null) {
        return $this->createRedirect($this->generator->to($path, [], $secure), $status, $headers);
    }

    /**
     * Create a new redirect response to an external URL (no validation).
     *
     * @param string $path
     * @param int    $status
     * @param array  $headers
     *
     * @return CHTTP_RedirectResponse
     */
    public function away($path, $status = 302, $headers = []) {
        return $this->createRedirect($path, $status, $headers);
    }

    /**
     * Create a new redirect response to the given HTTPS path.
     *
     * @param string $path
     * @param int    $status
     * @param array  $headers
     *
     * @return CHTTP_RedirectResponse
     */
    public function secure($path, $status = 302, $headers = []) {
        return $this->to($path, $status, $headers, true);
    }

    /**
     * Create a new redirect response to a named route.
     *
     * @param string $route
     * @param mixed  $parameters
     * @param int    $status
     * @param array  $headers
     *
     * @return CHTTP_RedirectResponse
     */
    public function route($route, $parameters = [], $status = 302, $headers = []) {
        return $this->to($this->generator->route($route, $parameters), $status, $headers);
    }

    /**
     * Create a new redirect response to a signed named route.
     *
     * @param string                                    $route
     * @param mixed                                     $parameters
     * @param null|\DateTimeInterface|\DateInterval|int $expiration
     * @param int                                       $status
     * @param array                                     $headers
     *
     * @return CHTTP_RedirectResponse
     */
    public function signedRoute($route, $parameters = [], $expiration = null, $status = 302, $headers = []) {
        return $this->to($this->generator->signedRoute($route, $parameters, $expiration), $status, $headers);
    }

    /**
     * Create a new redirect response to a signed named route.
     *
     * @param string                                    $route
     * @param null|\DateTimeInterface|\DateInterval|int $expiration
     * @param mixed                                     $parameters
     * @param int                                       $status
     * @param array                                     $headers
     *
     * @return CHTTP_RedirectResponse
     */
    public function temporarySignedRoute($route, $expiration, $parameters = [], $status = 302, $headers = []) {
        return $this->to($this->generator->temporarySignedRoute($route, $expiration, $parameters), $status, $headers);
    }

    /**
     * Create a new redirect response to a controller action.
     *
     * @param string|array $action
     * @param mixed        $parameters
     * @param int          $status
     * @param array        $headers
     *
     * @return CHTTP_RedirectResponse
     */
    public function action($action, $parameters = [], $status = 302, $headers = []) {
        return $this->to($this->generator->action($action, $parameters), $status, $headers);
    }

    /**
     * Create a new redirect response.
     *
     * @param string $path
     * @param int    $status
     * @param array  $headers
     *
     * @return CHTTP_RedirectResponse
     */
    protected function createRedirect($path, $status, $headers) {
        return c::tap(new CHTTP_RedirectResponse($path, $status, $headers), function ($redirect) {
            $redirect->setRequest($this->generator->getRequest());
        });
    }

    /**
     * Get the URL generator instance.
     *
     * @return CRouting_UrlGenerator
     */
    public function getUrlGenerator() {
        return $this->generator;
    }

    /**
     * Set the active session store.
     *
     * @param CSession_Store $session
     *
     * @return void
     */
    public function setSession(CSession_Store $session) {
        $this->session = $session;
    }
}
