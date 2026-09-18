<?php

defined('SYSPATH') or die('No direct access allowed.');

class CPeriod_Exception_InvalidDateException extends InvalidArgumentException {
    /**
     * @param string $parameter
     *
     * @return CPeriod_Exception_InvalidDateException
     */
    public static function cannotBeNull($parameter) {
        return new static("{$parameter} cannot be null");
    }

    /**
     * @param string      $date
     * @param null|string $format
     *
     * @return CPeriod_Exception_InvalidDateException
     */
    public static function forFormat($date, $format) {
        $message = "Could not construct a date from `{$date}`";

        if ($format) {
            $message .= " with format `{$format}`";
        }

        return new static($message);
    }
}
