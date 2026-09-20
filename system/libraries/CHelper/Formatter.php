<?php

defined('SYSPATH') or die('No direct access allowed.');

class CHelper_Formatter {
    public static function formatDatetime($time, $format = null) {
        if (strlen($time) == 0) {
            return $time;
        }
        if ($format == null) {
            $format = CApp_Config::get('long_date_formatted');
        }
        if (strlen($format) == 0) {
            $format = 'Y-m-d H:i:s';
        }

        if (!is_double($time)) {
            $time = strtotime($time);
        }

        return date($format, $time);
    }

    public static function formatTime($seconds, $format = '%a days, %h hours, %i minutes and %s seconds') {
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$seconds");

        return $dtF->diff($dtT)->format($format);
    }

    public static function formatSize($bytes) {
        if ($bytes <= 0) {
            return '0 B';
        }
        $si_prefix = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $base = 1024;
        $class = min((int) log($bytes, $base), count($si_prefix) - 1);

        return sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
    }

    public static function formatNumber($number) {
        return ctransform::thousand_separator($number);
    }

    public static function formatCurrency($number) {
        return ctransform::thousand_separator($number, 2);
    }
}
