<?php

defined('SYSPATH') or die('No direct access allowed.');

//@codingStandardsIgnoreStart
/**
 * @deprecated 1.6 use CFile
 */
class cfs {
    /**
     * @param string $dir
     * @param array  $results
     * @param array  $ignore_dir
     *
     * @deprecated use CFile
     *
     * @return array
     */
    public static function list_files_in_dir($dir, &$results = [], $ignore_dir = []) {
        $files = scandir($dir);

        foreach ($files as $key => $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path)) {
                $results[] = $path;
            } elseif ($value != '.' && $value != '..' && !in_array($value, $ignore_dir)) {
                self::list_files_in_dir($path, $results, $ignore_dir);
                $results[] = $path;
            }
        }

        return $results;
    }

    /**
     * @param string $dir
     *
     * @deprecated use CFile
     *
     * @return array
     */
    public static function list_files($dir) {
        $result = [];
        $dir = rtrim($dir, DS) . DS;
        if (is_dir($dir)) {
            if ($handle = opendir($dir)) {
                while (($file = readdir($handle)) !== false) {
                    if ($file == '.' || $file == '..' || $file == 'Thumbs.db') {
                        continue;
                    }
                    if (is_dir($dir . $file)) {
                        continue;
                    }
                    $result[] = $dir . $file;
                }
                closedir($handle);
            }
        }

        return $result;
    }

    /**
     * @param string $dir
     *
     * @deprecated use CFile
     *
     * @return array
     */
    public static function list_dir($dir) {
        $result = [];
        $dir = rtrim($dir, DS) . DS;
        if (is_dir($dir)) {
            if ($handle = opendir($dir)) {
                while (($file = readdir($handle)) !== false) {
                    if ($file == '.' || $file == '..' || $file == 'Thumbs.db') {
                        continue;
                    }
                    if (!is_dir($dir . $file)) {
                        continue;
                    }
                    $result[] = $dir . $file;
                }
                closedir($handle);
            }
        }

        return $result;
    }

    public static function delete_dir($dir, $virtual = false) {
        $ds = DIRECTORY_SEPARATOR;
        $dir = $virtual ? realpath($dir) : $dir;
        $dir = substr($dir, -1) == $ds ? substr($dir, 0, -1) : $dir;
        if (is_dir($dir) && $handle = opendir($dir)) {
            while ($file = readdir($handle)) {
                if ($file == '.' || $file == '..') {
                    continue;
                } elseif (is_dir($dir . $ds . $file)) {
                    self::delete_dir($dir . $ds . $file);
                } else {
                    unlink($dir . $ds . $file);
                }
            }
            closedir($handle);
            @rmdir($dir);

            return true;
        } else {
            return false;
        }
    }

    public static function basename($str) {
        return basename($str);
    }

    /**
     * Creates a folder if missing, or ensures that it is writable.
     * this is a safe function to check dir already exists or will create dir recursively.
     *
     * @param string $dir the directory path
     *
     * @return bool TRUE if folder exists and is writable, otherwise FALSE
     */
    public static function mkdir($dir) {
        // Test write-permissions for the folder and create/fix if necessary.
        if ((is_dir($dir) && is_writable($dir)) || (!is_dir($dir) && @mkdir($dir, 0755, true)) || chmod($dir, 0755)) {
            return true;
        }

        return false;
    }

    public static function is_dir($dir) {
        return is_dir($dir);
    }

    public static function is_file($value) {
        $value = strval(str_replace("\0", '', $value));

        return is_file($value);
    }

    public static function file_exists($value) {
        if (!cfs::is_file($value)) {
            return false;
        }

        return file_exists($value);
    }

    public static function mtime($file) {
        return filemtime($file);
    }

    public static function mtime_diff($file, $time = null) {
        if ($time == null) {
            return time() - cfs::mtime($file);
        }
        if (is_string($time)) {
            $time = strtotime($time);
        }

        return $time - cfs::mtime($file);
    }

    /**
     * Atomic filewriter.
     *
     * Safely writes new contents to a file using an atomic two-step process.
     * If the script is killed before the write is complete, only the temporary
     * trash file will be corrupted.
     *
     * @param string $filename      filename to write the data to
     * @param string $data          data to write to file
     * @param string $atomicSuffix  lets you optionally provide a different
     *                              suffix for the temporary file
     * @param mixed  $atomic_suffix
     *
     * @return mixed number of bytes written on success, otherwise FALSE
     */
    public static function atomic_write($filename, $data, $atomic_suffix = 'atomictmp') {
        // Perform an exclusive (locked) overwrite to a temporary file.
        $filenameTmp = sprintf('%s.%s', $filename, $atomic_suffix);
        $writeResult = @file_put_contents($filenameTmp, $data, LOCK_EX);
        if ($writeResult !== false) {
            // Now move the file to its real destination (replaced if exists).
            $moveResult = @rename($filenameTmp, $filename);
            if ($moveResult === true) {
                // Successful write and move. Return number of bytes written.
                return $writeResult;
            }
        }

        return false; // Failed.
    }
}
