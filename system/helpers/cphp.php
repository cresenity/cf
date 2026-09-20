<?php

//@codingStandardsIgnoreStart
/**
 * @deprecated since 1.2, see CFile
 * @see CFile
 */
class cphp {
    /**
     * @param mixed $val
     * @param int   $level
     *
     * @return string
     *
     * @deprecated since 1.2, see CFile::phpValue
     */
    public static function string_value($val, $level = 0) {
        CF::deprecated(__METHOD__, 'CFile::phpValue', '1.2', null, true);
        return CFile::phpValue($val, $level);
    }

    /**
     * @param mixed  $value
     * @param string $filename
     *
     * @return int|bool
     *
     * @deprecated since 1.2, see CFile::putPhpValue
     */
    public static function save_value($value, $filename = null) {
        CF::deprecated(__METHOD__, 'CFile::putPhpValue', '1.2', null, true);
        return CFile::putPhpValue($filename, $value);
    }

    /**
     * @param string $filename
     *
     * @return mixed
     *
     * @deprecated since 1.2, see CFile::getRequire
     */
    public static function load_value($filename) {
        CF::deprecated(__METHOD__, 'CFile::getRequire', '1.2', null, true);
        return CFile::getRequire($filename);
    }
}
//@codingStandardsIgnoreEnd
