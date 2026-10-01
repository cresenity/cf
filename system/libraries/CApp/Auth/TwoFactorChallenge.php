<?php

/**
 * Second step of a login for users with two factor authentication. The first step stores a pending
 * login in the session (begin()), this class turns it into a real login once a valid code is given.
 */
class CApp_Auth_TwoFactorChallenge {
    const SESSION_ID = 'login.id';

    const SESSION_REMEMBER = 'login.remember';

    const SESSION_AT = 'login.at';

    /**
     * Seconds a pending login stays valid.
     */
    const TTL = 300;

    const MAX_ATTEMPTS = 5;

    const DECAY_SECONDS = 60;

    /**
     * @var CAuth_Contract_StatefulGuardInterface
     */
    protected $guard;

    /**
     * @var CSession_Store
     */
    protected $session;

    /**
     * @var CCache_RateLimiter
     */
    protected $limiter;

    /**
     * @var CAuth_TwoFactor_Manager
     */
    protected $manager;

    /**
     * @param null|CSession_Store $session defaults to the app session
     */
    public function __construct(CAuth_Contract_StatefulGuardInterface $guard, $session = null, CCache_RateLimiter $limiter = null, CAuth_TwoFactor_Manager $manager = null) {
        $this->guard = $guard;
        $this->session = $session ?: CBase::session();
        $this->limiter = $limiter ?: new CCache_RateLimiter(c::cache()->store());
        $this->manager = $manager ?: CAuth_TwoFactor_Manager::instance();
    }

    /**
     * @return static
     */
    public static function make() {
        return new static(c::app()->auth()->guard());
    }

    /**
     * Remember a user whose password was accepted but who still has to pass the challenge.
     *
     * @param CSession_Store                 $session
     * @param CAuth_AuthenticatableInterface $user
     * @param bool                           $remember
     *
     * @return void
     */
    public static function begin($session, $user, $remember = false) {
        $session->put([
            static::SESSION_ID => $user->getAuthIdentifier(),
            static::SESSION_REMEMBER => (bool) $remember,
            static::SESSION_AT => time(),
        ]);
    }

    /**
     * @return bool
     */
    public function hasChallengedUser() {
        return $this->challengedUser() !== null;
    }

    /**
     * The user waiting for the challenge, or null when there is none, it expired, or two factor is off.
     *
     * @return null|CAuth_AuthenticatableInterface
     */
    public function challengedUser() {
        $id = $this->session->get(static::SESSION_ID);
        if ($id === null) {
            return null;
        }
        $at = (int) $this->session->get(static::SESSION_AT, 0);
        if ($at > 0 && time() - $at > static::TTL) {
            $this->cancel();

            return null;
        }
        $user = $this->guard->getProvider()->retrieveById($id);
        if (!$user || !$this->manager->isEnabled($user)) {
            $this->cancel();

            return null;
        }

        return $user;
    }

    /**
     * Check `code` or `recovery_code` of the request and, when valid, log the user in.
     *
     * @throws CValidation_Exception
     *
     * @return CAuth_AuthenticatableInterface
     */
    public function complete(CHTTP_Request $request) {
        $user = $this->challengedUser();
        if ($user === null) {
            throw $this->invalid('code', 'The two factor challenge has expired. Please log in again.');
        }

        $key = $this->throttleKey($user, $request);
        if ($this->limiter->tooManyAttempts($key, static::MAX_ATTEMPTS)) {
            c::event(new CAuth_Event_Lockout($request));

            throw $this->invalid('code', str_replace(':seconds', (string) $this->limiter->availableIn($key), c::__('Too many attempts. Please try again in :seconds seconds.')));
        }

        $code = trim((string) $request->input('code', ''));
        $recoveryCode = trim((string) $request->input('recovery_code', ''));
        if ($code !== '') {
            $field = 'code';
            $valid = $this->manager->verifyCode($user, $code);
        } elseif ($recoveryCode !== '') {
            $field = 'recovery_code';
            $valid = $this->manager->useRecoveryCode($user, $recoveryCode);
        } else {
            throw $this->invalid('code', 'The code field is required.');
        }

        if (!$valid) {
            $this->limiter->hit($key, static::DECAY_SECONDS);
            c::event(new CAuth_Event_TwoFactorFailed($user));

            throw $this->invalid($field, $field === 'code' ? 'The provided two factor authentication code was invalid.' : 'The provided two factor recovery code was invalid.');
        }

        $this->limiter->clear($key);
        $remember = (bool) $this->session->get(static::SESSION_REMEMBER, false);
        $this->cancel();
        $this->guard->login($user, $remember);
        $this->session->regenerate();

        return $user;
    }

    /**
     * Drop the pending login.
     *
     * @return void
     */
    public function cancel() {
        $this->session->forget([static::SESSION_ID, static::SESSION_REMEMBER, static::SESSION_AT]);
    }

    /**
     * @param CAuth_AuthenticatableInterface $user
     *
     * @return string
     */
    protected function throttleKey($user, CHTTP_Request $request) {
        return 'twofactor|' . $user->getAuthIdentifier() . '|' . $request->ip();
    }

    /**
     * @param string $field
     * @param string $message
     *
     * @return CValidation_Exception
     */
    protected function invalid($field, $message) {
        return CValidation_Exception::withMessages([$field => [c::__($message)]]);
    }
}
