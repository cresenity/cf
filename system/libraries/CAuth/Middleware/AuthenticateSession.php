<?php

/**
 * Menyimpan hash password user di sesi dan mengeluarkan sesi ini begitu hash-nya berubah
 * (password diganti dari perangkat lain / `CAuth::guard()->logoutOtherDevices()`).
 * Opt-in: `session.authenticate => true`, atau push sendiri lewat CMiddleware::manager().
 */
class CAuth_Middleware_AuthenticateSession {
    /**
     * @var null|CAuth_GuardInterface
     */
    protected $guard;

    /**
     * @var null|CSession_Store
     */
    protected $session;

    /**
     * @var null|string
     */
    protected $guardName;

    /**
     * Tanpa argumen memakai guard bawaan CAuth dan sesi request (pipeline); argumen ada untuk test.
     *
     * @param null|CAuth_GuardInterface $guard
     * @param null|CSession_Store       $session
     * @param null|string               $guardName nama guard untuk kunci `password_hash_<guard>`
     */
    public function __construct($guard = null, $session = null, $guardName = null) {
        $this->guard = $guard;
        $this->session = $session;
        $this->guardName = $guardName;
    }

    /**
     * @param CHTTP_Request $request
     * @param Closure       $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $session = $this->session($request);
        if ($session === null || !$session->isStarted() || !$this->guard()->user()) {
            return $next($request);
        }
        $key = $this->passwordHashKey();

        if ($this->guard()->viaRemember() && !$this->hashMatches($session->get($key))) {
            return $this->logout($request);
        }
        if (!$session->has($key)) {
            $this->storePasswordHashInSession($session, $key);
        }
        if (!$this->hashMatches($session->get($key))) {
            return $this->logout($request);
        }

        return c::tap($next($request), function () use ($session, $key) {
            if ($this->guard()->user()) {
                $this->storePasswordHashInSession($session, $key);
            }
        });
    }

    /**
     * @param null|string $stored
     *
     * @return bool
     */
    protected function hashMatches($stored) {
        return $stored === null ? false : $stored === (string) $this->guard()->user()->getAuthPassword();
    }

    /**
     * @param CSession_Store $session
     * @param string         $key
     *
     * @return void
     */
    protected function storePasswordHashInSession($session, $key) {
        $session->put([$key => (string) $this->guard()->user()->getAuthPassword()]);
    }

    /**
     * Keluar dari sesi ini, lalu 401 untuk permintaan JSON atau redirect ke `session.authenticate_redirect`
     * (default: URL yang sama, supaya gerbang login app yang menjawab).
     *
     * @param CHTTP_Request $request
     *
     * @return CHTTP_Response
     */
    protected function logout($request) {
        $this->guard()->logout();
        $this->session($request)->flush();

        if ($request->expectsJson()) {
            return c::response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $redirect = CF::config('session.authenticate_redirect');
        if (!$redirect) {
            $redirect = $request->isMethod('GET') ? $request->fullUrl() : curl::base();
        }

        return c::redirect()->to($redirect);
    }

    /**
     * @return string
     */
    protected function passwordHashKey() {
        return 'password_hash_' . ($this->guardName ?: CAuth::manager()->getDefaultDriver());
    }

    /**
     * @return CAuth_Guard_SessionGuard|CAuth_GuardInterface
     */
    protected function guard() {
        return $this->guard ?: CAuth::manager()->guard();
    }

    /**
     * @param CHTTP_Request $request
     *
     * @return null|CSession_Store
     */
    protected function session($request) {
        if ($this->session !== null) {
            return $this->session;
        }

        try {
            return $request->session();
        } catch (Throwable $e) {
            return null;
        }
    }
}
