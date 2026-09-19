<?php
class CPeriod_Exception_InvalidDateTimeClass extends CPeriod_Exception {
    public static function forString($string) {
        return new self("The string `{$string}` isn't a valid class implementing DateTimeInterface.");
    }
}
