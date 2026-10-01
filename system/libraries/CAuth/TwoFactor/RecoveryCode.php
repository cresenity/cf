<?php

class CAuth_TwoFactor_RecoveryCode {
    /**
     * Generate a new recovery code.
     *
     * @return string
     */
    public static function generate() {
        return cstr::random(10) . '-' . cstr::random(10);
    }
}
