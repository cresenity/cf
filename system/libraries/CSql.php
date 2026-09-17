<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.6, use CParser_Sql
 */
class CSql {
    public static function format($query) {
        return CParser_Sql::format($query);
    }

    public static function highlight($query) {
        return CParser_Sql::highlight($query);
    }
}
