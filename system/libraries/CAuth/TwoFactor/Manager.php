<?php

/**
 * Two factor authentication for a user model: enable, confirm, disable, verify codes and recovery codes.
 * Needs no request, session or Features, so jobs, CApi methods and controllers can all call it.
 */
class CAuth_TwoFactor_Manager {
    const RECOVERY_CODE_COUNT = 8;

    /**
     * @var null|static
     */
    protected static $instance;

    /**
     * @var CAuth_TwoFactor_ProviderInterface
     */
    protected $provider;

    public function __construct(CAuth_TwoFactor_ProviderInterface $provider = null) {
        $this->provider = $provider ?: new CAuth_TwoFactor_TotpProvider();
    }

    /**
     * @return static
     */
    public static function instance() {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @param null|static $instance
     *
     * @return void
     */
    public static function setInstance($instance) {
        static::$instance = $instance;
    }

    /**
     * @return CAuth_TwoFactor_ProviderInterface
     */
    public function provider() {
        return $this->provider;
    }

    /**
     * Whether the user must pass a two factor challenge at login.
     *
     * @param mixed $user
     *
     * @return bool
     */
    public function isEnabled($user) {
        if (!$this->has($user, 'two_factor_secret')) {
            return false;
        }

        return !$this->tracksConfirmation($user) || $user->two_factor_confirmed_at !== null;
    }

    /**
     * Generate a new secret and recovery codes. When the model has a `two_factor_confirmed_at` column the
     * setup is pending until confirm() succeeds.
     *
     * @param mixed $user
     *
     * @return void
     */
    public function enable($user) {
        $attributes = [
            'two_factor_secret' => c::encrypt($this->provider->generateSecretKey()),
            'two_factor_recovery_codes' => c::encrypt(json_encode($this->newRecoveryCodes())),
        ];
        if ($this->tracksConfirmation($user)) {
            $attributes['two_factor_confirmed_at'] = null;
        }
        $user->forceFill($attributes)->save();

        c::event(new CAuth_Event_TwoFactorEnabled($user));
    }

    /**
     * @param mixed  $user
     * @param string $code
     *
     * @throws LogicException        when enable() was not called first
     * @throws CValidation_Exception when the code is not valid
     *
     * @return void
     */
    public function confirm($user, $code) {
        if (!$this->has($user, 'two_factor_secret')) {
            throw new LogicException('Two factor authentication is not set up for this user.');
        }
        if (!$this->provider->verify(c::decrypt($user->two_factor_secret), $code)) {
            throw CValidation_Exception::withMessages([
                'code' => [c::__('The provided two factor authentication code was invalid.')],
            ]);
        }
        if ($this->tracksConfirmation($user)) {
            $user->forceFill(['two_factor_confirmed_at' => c::now()])->save();
        }

        c::event(new CAuth_Event_TwoFactorConfirmed($user));
    }

    /**
     * @param mixed $user
     *
     * @return void
     */
    public function disable($user) {
        if (!$this->has($user, 'two_factor_secret') && !$this->has($user, 'two_factor_recovery_codes')) {
            return;
        }
        $attributes = ['two_factor_secret' => null, 'two_factor_recovery_codes' => null];
        if ($this->tracksConfirmation($user)) {
            $attributes['two_factor_confirmed_at'] = null;
        }
        $user->forceFill($attributes)->save();

        c::event(new CAuth_Event_TwoFactorDisabled($user));
    }

    /**
     * @param mixed $user
     *
     * @return void
     */
    public function regenerateRecoveryCodes($user) {
        $user->forceFill([
            'two_factor_recovery_codes' => c::encrypt(json_encode($this->newRecoveryCodes())),
        ])->save();

        c::event(new CAuth_Event_TwoFactorRecoveryCodesGenerated($user));
    }

    /**
     * Verify a one-time code from the authenticator app. A code can be used once.
     *
     * @param mixed  $user
     * @param string $code
     *
     * @return bool
     */
    public function verifyCode($user, $code) {
        if (!$this->has($user, 'two_factor_secret')) {
            return false;
        }
        if (!$this->provider->verify(c::decrypt($user->two_factor_secret), $code)) {
            return false;
        }

        c::event(new CAuth_Event_TwoFactorCodeVerified($user));

        return true;
    }

    /**
     * Consume a recovery code: it is replaced by a fresh one, so each code works once.
     *
     * @param mixed  $user
     * @param string $code
     *
     * @return bool
     */
    public function useRecoveryCode($user, $code) {
        $code = trim((string) $code);
        if ($code === '' || !$this->has($user, 'two_factor_recovery_codes')) {
            return false;
        }
        foreach ($this->recoveryCodesOf($user) as $stored) {
            if (hash_equals($stored, $code)) {
                $this->replaceRecoveryCode($user, $stored);
                c::event(new CAuth_Event_TwoFactorRecoveryCodeUsed($user));

                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed  $user
     * @param string $code
     *
     * @return void
     */
    public function replaceRecoveryCode($user, $code) {
        $codes = array_map(function ($stored) use ($code) {
            return $stored === $code ? CAuth_TwoFactor_RecoveryCode::generate() : $stored;
        }, $this->recoveryCodesOf($user));

        $user->forceFill(['two_factor_recovery_codes' => c::encrypt(json_encode(array_values($codes)))])->save();
    }

    /**
     * Read the attribute directly: empty() would go through __isset(), which not every model implements.
     *
     * @param mixed  $user
     * @param string $attribute
     *
     * @return bool
     */
    protected function has($user, $attribute) {
        $value = $user->{$attribute};

        return $value !== null && $value !== '';
    }

    /**
     * @param mixed $user
     *
     * @return string[]
     */
    protected function recoveryCodesOf($user) {
        $codes = json_decode(c::decrypt($user->two_factor_recovery_codes), true);

        return is_array($codes) ? $codes : [];
    }

    /**
     * @return string[]
     */
    protected function newRecoveryCodes() {
        $codes = [];
        for ($i = 0; $i < static::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = CAuth_TwoFactor_RecoveryCode::generate();
        }

        return $codes;
    }

    /**
     * Models without a `two_factor_confirmed_at` column keep the old behaviour: a stored secret is active at once.
     *
     * @param mixed $user
     *
     * @return bool
     */
    protected function tracksConfirmation($user) {
        return array_key_exists('two_factor_confirmed_at', $user->getAttributes());
    }
}
