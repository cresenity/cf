<?php

defined('SYSPATH') or die('No direct access allowed.');

//@codingStandardsIgnoreStart
/**
 * @deprecated 1.6, use c::transform
 */
class ctransform {
    /**
     * @param mixed $rp
     * @param mixed $decimal
     * @param bool  $always_decimal
     *
     * @deprecated since 1.3, use c::app()->formatter()
     *
     * @return string
     */
    public static function thousand_separator($rp, $decimal = null, $always_decimal = false) {
        CF::deprecated(__METHOD__, 'c::app()->formatter()', '1.3', null, true);
        $minus_str = '';
        $rp = floatval($rp);
        $rp = sprintf('%d', $rp);
        if (strlen($rp) == 0) {
            return $rp;
        }
        $ds = c::formatter()->getDecimalSeparator();
        if ($ds == null) {
            $ds = '.'; //decimal separator
        }
        $ts = c::formatter()->getThousandSeparator();
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

    public static function short_date_format($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        if (strlen($x) > 10) {
            $x = substr($x, 0, 10);
        }

        return $x;
    }

    public static function uppercase($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return strtoupper($x);
    }

    public static function lowercase($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return strtolower($x);
    }

    public static function month_name($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return cutils::month_name($x);
    }

    public static function html_specialchars($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return chtml::specialchars($x);
    }

    public static function lang($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return clang::__($x);
    }

    public static function date_formatted($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        if (strlen($x) == 0) {
            return $x;
        }
        $date_format = ccfg::get('date_formatted');
        if (strlen($date_format) == 0) {
            return $x;
        }

        return date($date_format, strtotime($x));
    }

    public static function long_date_formatted($x, $unformat = false) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        if (strlen($x) == 0) {
            return $x;
        }
        $long_date_format = ccfg::get('long_date_formatted');
        if (strlen($long_date_format) == 0) {
            return $x;
        }
        if ($unformat == true) {
            return ctransform::unformat_datetime($x);
        } else {
            return date($long_date_format, strtotime($x));
        }
    }

    public static function format_date($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return self::date_formatted($x);
    }

    public static function unformat_date($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return date('Y-m-d', strtotime($x));
    }

    public static function format_long_date($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return self::long_date_formatted($x);
    }

    public static function format_datetime($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return self::long_date_formatted($x);
    }

    public static function unformat_long_date($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return date('Y-m-d H:i:s', strtotime($x));
    }

    public static function unformat_datetime($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        return date('Y-m-d H:i:s', strtotime($x));
    }

    public static function format_currency($x, $unformat = false) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
        if ($unformat) {
            return self::unformat_currency($x);
        } else {
            return self::thousand_separator($x);
        }
    }

    public static function unformat_currency($x) {
        CF::deprecated(__METHOD__, 'c::transform', '1.6', null, true);
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
//@codingStandardsIgnoreEnd
