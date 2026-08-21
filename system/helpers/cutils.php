<?php

//@codingStandardsIgnoreStart

class cutils {
    public static function indent($n, $char = "\t") {
        $res = '';
        for ($i = 0; $i < $n; $i++) {
            $res .= $char;
        }

        return $res;
    }

    public static function br() {
        return "\r\n";
    }

    public function add_date($givendate, $day = 0, $mth = 0, $yr = 0) {
        $cd = strtotime($givendate);
        $newdate = date('Y-m-d h:i:s', mktime(date('h', $cd), date('i', $cd), date('s', $cd), date('m', $cd) + $mth, date('d', $cd) + $day, date('Y', $cd) + $yr));

        return $newdate;
    }

    public static function get_month($strdate = '') {
        if (strlen($strdate) > 0) {
            return date('m', strtotime($strdate));
        }

        return date('m');
    }

    public static function get_year($strdate = '') {
        if (strlen($strdate) > 0) {
            return date('Y', strtotime($strdate));
        }

        return date('Y');
    }

    public static function get_day($strdate = '') {
        if (strlen($strdate) > 0) {
            return date('d', strtotime($strdate));
        }

        return date('d');
    }

    public static function get_short_day_name($day, $month, $year) {
        $value = '';
        $timestamp = mktime(0, 0, 0, $month, $day, $year);
        $value = date('D', $timestamp);

        return $value;
    }

    public static function day_count($month = '', $year = '') {
        if (empty($month)) {
            $month = date('m');
        }
        if (empty($year)) {
            $year = date('Y');
        }

        return date('d', mktime(0, 0, 0, $month + 1, 0, $year));
    }

    public static function begin_date_month() {
        $d1 = '1';
        $m1 = date('m');
        $y1 = date('Y');
        $date1 = $y1 . '-' . $m1 . '-' . $d1;
        $date_format = ccfg::get('date_formatted');
        if ($date_format != null) {
            $date1 = date($date_format, strtotime($date1));
        }

        return $date1;
    }

    public static function last_date_month() {
        $d2 = cutils::day_count();
        $m2 = date('m');
        $y2 = date('Y');
        $date2 = $y2 . '-' . $m2 . '-' . $d2;
        $date_format = ccfg::get('date_formatted');
        if ($date_format != null) {
            $date2 = date($date_format, strtotime($date2));
        }

        return $date2;
    }

    /**
     * Nilai acak 32 karakter heks, panjang dan bentuknya sama dengan md5.
     *
     * @return string
     */
    public static function randmd5() {
        return bin2hex(random_bytes(16));
    }

    public static function format_filesize($size) {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }

    public static function thousand_separator($rp) {
        return ctransform::thousand_separator($rp);
    }

    public static function get_under_1000($val) {
        $C_NUMBER = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan'];
        $res = '';
        if ($val >= 1000) {
            return $res;
        }
        //process hundred
        $tempv = $val;
        if ($tempv >= 100) {
            if (floor($tempv / 100) == 1) {
                $res = $res . ' Seratus';
            } else {
                $res .= ' ' . $C_NUMBER[floor($tempv / 100)] . ' Ratus';
            }
        }
        //process ten
        $tempv = $val % 100;
        if ($tempv >= 10) {
            if (floor($tempv / 10) == 1) {
                if ($tempv % 10 == 0) {
                    $res .= ' Sepuluh';
                } elseif ($tempv % 10 == 1) {
                    $res .= ' Sebelas';
                } else {
                    $res .= ' ' . $C_NUMBER[$tempv % 10] . ' Belas';
                }
            } else {
                $res .= ' ' . $C_NUMBER[floor($tempv / 10)] . ' Puluh';
                if ($tempv % 10 > 0) {
                    $res .= ' ' . $C_NUMBER[$tempv % 10];
                }
            }
        } else {
            if ($tempv % 10 > 0) {
                $res .= ' ' . $C_NUMBER[$tempv % 10];
            }
        }
        $res = trim($res);

        return $res;
    }

