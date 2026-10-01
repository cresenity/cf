<?php

/**
 * "Confirm your password" before a sensitive action: a correct password is remembered in the session for a while.
 */
class CApp_Auth_PasswordConfirmation {
    const SESSION_KEY = 'auth.password_confirmed_at';

    /**
     * Seconds a confirmation stays valid by default.
     */
    const TTL = 900;

    /**
     * @var CAuth_Contract_StatefulGuardInterface
     */
    protected $guard;

    /**
     * @var CSession_Store
     */
    protected $session;

    /**
     * @param null|CSession_Store $session defaults to the app session
     */
    public function __construct(CAuth_Contract_StatefulGuardInterface $guard, $session = null) {
        $this->guard = $guard;
        $this->session = $session ?: CBase::session();
    }

    /**
     * @return static
     */
    public static function make() {
        return new static(c::app()->auth()->guard());
    }

    /**
     * @param CAuth_AuthenticatableInterface $user
     * @param string                         $password
     *
     * @return bool
     */
    public function confirm($user, $password) {
        $username = CApp_Auth::username();
        $valid = $this->guard->validate([
            $username => $user->{$username},
            'password' => (string) $password,
        ]);
        if ($valid) {
            $this->session->put(static::SESSION_KEY, time());
        }

        return $valid;
    }

    /**
     * @param int $seconds
     *
     * @return bool
     */
    public function isConfirmed($seconds = self::TTL) {
        $at = (int) $this->session->get(static::SESSION_KEY, 0);

        return $at > 0 && time() - $at <= $seconds;
    }

    /**
     * @return void
     */
    public function forget() {
        $this->session->forget(static::SESSION_KEY);
    }
}
