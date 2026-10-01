<?php

use BaconQrCode\Writer;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

/**
 * For user models that store `two_factor_secret`, `two_factor_recovery_codes` and (optionally) `two_factor_confirmed_at`.
 */
trait CAuth_TwoFactor_AuthenticatableTrait {
    /**
     * @return bool
     */
    public function hasEnabledTwoFactorAuthentication() {
        return CAuth_TwoFactor_Manager::instance()->isEnabled($this);
    }

    /**
     * Get the user's two factor authentication recovery codes.
     *
     * @return array
     */
    public function recoveryCodes() {
        return json_decode(c::decrypt($this->two_factor_recovery_codes), true);
    }

    /**
     * Replace the given recovery code with a new one.
     *
     * @param string $code
     *
     * @return void
     */
    public function replaceRecoveryCode($code) {
        CAuth_TwoFactor_Manager::instance()->replaceRecoveryCode($this, $code);
    }

    /**
     * Get the QR code SVG of the user's two factor authentication QR code URL.
     *
     * @return string
     */
    public function twoFactorQrCodeSvg() {
        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72))),
                new SvgImageBackEnd()
            )
        ))->writeString($this->twoFactorQrCodeUrl());

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    /**
     * Get the two factor authentication QR code URL.
     *
     * @return string
     */
    public function twoFactorQrCodeUrl() {
        $account = method_exists($this, 'twoFactorAccountLabel') ? $this->twoFactorAccountLabel() : $this->email;

        $issuer = CF::config('app.name') ?: (CF::config('app.title') ?: CF::domain());

        return CAuth_TwoFactor_Manager::instance()->provider()->qrCodeUrl(
            $issuer,
            $account,
            c::decrypt($this->two_factor_secret)
        );
    }
}
