<?php

/**
 * Description of UtilsTrait.
 */
trait CBase_Trait_UtilsTrait {
    public static function resolveLibraryClassName($name, $folder) {
        $name = str_replace('/', '_', $name);
        $names = explode('_', $name);
        if ($folder != null) {
            $folder = ucfirst($folder);
        }
        $prefix = CF::config('app.prefix');
        if (carr::first($names) == $prefix . $folder) {
            return $name;
        }

        return $prefix . $folder . '_' . $name;
    }

    public static function formatCurrency($value) {
        return c::formatter()->formatCurrency($value);
    }

    public static function formatNumber($value) {
        return c::formatter()->formatNumber($value);
    }

    public static function formatDecimal($value) {
        return c::formatter()->formatDecimal($value);
    }

    /**
     * Pemisah yang tidak diisi mengikuti c::formatter() (app.format.*).
     *
     * @param string      $number
     * @param bool        $force_number
     * @param string|null $dec_point
     * @param string|null $thousands_sep
     *
     * @return float|int|false
     */
    public static function unformatCurrency($number, $force_number = true, $dec_point = null, $thousands_sep = null) {
        if (!$force_number && preg_match('/^[^\d]+/', $number)) {
            return false;
        }

        return c::formatter()->unformatCurrency($number, $dec_point, $thousands_sep);
    }

    /**
     * Pemisah yang tidak diisi mengikuti c::formatter() (app.format.*).
     *
     * @param string      $number
     * @param bool        $force_number
     * @param string|null $dec_point
     * @param string|null $thousands_sep
     *
     * @return float|int|false
     */
    public static function unformatNumber($number, $force_number = true, $dec_point = null, $thousands_sep = null) {
        if (!$force_number && preg_match('/^[^\d]+/', $number)) {
            return false;
        }

        return c::formatter()->unformatNumber($number, $dec_point, $thousands_sep);
    }

    public static function formatDate($date) {
        return c::formatter()->formatDate($date);
    }

    public static function formatDatetime($date) {
        return c::formatter()->formatDatetime($date);
    }
}
