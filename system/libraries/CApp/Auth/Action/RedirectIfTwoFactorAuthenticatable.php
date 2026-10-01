<?php

class CApp_Auth_Action_RedirectIfTwoFactorAuthenticatable {
    /**
     * The guard implementation.
     *
     * @var CAuth_Contract_StatefulGuardInterface
     */
    protected $guard;

    /**
     * The login rate limiter instance.
     *
     * @var CApp_Auth_LoginRateLimiter
     */
    protected $limiter;

    /**
     * Create a new controller instance.
     *
     * @param CAuth_Contract_StatefulGuardInterface $guard
     * @param CApp_Auth_LoginRateLimiter            $limiter
     *
     * @return void
     */
    public function __construct(CAuth_Contract_StatefulGuardInterface $guard, CApp_Auth_LoginRateLimiter $limiter) {
        $this->guard = $guard;
        $this->limiter = $limiter;
    }

    /**
     * Handle the incoming request.
     *
     * @param \CHTTP_Request $request
     * @param callable       $next
     *
     * @return mixed
     */
    public function handle($request, $next) {
        $user = $this->validateCredentials($request);

        if ($user
            && in_array(CAuth_TwoFactor_AuthenticatableTrait::class, c::classUsesRecursive($user))
            && CAuth_TwoFactor_Manager::instance()->isEnabled($user)
        ) {
            return $this->twoFactorChallengeResponse($request, $user);
        }

        return $next($request);
    }

    /**
     * Attempt to validate the incoming credentials.
     *
     * @param \CHTTP_Request $request
     *
     * @return mixed
     */
    protected function validateCredentials($request) {
        if (CApp_Auth::$authenticateUsingCallback) {
            return c::tap(call_user_func(CApp_Auth::$authenticateUsingCallback, $request), function ($user) use ($request) {
                if (!$user) {
                    $this->fireFailedEvent($request);

                    $this->throwFailedAuthenticationException($request);
                }
            });
        }

        $provider = $this->guard->getProvider();
        $user = $provider->retrieveByCredentials(
            $request->only(CApp_Auth::username(), 'password')
        );

        if (!$user || !$provider->validateCredentials($user, $request->only('password'))) {
            $this->fireFailedEvent($request, $user);

            $this->throwFailedAuthenticationException($request);
        }

        return $user;
    }

    /**
     * Throw a failed authentication validation exception.
     *
     * @param CHTTP_Request $request
     *
     * @throws CValidation_Exception
     *
     * @return void
     */
    protected function throwFailedAuthenticationException($request) {
        $this->limiter->increment($request);

        throw CValidation_Exception::withMessages([
            CApp_Auth::username() => [c::trans('auth.failed')],
        ]);
    }

    /**
     * Fire the failed authentication attempt event with the given arguments.
     *
     * @param \CHTTP_Request                      $request
     * @param null|CAuth_AuthenticatableInterface $user
     *
     * @return void
     */
    protected function fireFailedEvent($request, $user = null) {
        c::event(new CAuth_Event_Failed(CF::config('app.auth.guard'), $user, [
            CApp_Auth::username() => $request->{CApp_Auth::username()},
            'password' => $request->password,
        ]));
    }

    /**
     * Get the two factor authentication enabled response.
     *
     * @param \CHTTP_Request $request
     * @param mixed          $user
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function twoFactorChallengeResponse($request, $user) {
        CApp_Auth_TwoFactorChallenge::begin($request->session(), $user, $request->filled('remember'));

        return $request->wantsJson()
            ? c::response()->json(['two_factor' => true])
            : c::redirect('login/twofactor');
    }
}
