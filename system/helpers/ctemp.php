<?php

/**
 * Helper ctemp.
 *
 * @deprecated 1.2 use CTemporary
 */
// @codingStandardsIgnoreStart
class ctemp {
    public static function get_directory() {
        CF::deprecated(__METHOD__, 'CTemporary', '1.2', null, true);
        return CTemporary::getDirectory();
    }

    public static function makedir($path) {
        CF::deprecated(__METHOD__, 'CTemporary', '1.2', null, true);
        return CTemporary::makeDir($path);
    }

    public static function makefolder($path, $folder) {
        CF::deprecated(__METHOD__, 'CTemporary', '1.2', null, true);
        return CTemporary::makeFolder($path, $folder);
    }

    public static function makepath($folder, $filename) {
        CF::deprecated(__METHOD__, 'CTemporary', '1.2', null, true);
        return CTemporary::makePath($folder, $filename);
    }

    public static function get_url($folder, $filename) {
        CF::deprecated(__METHOD__, 'CTemporary', '1.2', null, true);
        return CTemporary::getUrl($folder, $filename);
    }
}
// @codingStandardsIgnoreEnd
