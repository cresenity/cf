<?php

class CAuth_TwoFactor_TotpProvider implements CAuth_TwoFactor_ProviderInterface {
    /**
     * Seconds a verified code stays remembered, so it cannot be used twice.
     */
    const REPLAY_TTL = 120;

    /**
     * Periods before and after the current one whose codes are still accepted.
     *
     * @var int
     */
    protected $window;

    /**
     * @var null|CCache_Repository
     */
    protected $cache;

    /**
     * @param int                    $window
     * @param null|CCache_Repository $cache  store that remembers verified codes, defaults to the app cache
     */
    public function __construct($window = 1, $cache = null) {
        $this->window = max(0, (int) $window);
        $this->cache = $cache;
    }

    /**
     * @return string
     */
    public function generateSecretKey() {
        return CAuth_OTP_TOTP::generate()->getSecret();
    }

    /**
     * @param string $issuer
     * @param string $account
     * @param string $secret
     *
     * @return string
     */
    public function qrCodeUrl($issuer, $account, $secret) {
        $totp = CAuth_OTP_TOTP::createFromSecret($secret);
        $totp->setLabel((string) $account);
        if ((string) $issuer !== '') {
            $totp->setIssuer((string) $issuer);
        }

        return $totp->getProvisioningUri();
    }

    /**
     * @param string $secret
     * @param string $code
     *
     * @return bool
     */
    public function verify($secret, $code) {
        $code = preg_replace('/\s+/', '', (string) $code);
        if ($code === '' || !ctype_digit($code)) {
            return false;
        }

        if (!$this->matches($secret, $code)) {
            return false;
        }

        return $this->cache()->add('cauth.twofactor.used.' . sha1($secret . '|' . $code), 1, static::REPLAY_TTL);
    }

    /**
     * @param string $secret
     * @param string $code
     *
     * @return bool
     */
    protected function matches($secret, $code) {
        $totp = CAuth_OTP_TOTP::createFromSecret($secret);
        $now = time();
        $matched = false;
        for ($i = -$this->window; $i <= $this->window; $i++) {
            // no short-circuit: every period is compared so timing does not reveal which one matched
            if ($totp->verify($code, max(1, $now + $i * $totp->getPeriod()))) {
                $matched = true;
            }
        }

        return $matched;
    }

    /**
     * @return CCache_Repository
     */
    protected function cache() {
        return $this->cache ?: c::cache()->store();
    }
}
