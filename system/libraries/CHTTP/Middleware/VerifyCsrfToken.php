<?php
use Symfony\Component\HttpFoundation\Cookie;

class CHTTP_Middleware_VerifyCsrfToken {
    use CTrait_Helper_InteractsWithTime;

    /**
     * The encrypter implementation.
     *
     * @var \CCrypt_EncrypterInterface
     */
    protected $encrypter;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [];

    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification for every instance.
     *
     * @var array
     */
    protected static $neverVerify = [];

    /**
     * Opt-in: let `Sec-Fetch-Site: same-origin` requests skip the token check.
     *
     * @var bool
     */
    protected static $trustSecFetchSite = false;

    /**
     * Opt-in: also trust `Sec-Fetch-Site: same-site` (only meaningful with $trustSecFetchSite).
     *
     * @var bool
     */
    protected static $allowSameSite = false;

    /**
     * Opt-in: verify by origin only and reject cross-origin requests without checking a token.
     *
     * @var bool
     */
    protected static $originOnly = false;

    /**
     * Create a new middleware instance.
     *
     * @return void
     */
    public function __construct() {
        $this->encrypter = CCrypt::encrypter();
    }

    /**
     * Handle an incoming request.
     *
     * @param \CHTTP_Request $request
     * @param \Closure       $next
     *
     * @throws \CSession_Exception_TokenMismatchException
     *
     * @return mixed
     */
    public function handle($request, Closure $next) {
        if ($this->isReading($request)
            || $this->runningUnitTests()
            || $this->inExceptArray($request)
            || $this->hasValidOrigin($request)
            || $this->tokensMatch($request)
        ) {
            return c::tap($next($request), function ($response) use ($request) {
                if ($this->shouldAddXsrfTokenCookie()) {
                    $this->addCookieToResponse($request, $response);
                }
            });
        }

        throw new CSession_Exception_TokenMismatchException('CSRF token mismatch.');
    }

    /**
     * Determine if the HTTP request uses a ‘read’ verb.
     *
     * @param \CHTTP_Request $request
     *
     * @return bool
     */
    protected function isReading($request) {
        return in_array($request->method(), ['HEAD', 'GET', 'OPTIONS']);
    }

    /**
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    protected function runningUnitTests() {
        return CF::isCli() && CF::isTesting();
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param \CHTTP_Request $request
     *
     * @return bool
     */
    protected function inExceptArray($request) {
        foreach (array_merge($this->except, static::$neverVerify) as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->fullUrlIs($except) || $request->is($except)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the request is trusted by its fetch metadata (opt-in, see trustSecFetchSite()).
     *
     * @param CHTTP_Request $request
     *
     * @throws CHTTP_Exception_OriginMismatchException
     *
     * @return bool
     */
    protected function hasValidOrigin($request) {
        if (!static::$trustSecFetchSite && !static::$originOnly) {
            return false;
        }

        $secFetchSite = $request->header('Sec-Fetch-Site');

        if ($secFetchSite === 'same-origin') {
            return true;
        }

        if ($secFetchSite === 'same-site' && static::$allowSameSite) {
            return true;
        }

        if (static::$originOnly) {
            throw new CHTTP_Exception_OriginMismatchException('Origin mismatch.');
        }

        return false;
    }

    /**
     * Opt in to skipping the token check for same-origin requests (Sec-Fetch-Site).
     *
     * @param bool $trust
     *
     * @return void
     */
    public static function trustSecFetchSite($trust = true) {
        static::$trustSecFetchSite = $trust;
    }

    /**
     * Opt in to also trusting same-site requests.
     *
     * @param bool $allow
     *
     * @return void
     */
    public static function allowSameSite($allow = true) {
        static::$allowSameSite = $allow;
    }

    /**
     * Opt in to origin-only verification: cross-origin requests are rejected without looking at a token.
     *
     * @param bool $originOnly
     *
     * @return void
     */
    public static function useOriginOnly($originOnly = true) {
        static::$originOnly = $originOnly;
    }

    /**
     * Indicate that the given URIs should never be verified.
     *
     * @param array|string $uris
     *
     * @return void
     */
    public static function except($uris) {
        static::$neverVerify = array_values(array_unique(array_merge(static::$neverVerify, (array) $uris)));
    }

    /**
     * Flush the global state of the middleware.
     *
     * @return void
     */
    public static function flushState() {
        static::$neverVerify = [];
        static::$trustSecFetchSite = false;
        static::$allowSameSite = false;
        static::$originOnly = false;
    }

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param \CHTTP_Request $request
     *
     * @return bool
     */
    protected function tokensMatch($request) {
        $token = $this->getTokenFromRequest($request);

        return is_string($request->session()->token())
               && is_string($token)
               && hash_equals($request->session()->token(), $token);
    }

    /**
     * Get the CSRF token from the request.
     *
     * @param \CHTTP_Request $request
     *
     * @return string
     */
    protected function getTokenFromRequest($request) {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (!$token && $header = $request->header('X-XSRF-TOKEN')) {
            try {
                $token = CHTTP_Cookie_CookieValuePrefix::remove($this->encrypter->decrypt($header, static::serialized()));
            } catch (CCrypt_Exception_DecryptException $e) {
                $token = '';
            }
        }

        return $token;
    }

    /**
     * Determine if the cookie should be added to the response.
     *
     * @return bool
     */
    public function shouldAddXsrfTokenCookie() {
        if (static::$originOnly) {
            return false;
        }

        return $this->addHttpCookie;
    }

    /**
     * Add the CSRF token to the response cookies.
     *
     * @param \CHTTP_Request                             $request
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addCookieToResponse($request, $response) {
        $config = CF::config('session');

        if ($response instanceof CInterface_Responsable) {
            $response = $response->toResponse($request);
        }

        $response->headers->setCookie(
            new Cookie(
                'XSRF-TOKEN',
                $request->session()->token(),
                $this->availableAt($config['expiration']),
                $config['path'],
                $config['domain'],
                $config['secure'],
                false,
                false,
                isset($config['same_site']) ? $config['same_site'] : null,
                isset($config['partitioned']) ? (bool) $config['partitioned'] : false
            )
        );

        return $response;
    }

    /**
     * Determine if the cookie contents should be serialized.
     *
     * @return bool
     */
    public static function serialized() {
        return CHTTP_Cookie_Middleware_EncryptCookies::serialized('XSRF-TOKEN');
    }
}
