<?php

/**
 * Description of StartSessionMiddleware.
 */
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class CSession_Middleware_SessionMiddleware {
    /**
     * The callback that can resolve an instance of the cache factory.
     *
     * @var null|callable
     */
    protected $cacheFactoryResolver;

    /**
     * Create a new session middleware.
     *
     * @return void
     */
    public function __construct() {
        $this->cacheFactoryResolver = null;
    }

    /**
     * Handle an incoming request.
     *
     * @param CHTTP_Request $request
     * @param \Closure      $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next) {
        if (!$this->sessionConfigured()) {
            return $next($request);
        }

        $session = $this->getSession($request);

        if (CSession::manager()->shouldBlock()
            || ($request->route() instanceof CRouting_Route && $request->route()->locksFor())
        ) {
            return $this->handleRequestWhileBlocking($request, $session, $next);
        } else {
            return $this->handleStatefulRequest($request, $session, $next);
        }
    }

    /**
     * Handle the given request within session state.
     *
     * @param CHTTP_Request  $request
     * @param CSession_Store $session
     * @param \Closure       $next
     *
     * @return mixed
     */
    protected function handleRequestWhileBlocking(CHTTP_Request $request, $session, Closure $next) {
        if (!$request->route() instanceof CRouting_Route) {
            return;
        }

        $lockFor = $request->route() && $request->route()->locksFor() ? $request->route()->locksFor() : 10;

        $cache = $this->cache(CSession::manager()->blockDriver());
        $lock = null;
        if ($cache instanceof CCache_Contract_LockProviderDriverInterface) {
            $lock = $cache
                ->lock('session:' . $session->getId(), $lockFor)
                ->betweenBlockedAttemptsSleepFor(50);
        }

        try {
            if ($lock != null) {
                $lock->block(
                    !is_null($request->route()->waitsFor()) ? $request->route()->waitsFor() : 10
                );
            }

            return $this->handleStatefulRequest($request, $session, $next);
        } finally {
            c::optional($lock)->release();
        }
    }

    /**
     * Handle the given request within session state.
     *
     * @param CHTTP_Request  $request
     * @param CSession_Store $session
     * @param \Closure       $next
     *
     * @return mixed
     */
    protected function handleStatefulRequest(CHTTP_Request $request, $session, Closure $next) {
        $this->collectGarbage($session);

        $response = $next($request);

        $this->storeCurrentUrl($request, $session);

        $session->updateTotalHits();
        $session->updateLastActivity();

        $this->addCookieToResponse($response, $session);

        // Again, if the session has been configured we will need to close out the session
        // so that the attributes may be persisted to some storage medium. We will also
        // add the session identifier cookie to the application response headers now.
        $this->saveSession($request);

        return $response;
    }

    /**
     * Get the session implementation from the manager.
     *
     * @param CHTTP_Request $request
     *
     * @return CSession_Store
     */
    public function getSession(CHTTP_Request $request) {
        return CBase::session();
    }

    /**
     * Remove the garbage from the session if necessary.
     *
     * @param \CSession_Store $session
     *
     * @return void
     */
    protected function collectGarbage(CSession_Store $session) {
        $config = CSession::manager()->getSessionConfig();

        // Here we will see if this request hits the garbage collection lottery by hitting
        // the odds needed to perform garbage collection on any given request. If we do
        // hit it, we'll call this handler to let it delete all the expired sessions.
        if ($this->configHitsLottery($config)) {
            $session->getHandler()->gc($this->getSessionLifetimeInSeconds());
        }
    }

    /**
     * Determine if the configuration odds hit the lottery.
     *
     * @param array $config
     *
     * @return bool
     */
    protected function configHitsLottery(array $config) {
        $lottery = $config['gc_probability'];
        $maxLottery = 100;
        if (is_array($lottery)) {
            $maxLottery = carr::get($lottery, 1);
            $lottery = carr::get($lottery, 0);
        }

        return random_int(1, $maxLottery) <= $lottery;
    }

    /**
     * Store the current URL for the request if necessary.
     *
     * @param CHTTP_Request  $request
     * @param CSession_Store $session
     *
     * @return void
     */
    protected function storeCurrentUrl(CHTTP_Request $request, $session) {
        if ($request->method() === 'GET'
            && $request->route() instanceof CRouting_Route
            && !$request->ajax()
            && !$request->prefetch()
        ) {
            $session->setPreviousUrl($request->fullUrl());
        }
    }

    /**
     * Add the session cookie to the application response.
     *
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param \CSession_Store                            $session
     *
     * @return void
     */
    protected function addCookieToResponse(Response $response, CSession_Store $session) {
        if ($this->sessionIsPersistent($config = CSession::manager()->getSessionConfig())) {
            $response->headers->setCookie(new Cookie(
                $session->getName(),
                $session->getId(),
                $this->getCookieExpirationDate(),
                $config['path'],
                $config['domain'],
                $config['secure'],
                $config['httponly'],
                false,
                $config['same_site']
            ));
        }
    }

    /**
     * Save the session data to storage.
     *
     * @param CHTTP_Request $request
     *
     * @return void
     */
    protected function saveSession($request) {
        CSession::store()->save();
    }

    /**
     * Get the session lifetime in seconds.
     *
     * @return int
     */
    protected function getSessionLifetimeInSeconds() {
        return carr::get(CSession::manager()->getSessionConfig(), 'expiration');
    }

    /**
     * Get the cookie lifetime in seconds.
     *
     * @return \DateTimeInterface|int
     */
    protected function getCookieExpirationDate() {
        $config = CSession::manager()->getSessionConfig();

        return (isset($config['expire_on_close']) && $config['expire_on_close']) ? 0 : CCarbon::now()->addSeconds($config['expiration']);
    }

    /**
     * Determine if a session driver has been configured.
     *
     * @return bool
     */
    protected function sessionConfigured() {
        return !is_null(carr::get(CSession::manager()->getSessionConfig(), 'driver'));
    }

    /**
     * Determine if the configured session driver is persistent.
     *
     * @param null|array $config
     *
     * @return bool
     */
    protected function sessionIsPersistent(?array $config = null) {
        $config = $config ?: CSession::manager()->getSessionConfig();

        return !is_null(carr::get($config, 'driver'));
    }

    /**
     * Resolve the given cache driver.
     *
     * @param string $driver
     *
     * @return CCache_DriverAbstract
     */
    protected function cache($driver) {
        return call_user_func($this->cacheFactoryResolver)->driver($driver);
    }
}
