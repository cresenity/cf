<?php

defined('SYSPATH') or die('No direct access allowed.');

class CTemporary {
    use CTrait_Compat_Temporary;

    /**
     * @param null|mixed $diskName
     *
     * @return CStorage_Adapter
     */
    public static function disk($diskName = null) {
        return CStorage::instance()->temp($diskName);
    }

    /**
     * @param null|mixed $diskName
     *
     * @return CStorage_Adapter
     */
    public static function publicDisk($diskName = null) {
        return CStorage::instance()->publicTemp($diskName);
    }

    public static function defaultDiskDriver() {
        $defaultDiskName = static::defaultDiskName();
        $config = CF::config('storage.disks.' . $defaultDiskName);

        return carr::get($config, 'driver');
    }

    public static function defaultDiskName() {
        return CF::config('storage.temp');
    }

    /**
     * @param string $path
     *
     * @return \CTemporary_Directory
     */
    public static function createDirectory($path) {
        return new CTemporary_Directory($path);
    }

    /**
     * @param string $filename
     *
     * @return \CTemporary_File
     */
    public static function createFile($filename) {
        return new CTemporary_File(self::createDirectory(dirname($filename)), basename($filename));
    }

    /**
     * @param null|mixed $folder
     *
     * @return string
     */
    public static function getDirectory($folder = null) {
        $path = DOCROOT . 'temp' . DIRECTORY_SEPARATOR;

        if ($folder != null) {
            $path .= static::appFolder($folder) . DIRECTORY_SEPARATOR;
        }

        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }

        return $path;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public static function makeDir($path) {
        if (!is_dir($path)) {
            mkdir($path);
        }

        return $path;
    }

    /**
     * @param string $path
     * @param string $folder
     *
     * @return string
     */
    public static function makeFolder($path, $folder) {
        $path = $path . $folder . DIRECTORY_SEPARATOR;
        self::makeDir($path);

        return $path;
    }

    /**
     * Local path of a temp file with its directory created; an old shared-location file is still found.
     *
     * @param string $folder
     * @param string $filename
     *
     * @return string
     */
    public static function makePath($folder, $filename) {
        $path = static::getLocalPath($folder, $filename);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        return $path;
    }

    /**
     * Per-app temp folder, `<folder>/<appCode>` (`common` without a running app).
     *
     * @param null|string $folder
     *
     * @return string
     */
    public static function appFolder($folder = null) {
        $folder = rtrim($folder ?: 'common', DIRECTORY_SEPARATOR . '/');
        $appCode = (string) CF::appCode();
        $appCode = strlen($appCode) > 0 ? $appCode : 'common';
        if (cstr::endsWith($folder, DIRECTORY_SEPARATOR . $appCode) || cstr::endsWith($folder, '/' . $appCode)) {
            return $folder;
        }

        return $folder . DIRECTORY_SEPARATOR . $appCode;
    }

    /**
     * Relative temp path: new files go under `<folder>/<appCode>/`, a file that only exists in the
     * old shared `<folder>/` is still resolved there.
     *
     * @param null|string $folder
     * @param null|string $filename
     *
     * @return string
     */
    public static function getPath($folder = null, $filename = null) {
        if ($folder == null) {
            $folder = 'common';
        }
        if ($filename == null) {
            $filename = date('Ymd') . cutils::randmd5();
        }
        $perApp = static::buildPath(static::appFolder($folder), $filename);
        $legacy = static::buildPath($folder, $filename);
        if ($legacy === $perApp) {
            return $perApp;
        }
        $disk = static::disk();
        if (!$disk->exists($perApp) && $disk->exists($legacy)) {
            return $legacy;
        }

        return $perApp;
    }