    public static function indonesian_currency_string($val) {
        $res = '';
        $tempval = $val;
        if ($tempval >= 1000000000000) {
            $temp_under_1000 = floor($tempval / 1000000000000);
            $res = $res . ' ' . self::get_under_1000($temp_under_1000) . ' Triliun';
        }
        $tempval = $val % 1000000000000;
        if ($tempval >= 1000000000) {
            $temp_under_1000 = floor($tempval / 1000000000);
            $res = $res . ' ' . self::get_under_1000($temp_under_1000) . ' Miliar';
        }
        $tempval = $val % 1000000000;
        if (floor($val / 1000000) > 0) {
            $temp_under_1000 = floor($tempval / 1000000);
            $res = $res . ' ' . self::get_under_1000($temp_under_1000) . ' Juta';
        }
        $tempval = $val % 1000000;
        //		if (floor($val / 1000) > 0)  {
        if ($tempval >= 1000) {
            $temp_under_1000 = floor($tempval / 1000);
            if ($temp_under_1000 == 1) {
                $res = $res . ' Seribu';
            } else {
                $res = $res . ' ' . self::get_under_1000($temp_under_1000) . ' Ribu';
            }
        }
        $tempval = $val % 1000;
        $res = $res . ' ' . self::get_under_1000($tempval);
        $result = trim($res);

        return $result;
    }

    public static function day_list() {
        $day = [];
        for ($i = 1; $i <= 31; $i++) {
            $day['' . $i] = $i;
        }

        return $day;
    }

    public static function day_name_list() {
        $timestamp = strtotime('next Sunday');
        $day_list = [];
        for ($i = 0; $i < 7; $i++) {
            $day_list[] = strftime('%A', $timestamp);
            $timestamp = strtotime('+1 day', $timestamp);
        }

        return $day_list;
    }

    public static function month_list($clang = true) {
        $ret = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        ];
        if ($clang) {
            foreach ($ret as $k => $v) {
                $ret[$k] = c::__($v);
            }
        }

