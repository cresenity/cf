<?php
defined('SYSPATH') or die('No direct access allowed.');

//@codingStandardsIgnoreStart
/**
 * @deprecated 1.6 use CValidation
 */
class cvalid {
    /**
     * Validate email, commonly used characters only.
     *
     * @param   string   email address
     * @param mixed $email
     *
     * @return bool
     */
    public static function email($email) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);

        //return (bool) preg_match('/^[-_a-z0-9\'+*$^&%=~!?{}]++(?:\.[-_a-z0-9\'+*$^&%=~!?{}]+)*+@(?:(?![-.])[-a-z0-9.]+(?<![-.])\.[a-z]{2,6}|\d{1,3}(?:\.\d{1,3}){3})(?::\d++)?$/iD', (string) $email);
    }

    /**
     * Validate the domain of an email address by checking if the domain has a
     * valid MX record.
     *
     * @param   string   email address
     * @param mixed $email
     *
     * @return bool
     */
    public static function email_domain($email) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        // If we can't prove the domain is invalid, consider it valid
        // Note: checkdnsrr() is not implemented on Windows platforms
        if (!function_exists('checkdnsrr')) {
            return true;
        }

        // Check if the email domain has a valid MX record
        return (bool) checkdnsrr(preg_replace('/^[^@]+@/', '', $email), 'MX');
    }

    /**
     * Validate email, RFC compliant version
     * Note: This function is LESS strict than valid_email. Choose carefully.
     *
     * @see  Originally by Cal Henderson, modified to fit Kohana syntax standards:
     * @see  http://www.iamcal.com/publish/articles/php/parsing_email/
     * @see  http://www.w3.org/Protocols/rfc822/
     *
     * @param   string   email address
     * @param mixed $email
     *
     * @return bool
     */
    public static function email_rfc($email) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        $qtext = '[^\\x0d\\x22\\x5c\\x80-\\xff]';
        $dtext = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
        $atom = '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+';
        $pair = '\\x5c[\\x00-\\x7f]';

        $domain_literal = "\\x5b($dtext|$pair)*\\x5d";
        $quoted_string = "\\x22($qtext|$pair)*\\x22";
        $sub_domain = "($atom|$domain_literal)";
        $word = "($atom|$quoted_string)";
        $domain = "$sub_domain(\\x2e$sub_domain)*";
        $local_part = "$word(\\x2e$word)*";
        $addr_spec = "$local_part\\x40$domain";

        return (bool) preg_match('/^' . $addr_spec . '$/D', (string) $email);
    }

    /**
     * Validate URL.
     *
     * @param   string   URL
     * @param mixed $url
     *
     * @return bool
     */
    public static function url($url) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        return (bool) filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED);
    }

    /**
     * Validate IP.
     *
     * @param   string   IP address
     * @param   bool  allow IPv6 addresses
     * @param   bool  allow private IP networks
     * @param mixed $ip
     * @param mixed $ipv6
     * @param mixed $allow_private
     *
     * @return bool
     */
    public static function ip($ip, $ipv6 = false, $allow_private = true) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        // By default do not allow private and reserved range IPs
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if ($allow_private === true) {
            $flags = FILTER_FLAG_NO_RES_RANGE;
        }

        if ($ipv6 === true) {
            return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flags);
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flags | FILTER_FLAG_IPV4);
    }

    /**
     * Validates a credit card number using the Luhn (mod10) formula.
     *
     * @see http://en.wikipedia.org/wiki/Luhn_algorithm
     *
     * @param   int       credit card number
     * @param   string|array  card type, or an array of card types
     * @param mixed      $number
     * @param null|mixed $type
     *
     * @return bool
     */
    public static function credit_card($number, $type = null) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        // Remove all non-digit characters from the number
        if (($number = preg_replace('/\D+/', '', $number)) === '') {
            return false;
        }

        if ($type == null) {
            // Use the default type
            $type = 'default';
        } elseif (is_array($type)) {
            foreach ($type as $t) {
                // Test each type for validity
                if (cvalid::credit_card($number, $t)) {
                    return true;
                }
            }

            return false;
        }

        $cards = CF::config('credit_cards');

        // Check card type
        $type = strtolower($type);

        if (!isset($cards[$type])) {
            return false;
        }

        // Check card number length
        $length = strlen($number);

        // Validate the card length by the card type
        if (!in_array($length, preg_split('/\D+/', $cards[$type]['length']))) {
            return false;
        }

        // Check card number prefix
        if (!preg_match('/^' . $cards[$type]['prefix'] . '/', $number)) {
            return false;
        }

        // No Luhn check required
        if ($cards[$type]['luhn'] == false) {
            return true;
        }

        // Checksum of the card number
        $checksum = 0;

        for ($i = $length - 1; $i >= 0; $i -= 2) {
            // Add up every 2nd digit, starting from the right
            $checksum += $number[$i];
        }

        for ($i = $length - 2; $i >= 0; $i -= 2) {
            // Add up every 2nd digit doubled, starting from the right
            $double = $number[$i] * 2;

            // Subtract 9 from the double where value is greater than 10
            $checksum += ($double >= 10) ? $double - 9 : $double;
        }

        // If the checksum is a multiple of 10, the number is valid
        return $checksum % 10 === 0;
    }

    /**
     * Checks if a phone number is valid.
     *
     * @param   string   phone number to check
     * @param mixed      $number
     * @param null|mixed $lengths
     *
     * @return bool
     */
    public static function phone($number, $lengths = null) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        if (!is_array($lengths)) {
            $lengths = [7, 10, 11];
        }

        // Remove all non-digit characters from the number
        $number = preg_replace('/\D+/', '', $number);

        // Check if the number is within range
        return in_array(strlen($number), $lengths);
    }

    /**
     * Tests if a string is a valid date string.
     *
     * @param   string   date to check
     * @param mixed $str
     *
     * @return bool
     */
    public static function date($str) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        return strtotime($str) !== false;
    }

    /**
     * Checks whether a string consists of alphabetical characters only.
     *
     * @param   string   input string
     * @param   bool  trigger UTF-8 compatibility
     * @param mixed $str
     * @param mixed $utf8
     *
     * @return bool
     */
    public static function alpha($str, $utf8 = false) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        return ($utf8 === true)
            ? (bool) preg_match('/^\pL++$/uD', (string) $str)
            : ctype_alpha((string) $str);
    }

    /**
     * Checks whether a string consists of alphabetical characters and numbers only.
     *
     * @param   string   input string
     * @param   bool  trigger UTF-8 compatibility
     * @param mixed $str
     * @param mixed $utf8
     *
     * @return bool
     */
    public static function alpha_numeric($str, $utf8 = false) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        return ($utf8 === true)
            ? (bool) preg_match('/^[\pL\pN]++$/uD', (string) $str)
            : ctype_alnum((string) $str);
    }

    /**
     * Checks whether a string consists of alphabetical characters, numbers, underscores and dashes only.
     *
     * @param   string   input string
     * @param   bool  trigger UTF-8 compatibility
     * @param mixed $str
     * @param mixed $utf8
     *
     * @return bool
     */
    public static function alpha_dash($str, $utf8 = false) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        return ($utf8 === true)
            ? (bool) preg_match('/^[-\pL\pN_]++$/uD', (string) $str)
            : (bool) preg_match('/^[-a-z0-9_]++$/iD', (string) $str);
    }

    /**
     * Checks whether a string consists of digits only (no dots or dashes).
     *
     * @param   string   input string
     * @param   bool  trigger UTF-8 compatibility
     * @param mixed $str
     * @param mixed $utf8
     *
     * @return bool
     */
    public static function digit($str, $utf8 = false) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        return ($utf8 === true)
            ? (bool) preg_match('/^\pN++$/uD', (string) $str)
            : ctype_digit((string) $str);
    }

    /**
     * Checks whether a string is a valid number (negative and decimal numbers allowed).
     *
     * @see Uses locale conversion to allow decimal point to be locale specific.
     * @see http://www.php.net/manual/en/function.localeconv.php
     *
     * @param   string   input string
     * @param mixed $str
     *
     * @return bool
     */
    public static function numeric($str) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        // Use localeconv to set the decimal_point value: Usually a comma or period.
        $locale = localeconv();

        return (bool) preg_match('/^-?[0-9' . $locale['decimal_point'] . ']++$/D', (string) $str);
    }

    /**
     * Checks whether a string is a valid text. Letters, numbers, whitespace,
     * dashes, periods, and underscores are allowed.
     *
     * @param   string   text to check
     * @param mixed $str
     *
     * @return bool
     */
    public static function standard_text($str) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        // pL matches letters
        // pN matches numbers
        // pZ matches whitespace
        // pPc matches underscores
        // pPd matches dashes
        // pPo matches normal puncuation
        return (bool) preg_match('/^[\pL\pN\pZ\p{Pc}\p{Pd}\p{Po}]++$/uD', (string) $str);
    }

    /**
     * Checks if a string is a proper decimal format. The format array can be
     * used to specify a decimal length, or a number and decimal length, eg:
     * array(2) would force the number to have 2 decimal places, array(4,2)
     * would force the number to have 4 digits and 2 decimal places.
     *
     * @param   string   input string
     * @param   array    decimal format: y or x,y
     * @param mixed      $str
     * @param null|mixed $format
     *
     * @return bool
     */
    public static function decimal($str, $format = null) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        // Create the pattern
        $pattern = '/^[0-9]%s\.[0-9]%s$/';

        if (!empty($format)) {
            if (count($format) > 1) {
                // Use the format for number and decimal length
                $pattern = sprintf($pattern, '{' . $format[0] . '}', '{' . $format[1] . '}');
            } elseif (count($format) > 0) {
                // Use the format as decimal length
                $pattern = sprintf($pattern, '+', '{' . $format[0] . '}');
            }
        } else {
            // No format
            $pattern = sprintf($pattern, '+', '+');
        }

        return (bool) preg_match($pattern, (string) $str);
    }

    public static function passport($passport) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        // Passport must be only digits
        if (preg_match("/^\d{10}$|^\d{12}$/", $passport) === 0) {
            return false;
        }

        return true;
    }

    public function mysql_date($date) {
        CF::deprecated(__METHOD__, 'CValidation', '1.6', null, true);
        // Date mask YYYY-MM-DD
        if (preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/', $date) === 0) {
            return false;
        }

        return true;
    }
} // End valid
