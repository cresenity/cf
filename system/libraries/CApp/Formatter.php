<?php

/**
 * This class for utilize application format.
 */
class CApp_Formatter {
    /**
     * @var string
     */
    protected $dateFormat;

    /**
     * @var string
     */
    protected $datetimeFormat;

    /**
     * @var string
     */
    protected $thousandSeparator;

    /**
     * @var string
     */
    protected $decimalSeparator;

    /**
     * @var int
     */
    protected $decimalDigit;

    /**
     * @var int
     */
    protected $currencyDecimalDigit;

    /**
     * @var string
     */
    protected $currencyPrefix;

    /**
     * @var string
     */
    protected $currencySuffix;

    /**
     * @var bool
     */
    protected $currencyStripZeroDecimal;

    /**
     * @var CApp_Formatter
     */
    private static $instance;

    /**
     * @return CApp_Formatter
     */
    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Formatter constructor.
     */
    private function __construct() {
        $this->dateFormat = CF::config('app.format.date', CF::config('date_formatted', 'Y-m-d'));
        $this->datetimeFormat = CF::config('app.format.datetime', CF::config('long_date_formatted', 'Y-m-d H:i:s'));
        $this->thousandSeparator = CF::config('app.format.thousand_separator', '.');
        $this->decimalSeparator = CF::config('app.format.decimal_separator', ',');
        $this->decimalDigit = CF::config('app.format.decimal_digit', 0);
        $this->currencyDecimalDigit = CF::config('app.format.currency_decimal_digit', null);
        $this->currencyPrefix = CF::config('app.format.currency_prefix', '');
        $this->currencySuffix = CF::config('app.format.currency_suffix', '');
        $this->currencyStripZeroDecimal = CF::config('app.format.currency_strip_zero_decimal', false);
    }

    /**
     * @return string
     */
    public function getDateFormat() {
        return $this->dateFormat;
    }

