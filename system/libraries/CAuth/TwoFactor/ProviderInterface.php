<?php

interface CAuth_TwoFactor_ProviderInterface {
    /**
     * Generate a new secret key.
     *
     * @return string
     */
    public function generateSecretKey();

    /**
     * Get the provisioning URI that authenticator apps read from the QR code.
     *
     * @param string $issuer
     * @param string $account
     * @param string $secret
     *
     * @return string
     */
    public function qrCodeUrl($issuer, $account, $secret);

    /**
     * Verify the given one-time code against the secret.
     *
     * @param string $secret
     * @param string $code
     *
     * @return bool
     */
    public function verify($secret, $code);
}
