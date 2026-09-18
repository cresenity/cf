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

    public static function unformatCurrency($number, $force_number = true, $dec_point = '.', $thousands_sep = ',') {
        if ($force_number) {
            $number = preg_replace('/^[^\d]+/', '', $number);
        } elseif (preg_match('/^[^\d]+/', $number)) {
            return false;
        }
        $type = (strpos($number, $dec_point) === false) ? 'int' : 'float';
        $number = str_replace([$dec_point, $thousands_sep], ['.', ''], $number);
        settype($number, $type);

        return $number;
    }

    public static function unformatNumber($number, $force_number = true, $dec_point = '.', $thousands_sep = ',') {
        if ($force_number) {
            $number = preg_replace('/^[^\d]+/', '', $number);
        } elseif (preg_match('/^[^\d]+/', $number)) {
            return false;
        }
        $type = (strpos($number, $dec_point) === false) ? 'int' : 'float';
        $number = str_replace([$dec_point, $thousands_sep], ['.', ''], $number);
        settype($number, $type);

        return $number;
    }

    public static function formatDate($date) {
        return c::formatter()->formatDate($date);
    }

    public static function formatDatetime($date) {
        return c::formatter()->formatDatetime($date);
    }
}
