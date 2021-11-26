<?php

class CManager_Transform_DefaultMethod {
    public static function thousandSeparator($rp, $decimal = null, $always_decimal = false) {
        $minus_str = '';
        $rp = floatval($rp);
        $rp = sprintf('%d', $rp);
        if (strlen($rp) == 0) {
            return $rp;
        }
        $ds = ccfg::get('decimal_separator');
        if ($ds == null) {
            $ds = '.'; //decimal separator
        }
        $ts = ccfg::get('thousand_separator');
        if ($ts == null) {
            $ts = ','; //thousand separator
        }

        if (strpos($rp, '-') !== false) {
            $minus_str = substr($rp, 0, strpos($rp, '-') + 1);
            $rp = substr($rp, strpos($rp, '-') + 1);
        }
        $rupiah = '';
        $float = '';
        if (strpos($rp, '.') > 0) {
            $float = substr($rp, strpos($rp, '.'));
            if (strlen($float) > 3) {
                $char3 = $float[3];
                if ($char3 >= 5) {
                    $float[2] = $float[2] + 1;
                } else {
                    $float[2] = 0;
                }
            }

            $rp = substr($rp, 0, strpos($rp, '.'));
        }

        $p = strlen($rp);
        while ($p > 3) {
            $rupiah = $ts . substr($rp, -3) . $rupiah;
            $l = strlen($rp) - 3;
            $rp = substr($rp, 0, $l);
            $p = strlen($rp);
        }
        $rupiah = $rp . $rupiah;
        if ($decimal !== null) {
            if (strlen($float) > $decimal) {
                $float = substr($float, 0, $decimal + 1);
            }
        }

        $float = str_replace('.', $ds, $float);
        if ($always_decimal == false) {
            if ($float == '.00') {
                $float = '';
            }
        }
        $digit = ccfg::get('decimal_digit');
        if ($decimal === null) {
            if ($digit != null) {
                $float = substr($float, 0, $digit + 1) . '';
                if ($float == '') {
                    $float = $ds . str_repeat('0', $digit);
                }
            }
        }
        // remove char .
        if ($decimal === 0 || $digit == 0) {
            $float = '';
        }
        /*
          if(strlen($float)>3) {
          $float = substr($float,0,3);
          }

         */
        return $minus_str . $rupiah . $float;
    }

    public static function shortDateFormat($x) {
        if (strlen($x) > 10) {
            $x = substr($x, 0, 10);
        }

        return $x;
    }

    public static function uppercase($x) {
        return strtoupper($x);
    }

    public static function lowercase($x) {
        return strtolower($x);
    }

    public static function monthName($month) {
        $list = [
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

        if (isset($list[$month])) {
            return $list[$month];
        }

        return 'Unknown';
    }

    public static function htmlSpecialChars($x) {
        return c::e($x);
    }

    public static function lang($x) {
        return c::__($x);
    }

    public static function formatDate($x) {
        if (strlen($x) == 0) {
            return $x;
        }
        $date_format = ccfg::get('date_formatted');
        if (strlen($date_format) == 0) {
            return $x;
        }

        return date($date_format, strtotime($x));
    }

    public static function unformatDate($x) {
        return date('Y-m-d', strtotime($x));
    }

    public static function formatDatetime($x) {
        if (strlen($x) == 0) {
            return $x;
        }
        $long_date_format = ccfg::get('long_date_formatted');
        if (strlen($long_date_format) == 0) {
            return $x;
        }

        return date($long_date_format, strtotime($x));
    }

    public static function unformatDatetime($x) {
        return date('Y-m-d H:i:s', strtotime($x));
    }

    public static function formatCurrency($x, $unformat = false) {
        if ($unformat) {
            return self::unformatCurrency($x);
        } else {
            return self::thousandSeparator($x);
        }
    }

    public static function unformatCurrency($x) {
        $ds = ccfg::get('decimal_separator');
        if ($ds == null) {
            $ds = '.'; //decimal separator
        }
        $ts = ccfg::get('thousand_separator');
        if ($ts == null) {
            $ts = ','; //thousand separator
        }
        $ret = $x;
        $ret = str_replace($ts, '', $ret);
        $ret = str_replace($ds, '.', $ret);

        return $ret;
    }
}