    /**
     * `<folder>/<Ymd>/<c>/<c>/<c>/<c>/<c>/<filename>`, the five characters after the date spreading files.
     *
     * @param string $folder
     * @param string $filename
     *
     * @return string
     */
    protected static function buildPath($folder, $filename) {
        $depth = 5;
        $mainFolder = substr($filename, 0, 8);
        $path = '';
        $path = $path . rtrim($folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $path = $path . $mainFolder . DIRECTORY_SEPARATOR;

        $basefile = basename($filename);
        for ($i = 0; $i < $depth; $i++) {
            $c = '_';
            if (strlen($basefile) > ($i + 1)) {
                $c = substr($basefile, $i + 8, 1);
                if (strlen($c) == 0) {
                    $c = '_';
                }
                $path = $path = $path . $c . DIRECTORY_SEPARATOR;
            }
        }

        return $path . $filename;
    }

    /**
     * Absolute path under DOCROOT/temp; like getPath(), an old shared-location file is still found.
     *
     * @param string      $folder
     * @param null|string $filename
     *
     * @return string
     */
    public static function getLocalPath($folder, $filename = null) {
        $root = rtrim(DOCROOT, '/') . '/temp/';
        if ($filename == null) {
            $filename = date('Ymd') . cutils::randmd5();
        }
        $perApp = $root . static::buildPath(static::appFolder($folder ?: 'common'), $filename);
        $legacy = $root . static::buildPath($folder ?: 'common', $filename);
        if ($legacy !== $perApp && !file_exists($perApp) && file_exists($legacy)) {
            return $legacy;
        }

        return $perApp;
    }

    /**
     * @param string $folder
     * @param string $filename
     *
     * @return string
     */
    public static function getUrl($folder, $filename) {
        $path = static::getPath($folder, $filename);

        return static::disk()->url($path);
    }

    public static function getPublicUrl($folder, $filename) {
        $path = static::getPath($folder, $filename);

        return static::publicDisk()->url($path);
    }

    /**
     * @param string $folder
     * @param string $filename
     *
     * @return bool
     */
    public static function delete($folder, $filename) {
        $disk = static::disk();

        return $disk->delete(static::getPath($folder, $filename));
    }

    /**
     * @param string $folder
     * @param string $filename
     *
     * @return bool
     */
    public static function deleteLocal($folder, $filename) {
        $path = static::getLocalPath($folder, $filename);

        return @unlink($path);
    }

    public static function generateRandomFilename($extension = null) {
        return date('Ymd') . cutils::randmd5() . (strlen($extension) > 0 ? $extension : '');
    }

    public static function put($folder, $content, $filename = null) {
        if ($filename == null) {
            $filename = static::generateRandomFilename();
        }
        $path = static::getPath($folder, $filename);
        static::disk()->put($path, $content);

        return $path;
    }

    public static function publicPut($folder, $content, $filename = null) {
        if ($filename == null) {
            $filename = static::generateRandomFilename();
        }
        $path = static::getPath($folder, $filename);
        static::publicDisk()->put($path, $content);

        return $path;
    }

    public static function get($folder, $filename) {
        $path = static::getPath($folder, $filename);

        return static::disk()->get($path);
    }

    public static function getSize($folder, $filename) {
        $path = static::getPath($folder, $filename);

        return static::disk()->size($path);
    }

    public static function isExists($folder, $filename) {
        $path = static::getPath($folder, $filename);

        return static::disk()->exists($path);
    }

    /**
     * @return CTemporary_Instance
     */
    public static function local() {
        return CTemporary::instance('local-temp');
    }

    public static function createLocalFile($content, $folder = null, $suffix = null, $delete = true) {
        return new CTemporary_LocalFile($content, $folder, $suffix, $delete);
    }

    /**
     * @param null|mixed $disk
     *
     * @return CTemporary_Instance
     */
    public static function instance($disk = null) {
        return CTemporary_Instance::instance($disk);
    }

    public static function __callStatic($name, $arguments) {
        return CTemporary::instance()->$name(...$arguments);
    }

    /**
     * @param string $location
     *
     * @return CTemporary_CustomDirectory
     */
    public static function customDirectory($location = '') {
        return new CTemporary_CustomDirectory($location);
    }
}
