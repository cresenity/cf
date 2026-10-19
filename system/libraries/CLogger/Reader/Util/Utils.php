<?php

class CLogger_Reader_Util_Utils {
    /**
     * Get a human-friendly readable string of the number of bytes provided.
     *
     * @param mixed $bytes
     */
    public static function bytesForHumans($bytes) {
        if ($bytes > ($gb = 1024 * 1024 * 1024)) {
            return number_format($bytes / $gb, 2) . ' GB';
        } elseif ($bytes > ($mb = 1024 * 1024)) {
            return number_format($bytes / $mb, 2) . ' MB';
        } elseif ($bytes > ($kb = 1024)) {
            return number_format($bytes / $kb, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Calculate the memory footprint of a given variable.
     * CAUTION: This will increase the memory usage by that same amount because it makes a copy of this variable.
     *
     * @param mixed $var
     */
    public static function sizeOfVar($var) {
        $start_memory = memory_get_usage();
        $tmp = unserialize(serialize($var));

        return memory_get_usage() - $start_memory;
    }

    /**
     * Calculate the memory footprint of a given variable and return it as a human-friendly string.
     * CAUTION: This will increase the memory usage by that same amount because it makes a copy of this variable.
     *
     * @param mixed $var
     */
    public static function sizeOfVarInMB($var) {
        return self::bytesForHumans(self::sizeOfVar($var));
    }

    public static function validateRegex($regexString, $throw = true) {
        $error = null;
        set_error_handler(function ($errno, $errstr) use (&$error) {
            $error = $errstr;
        }, E_WARNING);
        preg_match($regexString, '');
        restore_error_handler();

        if (!empty($error)) {
            $error = str_replace('preg_match(): ', '', $error);

            if ($throw) {
                throw new CLogger_Reader_Exception_InvalidRegularExpression($error);
            }

            return false;
        }

        return true;
    }

    /**
     * Regex pencarian dari keyword: regex bila valid, selain itu teks literal.
     *
     * @param string $query
     *
     * @return string
     */
    public static function searchRegex($query) {
        $regex = '~' . $query . '~i';

        return self::validateRegex($regex, false) ? $regex : '~' . preg_quote($query, '~') . '~i';
    }

    public static function shortMd5($content, $length = 8) {
        if ($length > 32) {
            $length = 32;
        }

        return substr(md5($content), -$length, $length);
    }
}