    /**
     * @param string $format
     *
     * @return CApp_Formatter
     */
    public function setDateFormat($format) {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * @return string
     */
    public function getDatetimeFormat() {
        return $this->datetimeFormat;
    }

    /**
     * @param string $format
     *
     * @return CApp_Formatter
     */
    public function setDatetimeFormat($format) {
        $this->datetimeFormat = $format;

        return $this;
    }

    /**
     * @return string
     */
    public function getThousandSeparator() {
        return $this->thousandSeparator;
    }

    /**
     * @param string $thousandSeparator
     *
     * @return CApp_Formatter
     */
    public function setThousandSeparator($thousandSeparator) {
        $this->thousandSeparator = $thousandSeparator;

        return $this;
    }

    /**
     * @return string
     */
    public function getDecimalSeparator() {
        return $this->decimalSeparator;
    }

    /**
     * @param string $decimalSeparator
     *
     * @return CApp_Formatter
     */
    public function setDecimalSeparator($decimalSeparator) {
        $this->decimalSeparator = $decimalSeparator;

        return $this;
    }

    /**
     * @return int
     */
    public function getDecimalDigit() {
        return $this->decimalDigit;
    }

    /**
     * @param int $decimalDigit
     *
     * @return CApp_Formatter
     */
    public function setDecimalDigit($decimalDigit) {
        $this->decimalDigit = $decimalDigit;

        return $this;
    }

    /**
     * @param int $decimalDigit
     *
     * @return CApp_Formatter
     */
    public function setCurrencyDecimalDigit($decimalDigit) {
        $this->currencyDecimalDigit = $decimalDigit;

        return $this;
    }

    /**
     * @return int
     */
    public function getCurrencyDecimalDigit() {
        return $this->currencyDecimalDigit;
    }

    /**
     * @param bool $isStriped
     *
     * @return CApp_Formatter
     */
    public function setCurrencyStripZeroDecimal($isStriped = true) {
        $this->currencyStripZeroDecimal = $isStriped;

        return $this;
    }

    /**
     * @return bool
     */
    public function getCurrencyStripZeroDecimal() {
        return $this->currencyStripZeroDecimal;
    }

    /**
     * @param string $prefix
     *
     * @return CApp_Formatter
     */
    public function setCurrencyPrefix($prefix) {
        $this->currencyPrefix = $prefix;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrencyPrefix() {
        return $this->currencyPrefix;
    }

    /**
     * @param string $suffix
     *
     * @return CApp_Formatter
     */
    public function setCurrencySuffix($suffix) {
        $this->currencySuffix = $suffix;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrencySuffix() {
        return $this->currencySuffix;
    }

    /**
     * @param string $x
     * @param string|null $format
     *
     * @return string
     */
    public function formatDate($x, $format = null) {
        if (strlen($x) == 0) {
            return $x;
        }
        $dateFormat = $format ?: $this->dateFormat;
        if (strlen($dateFormat) == 0) {
            return $x;
        }
        $carbon = CCarbon::parse($x);

        return $carbon->translatedFormat($dateFormat);
    }

    /**
     * @param string $x
     * @param string|null $fromFormat
     *
     * @return string
     */
    public function unformatDate($x, $fromFormat = null) {
        $dateFormat = $fromFormat ?: $this->dateFormat;

        try {
            $date = CCarbon::createFromLocaleFormat($dateFormat, CCarbon::getLocale(), $x);
        } catch (Exception $ex) {
            $date = CCarbon::parse($x);
        }

        return $date->format('Y-m-d');
    }

    /**
     * @param string $x
     * @param string|null $format
     *
     * @return string
     */
    public function formatDatetime($x, $format = null) {
        if (strlen($x) == 0) {
            return $x;
        }
        $datetimeFormat = $format ?: $this->datetimeFormat;
        if (strlen($datetimeFormat) == 0) {
            return $x;
        }

        $carbon = CCarbon::parse($x);

        return $carbon->translatedFormat($datetimeFormat);
    }

    /**
     * @param string $x
     * @param string|null $fromFormat
     *
     * @return string
     */
    public function unformatDatetime($x, $fromFormat = null) {
        if ($x instanceof DateTime) {
            return $x->format('Y-m-d H:i:s');
        }
        $datetimeFormat = $fromFormat ?: $this->datetimeFormat;
        $date = CCarbon::createFromLocaleFormat($datetimeFormat, CCarbon::getLocale(), $x);

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * @param string $x
     * @param int|null $decimalDigit
     * @param string|null $decimalSeparator
     * @param string|null $thousandSeparator
     * @param string|null $currencyPrefix
     * @param string|null $currencySuffix
     * @param bool|null $stripZeroDecimal
     *
     * @return string
     */
    public function formatCurrency($x, $decimalDigit = null, $decimalSeparator = null, $thousandSeparator = null, $currencyPrefix = null, $currencySuffix = null, $stripZeroDecimal = null) {
        $decimalSeparator = $decimalSeparator ?: $this->decimalSeparator;
        $thousandSeparator = $thousandSeparator ?: $this->thousandSeparator;
        $decimalDigit = $decimalDigit !== null ? $decimalDigit : ($this->currencyDecimalDigit !== null ? $this->currencyDecimalDigit : $this->decimalDigit);
        $currencySuffix = $currencySuffix ?: $this->currencySuffix;
        $currencyPrefix = $currencyPrefix ?: $this->currencyPrefix;
        $stripZeroDecimal = $stripZeroDecimal !== null ? $stripZeroDecimal : $this->currencyStripZeroDecimal;

        $x = number_format((float) $x, $decimalDigit, $decimalSeparator, $thousandSeparator);
        if ($stripZeroDecimal) {
            if (substr($x, ($decimalDigit + 1) * -1) === $decimalSeparator . cstr::repeat('0', $decimalDigit)) {
                $x = substr($x, 0, ($decimalDigit + 1) * -1);
            }
            if (strpos($x, $decimalSeparator) !== false) {
                $x = rtrim($x, '0');
            }
        }

        return $currencyPrefix . $x . $currencySuffix;
    }

    /**
     * @param string $x
     * @param string|null $decimalSeparator
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function formatNumber($x, $decimalSeparator = null, $thousandSeparator = null) {
        return $this->formatDecimal($x, 0, $decimalSeparator, $thousandSeparator, true);
    }

    /**
     * @param string $x
     * @param int|null $decimalDigit
     * @param string|null $decimalSeparator
     * @param string|null $thousandSeparator
     * @param bool        $stripZeroDecimal
     *
     * @return string
     */
    public function formatDecimal($x, $decimalDigit = null, $decimalSeparator = null, $thousandSeparator = null, $stripZeroDecimal = false) {
        $decimalDigit = $decimalDigit ?: $this->decimalDigit;

        return $this->formatCurrency($x, $decimalDigit, $decimalSeparator, $thousandSeparator, '', '', $stripZeroDecimal);
    }

    /**
     * @param string      $number
     * @param string|null $decimalSeparator
     * @param string|null $thousandSeparator
     *
     * @return float|int
     */
    public function unformatCurrency($number, $decimalSeparator = null, $thousandSeparator = null) {
        $decimalSeparator = $decimalSeparator ?: $this->decimalSeparator;
        $thousandSeparator = $thousandSeparator ?: $this->thousandSeparator;
        $number = preg_replace('/^[^\d]+/', '', $number);

        $type = (strpos($number, $decimalSeparator) === false) ? 'int' : 'float';
        // strtr mengganti serentak; str_replace berurutan ikut menghapus titik desimal hasil konversi
        // bila pemisah ribuannya titik
        $pairs = [$decimalSeparator => '.'];
        if (strlen($thousandSeparator) > 0) {
            $pairs[$thousandSeparator] = '';
        }
        $number = strtr($number, $pairs);
        settype($number, $type);

        return $number;
    }

    /**
     * @param string      $number
     * @param string|null $decimalSeparator
     * @param string|null $thousandSeparator
     *
     * @return float|int
     */
    public function unformatNumber($number, $decimalSeparator = null, $thousandSeparator = null) {
        return $this->unformatCurrency($number, $decimalSeparator, $thousandSeparator);
    }

    /**
     * @param string      $number
     * @param string|null $decimalSeparator
     * @param string|null $thousandSeparator
     *
     * @return float|int
     */
    public function unformatDecimal($number, $decimalSeparator = null, $thousandSeparator = null) {
        return $this->unformatCurrency($number, $decimalSeparator, $thousandSeparator);
    }

    /**
     * @param int $bytes
     *
     * @return string
     */
    public function formatSize($bytes) {
        $si_prefix = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $base = 1024;
        $class = min((int) log($bytes, $base), count($si_prefix) - 1);
        if (pow($base, $class) == 0) {
            return 0;
        }

        return sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
    }
}
