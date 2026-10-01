<?php

class CApp_Auth_Features {
    /**
     * @var array
     */
    protected static $features;

    /**
     * Options given when a feature is enabled, keyed by feature.
     *
     * @var array
     */
    protected static $options = [];

    /**
     * Set the list of enabled features.
     *
     * @param array $features
     *
     * @return void
     */
    public static function setFeatures($features) {
        static::$features = $features;
    }

    /**
     * Enable the registration feature.
     *
     * @return string
     */
    public static function registration() {
        return 'registration';
    }

    /**
     * Enable the password reset feature.
     *
     * @return string
     */
    public static function resetPasswords() {
        return 'reset-passwords';
    }

    /**
     * Enable the email verification feature.
     *
     * @return string
     */
    public static function emailVerification() {
        return 'email-verification';
    }

    /**
     * Enable the update profile information feature.
     *
     * @return string
     */
    public static function updateProfileInformation() {
        return 'update-profile-information';
    }

    /**
     * Enable the update password feature.
     *
     * @return string
     */
    public static function updatePasswords() {
        return 'update-passwords';
    }

    /**
     * Enable the two factor authentication feature.
     *
     * @return string
     */
    public static function twoFactorAuthentication(array $options = []) {
        if (!empty($options)) {
            static::$options['two-factor-authentication'] = $options;
        }

        return 'two-factor-authentication';
    }

    /**
     * Enable the teams feature.
     *
     * @return string
     */
    public static function teams() {
        return 'teams';
    }

    /**
     * Enable the profile photo upload feature.
     *
     * @return string
     */
    public static function profilePhotos() {
        return 'profile-photos';
    }

    /**
     * Determine if the given feature is enabled.
     *
     * @param string $feature
     *
     * @return bool
     */
    public static function enabled($feature) {
        return in_array($feature, static::$features);
    }

    /**
     * Determine if the given option of an enabled feature is switched on.
     *
     * @param string $feature
     * @param string $option
     *
     * @return bool
     */
    public static function optionEnabled($feature, $option) {
        return static::enabled($feature) && !empty(static::$options[$feature][$option]);
    }
}
