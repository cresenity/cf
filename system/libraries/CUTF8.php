<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * A port of [phputf8](http://phputf8.sourceforge.net/) to a unified set
 * of files. Provides multi-byte aware replacement string functions.
 *
 * For UTF-8 support to work correctly, the following requirements must be met:
 *
 * - PCRE needs to be compiled with UTF-8 support (--enable-utf8)
 * - Support for [Unicode properties](http://php.net/manual/reference.pcre.pattern.modifiers.php)
 *   is highly recommended (--enable-unicode-properties)
 * - The [mbstring extension](http://php.net/mbstring) is highly recommended,
 *   but must not be overloading string functions
 *
 * [!!] This file is licensed differently from the rest of CF. As a port of
 * [phputf8](http://phputf8.sourceforge.net/), this file is released under the LGPL.
 */
class CUTF8 {
    /**
     * @var bool Does the server support UTF-8 natively?
     */
    public static $mbstringEnabled = null;

    /**
     * Recursively cleans arrays, objects, and strings. Removes ASCII control
     * codes and converts to the requested charset while silently discarding
     * incompatible characters.
     *
     *     CUTF8::clean($_GET); // Clean GET data
     *
     * @param mixed  $var     variable to clean
     * @param string $charset character set, defaults to CF::$charset
     *
     * @return mixed
     *
     * @uses    CUTF8::clean
     * @uses    CUTF8::strip_ascii_ctrl
     * @uses    CUTF8::is_ascii
     */
    public static function clean($var, $charset = null) {
        if (!$charset) {
            // Use the application character set
            $charset = CF::$charset;
        }

        if (is_array($var) or is_object($var)) {
            foreach ($var as $key => $val) {
                // Recursion!
                $var[CUTF8::clean($key)] = CUTF8::clean($val);
            }
        } elseif (is_string($var) and $var !== '') {
            // Remove control characters
            $var = CUTF8::stripAsciiCtrl($var);

            if (!CUTF8::isAscii($var)) {
                // Temporarily save the mb_substitute_character() value into a variable
                $mb_substitute_character = mb_substitute_character();

                // Disable substituting illegal characters with the default '?' character
                mb_substitute_character('none');

                // convert encoding, this is expensive, used when $var is not ASCII
                $var = mb_convert_encoding($var, $charset, $charset);

                // Reset mb_substitute_character() value back to the original setting
                mb_substitute_character($mb_substitute_character);
            }
        }

        return $var;
    }

    /**
     * Tests whether a string contains only 7-bit ASCII bytes. This is used to
     * determine when to use native functions or UTF-8 functions.
     *
     *     $ascii = CUTF8::is_ascii($str);
     *
     * @param mixed $str string or array of strings to check
     *
     * @return bool
     */
    public static function isAscii($str) {
        if (is_array($str)) {
            $str = implode($str);
        }

        return !preg_match('/[^\x00-\x7F]/S', $str);
    }

    /**
     * Strips out device control codes in the ASCII range.
     *
     *     $str = CUTF8::strip_ascii_ctrl($str);
     *
     * @param string $str string to clean
     *
     * @return string
     */
    public static function stripAsciiCtrl($str) {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $str);
    }

    /**
     * Strips out all non-7bit ASCII bytes.
     *
     *     $str = CUTF8::stripNonAscii($str);
     *
     * @param string $str string to clean
     *
     * @return string
     */
    public static function stripNonAscii($str) {
        return preg_replace('/[^\x00-\x7F]+/S', '', $str);
    }

    /**
     * Replaces special/accented UTF-8 characters by ASCII-7 "equivalents".
     *
     *     $ascii = CUTF8::transliterate_to_ascii($utf8);
     *
     * @param string $str  string to transliterate
     * @param int    $case -1 lowercase only, +1 uppercase only, 0 both cases
     *
     *
     * @return string
     */
    public static function transliterateToAscii($str, $case = 0) {
        return CUTF8_Native::transliterateToAscii($str, $case);
    }

    /**
     * Returns the length of the given string. This is a UTF8-aware version
     * of [strlen](http://php.net/strlen).
     *
     *     $length = CUTF8::strlen($str);
     *
     * @param string $str string being measured for length
     *
     * @return int
     *
     * @uses    CUTF8::$mbstringEnabled
     * @uses    CF::$charset
     */
    public static function strlen($str) {
        if (CUTF8::mbstringEnabled()) {
            return mb_strlen($str, CF::$charset);
        }

        return CUTF8_Native::strlen($str);
    }

    /**
     * Finds position of first occurrence of a UTF-8 string. This is a
     * UTF8-aware version of [strpos](http://php.net/strpos).
     *
     *     $position = CUTF8::strpos($str, $search);
     *
     * @param string $str    haystack
     * @param string $search needle
     * @param int    $offset offset from which character in haystack to start searching
     *
     *
     * @return int|bool position of needle|FALSE if the needle is not found
     *
     * @uses    CUTF8::$mbstringEnabled
     * @uses    CF::$charset
     */
    public static function strpos($str, $search, $offset = 0) {
        if (CUTF8::mbstringEnabled()) {
            return mb_strpos($str, $search, $offset, CF::$charset);
        }

        return CUTF8_Native::strpos($str, $search, $offset);
    }

    /**
     * Finds position of last occurrence of a char in a UTF-8 string. This is
     * a UTF8-aware version of [strrpos](http://php.net/strrpos).
     *
     *     $position = CUTF8::strrpos($str, $search);
     *
     * @param string $str    haystack
     * @param string $search needle
     * @param int    $offset offset from which character in haystack to start searching
     *
     *
     * @return int|bool position of needle|FALSE if the needle is not found
     *
     * @uses    CUTF8::$mbstringEnabled
     */
    public static function strrpos($str, $search, $offset = 0) {
        if (CUTF8::mbstringEnabled()) {
            return mb_strrpos($str, $search, $offset, CF::getCharset());
        }

        return CUTF8_Native::strrpos($str, $search, $offset);
    }

    /**
     * Returns part of a UTF-8 string. This is a UTF8-aware version
     * of [substr](http://php.net/substr).
     *
     *     $sub = CUTF8::substr($str, $offset);
     *
     * @param string $str    input string
     * @param int    $offset offset
     * @param int    $length length limit
     *
     *
     * @return string
     *
     * @uses    CUTF8::$mbstringEnabled
     * @uses    CF::$charset
     */
    public static function substr($str, $offset, $length = null) {
        if (CUTF8::mbstringEnabled()) {
            return ($length === null) ? mb_substr($str, $offset, mb_strlen($str), CF::$charset) : mb_substr($str, $offset, $length, CF::$charset);
        }

        return CUTF8_Native::substr($str, $offset, $length);
    }

    /**
     * Replaces text within a portion of a UTF-8 string. This is a UTF8-aware
     * version of [substr_replace](http://php.net/substr_replace).
     *
     *     $str = CUTF8::substr_replace($str, $replacement, $offset);
     *
     * @param string     $str         input string
     * @param string     $replacement replacement string
     * @param int        $offset      offset
     * @param null|mixed $length
     *
     *
     * @return string
     */
    public static function substrReplace($str, $replacement, $offset, $length = null) {
        return CUTF8_Native::substrReplace($str, $replacement, $offset, $length);
    }

    /**
     * Makes a UTF-8 string lowercase. This is a UTF8-aware version
     * of [strtolower](http://php.net/strtolower).
     *
     *     $str = CUTF8::strtolower($str);
     *
     * @param string $str mixed case string
     *
     *
     * @return string
     *
     * @uses    CUTF8::$mbstringEnabled
     * @uses    CF::$charset
     */
    public static function strtolower($str) {
        if (CUTF8::mbstringEnabled()) {
            return mb_strtolower($str, CF::$charset);
        }

        return CUTF8_Native::strtolower($str);
    }

    /**
     * Makes a UTF-8 string uppercase. This is a UTF8-aware version
     * of [strtoupper](http://php.net/strtoupper).
     *
     * @param string $str mixed case string
     *
     *
     * @return string
     *
     * @uses    CUTF8::$mbstringEnabled
     * @uses    CF::$charset
     */
    public static function strtoupper($str) {
        if (CUTF8::mbstringEnabled()) {
            return mb_strtoupper($str, CF::$charset);
        }

        return CUTF8_Native::strtoupper($str);
    }

    /**
     * Makes a UTF-8 string's first character uppercase. This is a UTF8-aware
     * version of [ucfirst](http://php.net/ucfirst).
     *
     *     $str = CUTF8::ucfirst($str);
     *
     * @param string $str mixed case string
     *
     *
     * @return string
     */
    public static function ucfirst($str) {
        return CUTF8_Native::ucfirst($str);
    }

    /**
     * Makes the first character of every word in a UTF-8 string uppercase.
     * This is a UTF8-aware version of [ucwords](http://php.net/ucwords).
     *
     *     $str = CUTF8::ucwords($str);
     *
     * @param string $str mixed case string
     *
     *
     * @return string
     */
    public static function ucwords($str) {
        return CUTF8_Native::ucwords($str);
    }

    /**
     * Case-insensitive UTF-8 string comparison. This is a UTF8-aware version
     * of [strcasecmp](http://php.net/strcasecmp).
     *
     *     $compare = CUTF8::strcasecmp($str1, $str2);
     *
     * @param string $str1 string to compare
     * @param string $str2 string to compare
     *
     *
     * @return int less than 0 if str1 is less than str2, greater than 0 if str1 is greater than str2, 0 if they are equal
     */
    public static function strcasecmp($str1, $str2) {
        return CUTF8_Native::strcasecmp($str1, $str2);
    }

    /**
     * Returns a string or an array with all occurrences of search in subject
     * (ignoring case) and replaced with the given replace value. This is a
     * UTF8-aware version of [str_ireplace](http://php.net/str_ireplace).
     *
     * [!!] This function is very slow compared to the native version. Avoid
     * using it when possible.
     *
     * @param string|array $search  text to replace
     * @param string|array $replace replacement text
     * @param string|array $str     subject text
     * @param int          $count   number of matched and replaced needles will be returned via this parameter which is passed by reference
     *
     *
     * @return string|array if the input was a string|if the input was an array
     */
    public static function strIreplace($search, $replace, $str, &$count = null) {
        return CUTF8_Native::strIreplace($search, $replace, $str, $count);
    }

    /**
     * Case-insensitive UTF-8 version of strstr. Returns all of input string
     * from the first occurrence of needle to the end. This is a UTF8-aware
     * version of [stristr](http://php.net/stristr).
     *
     *     $found = CUTF8::stristr($str, $search);
     *
     * @param string $str    input string
     * @param string $search needle
     *
     *
     * @return string|false matched substring if found|if the substring was not found
     */
    public static function stristr($str, $search) {
        return CUTF8_Native::stristr($str, $search);
    }

    /**
     * Finds the length of the initial segment matching mask. This is a
     * UTF8-aware version of [strspn](http://php.net/strspn).
     *
     *     $found = CUTF8::strspn($str, $mask);
     *
     * @param string $str    input string
     * @param string $mask   mask for search
     * @param int    $offset start position of the string to examine
     * @param int    $length length of the string to examine
     *
     *
     * @return int length of the initial segment that contains characters in the mask
     */
    public static function strspn($str, $mask, $offset = null, $length = null) {
        return CUTF8_Native::strspn($str, $mask, $offset, $length);
    }

    /**
     * Finds the length of the initial segment not matching mask. This is a
     * UTF8-aware version of [strcspn](http://php.net/strcspn).
     *
     *     $found = CUTF8::strcspn($str, $mask);
     *
     * @param string $str    input string
     * @param string $mask   mask for search
     * @param int    $offset start position of the string to examine
     * @param int    $length length of the string to examine
     *
     *
     * @return int length of the initial segment that contains characters not in the mask
     */
    public static function strcspn($str, $mask, $offset = null, $length = null) {
        return CUTF8_Native::strcspn($str, $mask, $offset, $length);
    }

    /**
     * Pads a UTF-8 string to a certain length with another string. This is a
     * UTF8-aware version of [str_pad](http://php.net/str_pad).
     *
     *     $str = CUTF8::str_pad($str, $length);
     *
     * @param string $str              input string
     * @param int    $final_str_length desired string length after padding
     * @param string $pad_str          string to use as padding
     * @param string $pad_type         padding type: STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH
     *
     *
     * @return string
     */
    public static function strPad($str, $final_str_length, $pad_str = ' ', $pad_type = STR_PAD_RIGHT) {
        return CUTF8_Native::strPad($str, $final_str_length, $pad_str, $pad_type);
    }

    /**
     * Converts a UTF-8 string to an array. This is a UTF8-aware version of
     * [str_split](http://php.net/str_split).
     *
     *     $array = CUTF8::str_split($str);
     *
     * @param string $str          input string
     * @param int    $split_length maximum length of each chunk
     *
     *
     * @return array
     */
    public static function strSplit($str, $split_length = 1) {
        return CUTF8_Native::strSplit($str, $split_length);
    }

    /**
     * Reverses a UTF-8 string. This is a UTF8-aware version of [strrev](http://php.net/strrev).
     *
     *     $str = CUTF8::strrev($str);
     *
     * @param string $str string to be reversed
     *
     *
     * @return string
     */
    public static function strrev($str) {
        return CUTF8_Native::strrev($str);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning and
     * end of a string. This is a UTF8-aware version of [trim](http://php.net/trim).
     *
     *     $str = CUTF8::trim($str);
     *
     * @param string $str      input string
     * @param string $charlist string of characters to remove
     *
     *
     * @return string
     */
    public static function trim($str, $charlist = null) {
        return CUTF8_Native::trim($str, $charlist);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning of
     * a string. This is a UTF8-aware version of [ltrim](http://php.net/ltrim).
     *
     *     $str = CUTF8::ltrim($str);
     *
     * @param string $str      input string
     * @param string $charlist string of characters to remove
     *
     *
     * @return string
     */
    public static function ltrim($str, $charlist = null) {
        return CUTF8_Native::ltrim($str, $charlist);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the end of a string.
     * This is a UTF8-aware version of [rtrim](http://php.net/rtrim).
     *
     *     $str = CUTF8::rtrim($str);
     *
     * @param string $str      input string
     * @param string $charlist string of characters to remove
     *
     *
     * @return string
     */
    public static function rtrim($str, $charlist = null) {
        return CUTF8_Native::rtrim($str, $charlist);
    }

    /**
     * Returns the unicode ordinal for a character. This is a UTF8-aware
     * version of [ord](http://php.net/ord).
     *
     *     $digit = CUTF8::ord($character);
     *
     * @param string $chr UTF-8 encoded character
     *
     *
     * @return int
     */
    public static function ord($chr) {
        return CUTF8_Native::ord($chr);
    }

    /**
     * Takes an UTF-8 string and returns an array of ints representing the Unicode characters.
     * Astral planes are supported i.e. the ints in the output can be > 0xFFFF.
     * Occurrences of the BOM are ignored. Surrogates are not allowed.
     *
     *     $array = CUTF8::to_unicode($str);
     *
     * The Original Code is Mozilla Communicator client code.
     * The Initial Developer of the Original Code is Netscape Communications Corporation.
     * Portions created by the Initial Developer are Copyright (C) 1998 the Initial Developer.
     * Ported to PHP by Henri Sivonen <hsivonen@iki.fi>, see <http://hsivonen.iki.fi/php-utf8/>
     * Slight modifications to fit with phputf8 library by Harry Fuecks <hfuecks@gmail.com>
     *
     * @param string $str UTF-8 encoded string
     *
     * @return array|false unicode code points|if the string is invalid
     */
    public static function toUnicode($str) {
        return CUTF8_Native::toUnicode($str);
    }

    /**
     * Takes an array of ints representing the Unicode characters and returns a UTF-8 string.
     * Astral planes are supported i.e. the ints in the input can be > 0xFFFF.
     * Occurrences of the BOM are ignored. Surrogates are not allowed.
     *
     *     $str = CUTF8::to_unicode($array);
     *
     * The Original Code is Mozilla Communicator client code.
     * The Initial Developer of the Original Code is Netscape Communications Corporation.
     * Portions created by the Initial Developer are Copyright (C) 1998 the Initial Developer.
     * Ported to PHP by Henri Sivonen <hsivonen@iki.fi>, see http://hsivonen.iki.fi/php-utf8/
     * Slight modifications to fit with phputf8 library by Harry Fuecks <hfuecks@gmail.com>.
     *
     * @param array $arr unicode code points representing a string
     *
     * @return string|bool utf8 string of characters|FALSE if a code point cannot be found
     */
    public static function fromUnicode($arr) {
        return CUTF8_Native::fromUnicode($arr);
    }

    public static function mbstringEnabled() {
        if (static::$mbstringEnabled === null) {
            static::$mbstringEnabled = extension_loaded('mbstring');
        }

        return static::$mbstringEnabled;
    }
}
