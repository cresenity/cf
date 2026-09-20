<?php

defined('SYSPATH') or die('No direct access allowed.');

class CManager_Asset_Helper {
    /**
     * Identitas rilis yang sudah diselesaikan, false selama belum diselesaikan.
     *
     * @var null|string|bool
     */
    protected static $releaseVersion = false;

    /**
     * Cache per-request hash isi berkas, kunci path|mtime|size.
     *
     * @var array
     */
    protected static $contentVersions = [];

    /**
     * @param string $file
     * @param bool   $withHttp
     *
     * @return string
     */
    public static function urlCssFile($file, $withHttp = false) {
        //return CResource::instance('css')->url($file);
        $docroot = str_replace(DS, '/', DOCROOT);
        $file = str_replace(DS, '/', $file);
        $path = carr::first(explode('?', $file));

        $base_url = curl::base();
        if ($withHttp) {
            $base_url = curl::base(false, 'http');
        }
        $file = str_replace($docroot, $base_url, $file);

        if (CF::config('assets.css.versioning')) {
            $separator = parse_url($file, PHP_URL_QUERY) ? '&' : '?';
            $interval = CF::config('assets.css.interval', 0);
            $version = static::getFileVersion($path, $interval);
            $file .= $separator . 'v=' . $version;
        }

        return $file;
    }

    /**
     * @param CManager_Asset_File_JsFile|string $file
     * @param bool                              $withHttp
     *
     * @return string
     */
    public static function urlJsFile($file, $withHttp = false) {
        if ($file instanceof CManager_Asset_File_JsFile) {
            return $file->getUrl();
        }
        $path = $file;
        $path = carr::first(explode('?', $file));
        $docroot = str_replace(DS, '/', DOCROOT);
        $file = str_replace(DS, '/', $file);
        $base_url = curl::base();
        if ($withHttp) {
            $base_url = curl::base(false, 'http');
        }

        $file = str_replace($docroot, $base_url, $file);

        if (CF::config('assets.js.versioning')) {
            $separator = parse_url($file, PHP_URL_QUERY) ? '&' : '?';
            $interval = CF::config('assets.js.interval', 0);
            $version = static::getFileVersion($path, $interval);
            $file .= $separator . 'v=' . $version;
        }

        return $file;
    }

    /**
     * Versi untuk sebuah berkas: identitas rilis bila ada, kalau tidak hash isinya.
     *
     * $interval (pembulatan mtime ke kelipatan menit) tidak lagi berpengaruh: versi berbasis isi
     * sudah identik di semua server untuk isi yang sama, jadi tidak perlu dibulatkan.
     *
     * @param string $file
     * @param int    $interval
     *
     * @return string
     */
    public static function getFileVersion($file, $interval = 0) {
        $release = static::getReleaseVersion();
        if ($release !== null) {
            return $release;
        }

        return static::getContentVersion($file);
    }

    /**
     * Versi untuk sebuah berkas, memakai identitas rilis bila tersedia.
     *
     * @param string $file
     *
     * @return string
     */
    public static function getVersionForFile($file) {
        $release = static::getReleaseVersion();
        if ($release !== null) {
            return $release;
        }

        return static::getContentVersion($file);
    }

    /**
     * 12 hex pertama md5 isi berkas — sama di setiap server untuk isi yang sama, tidak seperti
     * mtime yang berbeda antar node bila deploy tidak serempak. Hash disimpan di cache dengan
     * kunci path+mtime+size, jadi biaya steady-state hanya satu stat() per berkas.
     *
     * @param string $file
     *
     * @return string
     */
    public static function getContentVersion($file) {
        $stat = @stat($file);
        if ($stat === false) {
            return '0';
        }
        $key = $file . '|' . $stat['mtime'] . '|' . $stat['size'];
        if (isset(static::$contentVersions[$key])) {
            return static::$contentVersions[$key];
        }
        $cacheKey = 'cf-asset-version:' . md5($key);
        $version = null;
        try {
            $version = c::cache()->get($cacheKey);
        } catch (Throwable $e) {
            $version = null;
        }
        if (!is_string($version) || strlen($version) != 12) {
            $version = substr((string) md5_file($file), 0, 12);
            try {
                c::cache()->put($cacheKey, $version, 60 * 60 * 24 * 30);
            } catch (Throwable $e) {
                // tanpa cache persisten pun tetap benar, cuma menghitung ulang per request
            }
        }
        static::$contentVersions[$key] = $version;

        return $version;
    }

    /**
     * Identitas rilis yang sama di seluruh server, dari config assets.release.
     *
     * @return null|string
     */
    public static function getReleaseVersion() {
        if (static::$releaseVersion !== false) {
            return static::$releaseVersion;
        }
        $release = CF::config('assets.release');
        if ($release === 'git') {
            $release = static::resolveGitRelease();
        }
        $release = (string) $release;
        static::$releaseVersion = strlen($release) > 0 ? $release : null;

        return static::$releaseVersion;
    }

    /**
     * Buang identitas rilis yang sudah diselesaikan.
     *
     * @return void
     */
    public static function flushReleaseVersion() {
        static::$releaseVersion = false;
    }

    /**
     * Gabungan revisi git framework dan aplikasi, supaya deploy salah satunya ikut terbaca.
     *
     * @return null|string
     */
    protected static function resolveGitRelease() {
        $revisions = [];
        $paths = [DOCROOT, DOCROOT . 'application' . DS . CF::appCode() . DS];
        foreach ($paths as $path) {
            $revision = static::gitRevision($path);
            if ($revision !== null) {
                $revisions[] = $revision;
            }
        }
        if (count($revisions) == 0) {
            return null;
        }

        return substr(md5(implode('-', $revisions)), 0, 12);
    }

    /**
     * Baca revisi git dari berkas, tanpa memanggil biner git.
     *
     * @param string $basePath
     *
     * @return null|string
     */
    public static function gitRevision($basePath) {
        $gitPath = $basePath . '.git' . DS;
        if (!is_file($gitPath . 'HEAD')) {
            return null;
        }
        $head = trim((string) @file_get_contents($gitPath . 'HEAD'));
        if (strlen($head) == 0) {
            return null;
        }
        if (strpos($head, 'ref:') !== 0) {
            return $head;
        }
        $ref = trim(substr($head, 4));
        $refFile = $gitPath . str_replace('/', DS, $ref);
        if (is_file($refFile)) {
            $revision = trim((string) @file_get_contents($refFile));

            return strlen($revision) > 0 ? $revision : null;
        }
        if (!is_file($gitPath . 'packed-refs')) {
            return null;
        }
        foreach (explode("\n", (string) @file_get_contents($gitPath . 'packed-refs')) as $line) {
            $line = trim($line);
            if (strlen($line) == 0 || $line[0] == '#' || $line[0] == '^') {
                continue;
            }
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 2 && $parts[1] === $ref) {
                return $parts[0];
            }
        }

        return null;
    }

    /**
     * @param string $file
     * @param array  $mediaPaths
     *
     * @return string
     */
    public static function fullpathCssFile($file, $mediaPaths = []) {
        foreach ($mediaPaths as $dir) {
            $path = $dir . 'css' . DS . $file;

            if (file_exists($path)) {
                return $path;
            }
        }
        $dirs = CF::getDirs('media');
        $dirs = array_merge($mediaPaths, $dirs);

        foreach ($dirs as $dir) {
            $path = $dir . 'css' . DS . $file;

            if (file_exists($path)) {
                return $path;
            }
        }
        $path = DOCROOT . 'media' . DS . 'css' . DS;

        return $path . $file;
    }
}