        return $ret;
    }

    public static function month_name($month, $clang = true) {
        $list = static::month_list($clang);
        if (isset($list[$month])) {
            return $list[$month];
        }

        return 'Unknown';
    }

    public static function year_list($start = '', $end = '') {
        if (strlen($start) == 0) {
            $start = '1900';
        }
        if (strlen($end) == 0) {
            $end = cutils::get_year();
        }
        if ($start > $end) {
            return [];
        }
        $list = [];
        for ($i = $start; $i <= $end; $i++) {
            $list[$i] = $i;
        }

        return $list;
    }

    public static function sanitize_msisdn($msisdn, $prefix = '62') {
        $ret = $msisdn;
        if (strlen($ret) > 0) {
            if (substr($ret, 0, 1) == '+') {
                $ret = substr($ret, 1);
            }
            if (substr($ret, 0, 1) == '0') {
                $ret = $prefix . substr($ret, 1);
            }
        }

        return $ret;
    }

    public static function sanitize($string = '', $is_filename = false) {
        // Replace all weird characters with dashes
        $string = preg_replace('/[^\w\-' . ($is_filename ? '~_\.' : '') . ']+/u', '-', $string);

        // Only allow one dash separator at a time (and make string lowercase)
        return mb_strtolower(preg_replace('/--+/u', '-', $string), 'UTF-8');
    }

    public static function month_romawi($bln) {
        $result = '';
        switch ($bln) {
            case '1': $result = 'I';

                break;
            case '2': $result = 'II';

                break;
            case '3': $result = 'III';

                break;
            case '4': $result = 'IV';

                break;
            case '5': $result = 'V';

                break;
            case '6': $result = 'VI';

                break;
            case '7': $result = 'VII';

                break;
            case '8': $result = 'VIII';

                break;
            case '9': $result = 'IX';

                break;
            case '10': $result = 'X';

                break;
            case '11': $result = 'XI';

                break;
            case '12': $result = 'XII';

                break;
        }

        return $result;
    }

    public static function trim_csv($text) {
        if ($text == null) {
            $text = '';
        }
        $text = str_replace("\r\n", '', $text);
        $text = str_replace("\r", '', $text);
        $text = str_replace("\n", '', $text);

        return $text;
    }

    public static function year_diff($from, $to = '') {
        if ($to == '') {
            $to = time();
        }
        if (is_string($from)) {
            $from = strtotime($from);
        }
        if (is_string($to)) {
            $to = strtotime($to);
        }
        $from = new DateTime(date('Y-m-d H:i:s', $from));
        $to = new DateTime(date('Y-m-d H:i:s', $to));
        $seconds = (($to->format('U') - $from->format('U')));
        $minutes = (($to->format('U') - $from->format('U')) / (60));
        $hours = (($to->format('U') - $from->format('U')) / (60 * 60));
        $days = (($to->format('U') - $from->format('U')) / (60 * 60 * 24));
        $months = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30));
        $years = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30 * 12));

        return $years;
    }

    public static function month_diff($from, $to = '') {
        if ($to == '') {
            $to = time();
        }
        if (is_string($from)) {
            $from = strtotime($from);
        }
        if (is_string($to)) {
            $to = strtotime($to);
        }
        $from = new DateTime(date('Y-m-d H:i:s', $from));
        $to = new DateTime(date('Y-m-d H:i:s', $to));
        $seconds = (($to->format('U') - $from->format('U')));
        $minutes = (($to->format('U') - $from->format('U')) / (60));
        $hours = (($to->format('U') - $from->format('U')) / (60 * 60));
        $days = (($to->format('U') - $from->format('U')) / (60 * 60 * 24));
        $months = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30));
        $years = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30 * 12));

        return $months;
    }

    public static function day_diff($from, $to = '') {
        if ($to == '') {
            $to = time();
        }
        if (is_string($from)) {
            $from = strtotime($from);
        }
        if (is_string($to)) {
            $to = strtotime($to);
        }
        $from = new DateTime(date('Y-m-d H:i:s', $from));
        $to = new DateTime(date('Y-m-d H:i:s', $to));
        $seconds = (($to->format('U') - $from->format('U')));
        $minutes = (($to->format('U') - $from->format('U')) / (60));
        $hours = (($to->format('U') - $from->format('U')) / (60 * 60));
        $days = (($to->format('U') - $from->format('U')) / (60 * 60 * 24));
        $months = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30));
        $years = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30 * 12));

        return $days;
    }

    public static function hour_diff($from, $to = '') {
        if ($to == '') {
            $to = time();
        }
        if (is_string($from)) {
            $from = strtotime($from);
        }
        if (is_string($to)) {
            $to = strtotime($to);
        }
        $from = new DateTime(date('Y-m-d H:i:s', $from));
        $to = new DateTime(date('Y-m-d H:i:s', $to));
        $seconds = (($to->format('U') - $from->format('U')));
        $minutes = (($to->format('U') - $from->format('U')) / (60));
        $hours = (($to->format('U') - $from->format('U')) / (60 * 60));
        $days = (($to->format('U') - $from->format('U')) / (60 * 60 * 24));
        $months = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30));
        $years = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30 * 12));

        return $hours;
    }

    public static function minute_diff($from, $to = '') {
        if ($to == '') {
            $to = time();
        }
        if (is_string($from)) {
            $from = strtotime($from);
        }
        if (is_string($to)) {
            $to = strtotime($to);
        }
        $from = new DateTime(date('Y-m-d H:i:s', $from));
        $to = new DateTime(date('Y-m-d H:i:s', $to));
        $seconds = (($to->format('U') - $from->format('U')));
        $minutes = (($to->format('U') - $from->format('U')) / (60));
        $hours = (($to->format('U') - $from->format('U')) / (60 * 60));
        $days = (($to->format('U') - $from->format('U')) / (60 * 60 * 24));
        $months = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30));
        $years = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30 * 12));

        return $minutes;
    }

    public static function second_diff($from, $to = '') {
        if ($to == '') {
            $to = time();
        }
        if (is_string($from)) {
            $from = strtotime($from);
        }
        if (is_string($to)) {
            $to = strtotime($to);
        }
        $from = new DateTime(date('Y-m-d H:i:s', $from));
        $to = new DateTime(date('Y-m-d H:i:s', $to));
        $seconds = (($to->format('U') - $from->format('U')));
        $minutes = (($to->format('U') - $from->format('U')) / (60));
        $hours = (($to->format('U') - $from->format('U')) / (60 * 60));
        $days = (($to->format('U') - $from->format('U')) / (60 * 60 * 24));
        $months = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30));
        $years = (($to->format('U') - $from->format('U')) / (60 * 60 * 24 * 30 * 12));

        return $seconds;
    }

    /**
     * Converts a unix timestamp to a relative time string, such as "3 days ago"
     * or "2 weeks ago".
     *
     * @param int    $from    The date to use as a starting point
     * @param int    $to      The date to compare to, defaults to now
     * @param string $suffix  The string to add to the end, defaults to " ago"
     * @param mixed  $as_text
     *
     * @return string
     */
    public static function human_time_diff($from, $to = '', $as_text = false, $suffix = ' ago') {
        if ($to == '') {
            $to = time();
        }

        if (is_string($from)) {
            $from = strtotime($from);
        }
        if (is_string($to)) {
            $to = strtotime($to);
        }

        $from = new \DateTime(date('Y-m-d H:i:s', $from));
        $to = new \DateTime(date('Y-m-d H:i:s', $to));
        $diff = $from->diff($to);

        if ($diff->y > 1) {
            $text = $diff->y . ' years';
        } elseif ($diff->y == 1) {
            $text = '1 year';
        } elseif ($diff->m > 1) {
            $text = $diff->m . ' months';
        } elseif ($diff->m == 1) {
            $text = '1 month';
        } elseif ($diff->d > 7) {
            $text = ceil($diff->d / 7) . ' weeks';
        } elseif ($diff->d == 7) {
            $text = '1 week';
        } elseif ($diff->d > 1) {
            $text = $diff->d . ' days';
        } elseif ($diff->d == 1) {
            $text = '1 day';
        } elseif ($diff->h > 1) {
            $text = $diff->h . ' hours';
        } elseif ($diff->h == 1) {
            $text = ' 1 hour';
        } elseif ($diff->i > 1) {
            $text = $diff->i . ' minutes';
        } elseif ($diff->i == 1) {
            $text = '1 minute';
        } elseif ($diff->s > 1) {
            $text = $diff->s . ' seconds';
        } else {
            $text = '1 second';
        }

        if ($as_text) {
            $text = explode(' ', $text, 2);
            $text = self::number_to_word($text[0]) . ' ' . $text[1];
        }

        return trim($text) . $suffix;
    }

    /**
     * Converts a number into the text equivalent. For example, 456 becomes four
     * hundred and fifty-six.
     *
     * Part of the IntToWords Project.
     *
     * @param int|float $number The number to convert into text
     *
     * @return string
     */
    public static function number_to_word($number) {
        $number = (string) $number;

        if (strpos($number, '.') !== false) {
            list($number, $decimal) = explode('.', $number);
        } else {
            $decimal = false;
        }

        $output = '';

        if ($number[0] == '-') {
            $output = 'negative ';
            $number = ltrim($number, '-');
        } elseif ($number[0] == '+') {
            $output = 'positive ';
            $number = ltrim($number, '+');
        }

        if ($number[0] == '0') {
            $output .= 'zero';
        } else {
            $length = 19;
            $number = str_pad($number, 60, '0', STR_PAD_LEFT);
            $group = rtrim(chunk_split($number, 3, ' '), ' ');
            $groups = explode(' ', $group);
            $groups2 = [];

            foreach ($groups as $group) {
                $group[1] = isset($group[1]) ? $group[1] : null;
                $group[2] = isset($group[2]) ? $group[2] : null;
                $groups2[] = self::numberToWordThreeDigits($group[0], $group[1], $group[2]);
            }

            for ($z = 0; $z < count($groups2); $z++) {
                if ($groups2[$z] != '') {
                    $output .= $groups2[$z] . self::numberToWordConvertGroup($length - $z);
                    $output .= ($z < $length && !array_search('', array_slice($groups2, $z + 1, -1)) && $groups2[$length] != '' && $groups[$length][0] == '0' ? ' and ' : ', ');
                }
            }

            $output = rtrim($output, ', ');
        }

        if ($decimal > 0) {
            $output .= ' point';

            for ($i = 0; $i < strlen($decimal); $i++) {
                $output .= ' ' . self::numberToWordConvertDigit($decimal[$i]);
            }
        }

        return $output;
    }

    protected static function numberToWordConvertGroup($index) {
        switch ($index) {
            case 11:
                return ' decillion';
            case 10:
                return ' nonillion';
            case 9:
                return ' octillion';
            case 8:
                return ' septillion';
            case 7:
                return ' sextillion';
            case 6:
                return ' quintrillion';
            case 5:
                return ' quadrillion';
            case 4:
                return ' trillion';
            case 3:
                return ' billion';
            case 2:
                return ' million';
            case 1:
                return ' thousand';
            case 0:
                return '';
        }

        return '';
    }

    protected static function numberToWordThreeDigits($digit1, $digit2, $digit3) {
        $output = '';

        if ($digit1 == '0' && $digit2 == '0' && $digit3 == '0') {
            return '';
        }

        if ($digit1 != '0') {
            $output .= self::numberToWordConvertDigit($digit1) . ' hundred';

            if ($digit2 != '0' || $digit3 != '0') {
                $output .= ' and ';
            }
        }
        if ($digit2 != '0') {
            $output .= self::numberToWordTwoDigits($digit2, $digit3);
        } elseif ($digit3 != '0') {
            $output .= self::numberToWordConvertDigit($digit3);
        }

        return $output;
    }

    protected static function numberToWordTwoDigits($digit1, $digit2) {
        if ($digit2 == '0') {
            switch ($digit1) {
                case '1':
                    return 'ten';
                case '2':
                    return 'twenty';
                case '3':
                    return 'thirty';
                case '4':
                    return 'forty';
                case '5':
                    return 'fifty';
                case '6':
                    return 'sixty';
                case '7':
                    return 'seventy';
                case '8':
                    return 'eighty';
                case '9':
                    return 'ninety';
            }
        } elseif ($digit1 == '1') {
            switch ($digit2) {
                case '1':
                    return 'eleven';
                case '2':
                    return 'twelve';
                case '3':
                    return 'thirteen';
                case '4':
                    return 'fourteen';
                case '5':
                    return 'fifteen';
                case '6':
                    return 'sixteen';
                case '7':
                    return 'seventeen';
                case '8':
                    return 'eighteen';
                case '9':
                    return 'nineteen';
            }
        } else {
            $second_digit = self::numberToWordConvertDigit($digit2);

            switch ($digit1) {
                case '2':
                    return "twenty-{$second_digit}";
                case '3':
                    return "thirty-{$second_digit}";
                case '4':
                    return "forty-{$second_digit}";
                case '5':
                    return "fifty-{$second_digit}";
                case '6':
                    return "sixty-{$second_digit}";
                case '7':
                    return "seventy-{$second_digit}";
                case '8':
                    return "eighty-{$second_digit}";
                case '9':
                    return "ninety-{$second_digit}";
            }
        }
    }

    /**
     * @param $digit
     *
     * @throws \LogicException
     *
     * @return string
     */
    protected static function numberToWordConvertDigit($digit) {
        switch ($digit) {
            case '0':
                return 'zero';
            case '1':
                return 'one';
            case '2':
                return 'two';
            case '3':
                return 'three';
            case '4':
                return 'four';
            case '5':
                return 'five';
            case '6':
                return 'six';
            case '7':
                return 'seven';
            case '8':
                return 'eight';
            case '9':
                return 'nine';
            default:
                throw new \LogicException('Not a number');
        }
    }
}
//@codingStandardsIgnoreEnd
