<?php

/**
 * Bundel compiled (compiled/asset/<type>/<rilis>/<md5>.<type>) hanya ditulis oleh server yang
 * me-render halamannya. Di belakang load balancer, permintaan bundel bisa jatuh ke server lain
 * yang belum punya berkasnya (404 HTML, jQuery dkk. tidak termuat). Dengan
 * `assets.bundle_manifest_redis` diisi nama koneksi Redis bersama, daftar berkas tiap bundel
 * disimpan saat disusun, dan server yang tidak punya bundelnya menyusunnya sendiri saat diminta.
 * Tanpa config itu tidak ada yang berubah.
 */
class CManager_Asset_BundleManifest {
    const KEY_PREFIX = 'cf:asset-bundle:';

    const TTL = 2592000;

    const PATH_PATTERN = '#^compiled/asset/(js|css)/[A-Za-z0-9_.-]+/[a-f0-9]{32}\.(js|css)$#';

    /**
     * Penyimpanan pengganti untuk test: objek dengan get($key) dan set($key, $value, $ttl).
     *
     * @var null|object
     */
    public static $store = null;

    /**
     * @return null|object
     */
    protected static function store() {
        if (static::$store !== null) {
            return static::$store;
        }
        $connectionName = CF::config('assets.bundle_manifest_redis');
        if (!$connectionName) {
            return null;
        }

        return new CManager_Asset_BundleManifest_RedisStore(CRedis::instance()->connection($connectionName));
    }

    /**
     * Daftarkan route compiled/asset/... (konteks CApp saja, bukan API) bila config diisi.
     *
     * @return void
     */
    public static function registerRoute() {
        if (!CF::config('assets.bundle_manifest_redis')) {
            return;
        }
        c::router()->get('compiled/asset/{type}/{release}/{file}', function () {
            $response = static::respond(c::request());
            if ($response === null) {
                c::abort(404);
            }

            return $response;
        })->where(['type' => 'js|css', 'release' => '[A-Za-z0-9_.-]+', 'file' => '[a-f0-9]{32}\\.(js|css)']);
    }

    /**
     * Catat daftar berkas sebuah bundel; kegagalan tidak pernah mengganggu render.
     *
     * @param string   $outFile berkas bundel (absolut)
     * @param string[] $files   berkas sumber (absolut)
     *
     * @return bool
     */
    public static function remember($outFile, array $files) {
        try {
            $store = static::store();
            $relative = static::relativeToDocroot($outFile);
            if ($store === null || $relative === null || !preg_match(static::PATH_PATTERN, $relative)) {
                return false;
            }
            $sources = [];
            foreach ($files as $file) {
                $source = static::relativeToDocroot($file);
                if ($source === null) {
                    return false;
                }
                $sources[] = $source;
            }
            $store->set(static::KEY_PREFIX . $relative, json_encode($sources), static::TTL);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Respons untuk permintaan bundel yang berkasnya belum ada di server ini, atau null bila
     * bukan permintaan bundel / manifest tidak ada (alur normal: 404).
     *
     * @return null|CHTTP_Response
     */
    public static function respond(CHTTP_Request $request) {
        $path = ltrim((string) $request->path(), '/');
        if (strpos($path, 'compiled/asset/') !== 0 || !preg_match(static::PATH_PATTERN, $path, $match) || $match[1] !== $match[2]) {
            return null;
        }

        try {
            $outFile = DOCROOT . $path;
            if (!is_file($outFile)) {
                $files = static::sourcesFor($path, $match[1]);
                if ($files === null) {
                    return null;
                }
                (new CManager_Asset_Compiler($files, ['type' => $match[1], 'outFile' => $outFile]))->compile();
            }
            if (!is_file($outFile)) {
                return null;
            }

            return new CHTTP_Response(file_get_contents($outFile), 200, [
                'Content-Type' => $match[1] === 'js' ? 'text/javascript' : 'text/css',
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Berkas sumber dari manifest, hanya bila semuanya berkas bertipe sama di bawah docroot.
     *
     * @param string $path
     * @param string $type
     *
     * @return null|string[]
     */
    protected static function sourcesFor($path, $type) {
        $store = static::store();
        if ($store === null) {
            return null;
        }
        $sources = json_decode((string) $store->get(static::KEY_PREFIX . $path), true);
        if (!is_array($sources) || count($sources) === 0) {
            return null;
        }
        $docroot = rtrim(realpath(DOCROOT), DS) . DS;
        $files = [];
        foreach ($sources as $source) {
            $real = is_string($source) ? realpath(DOCROOT . $source) : false;
            if ($real === false || strpos($real, $docroot) !== 0 || strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== $type) {
                return null;
            }
            $files[] = DOCROOT . $source;
        }

        return $files;
    }

    /**
     * @param string $file
     *
     * @return null|string
     */
    protected static function relativeToDocroot($file) {
        $file = (string) $file;
        if (strpos($file, DOCROOT) !== 0) {
            return null;
        }
        $relative = substr($file, strlen(DOCROOT));

        return strpos($relative, '..') === false ? $relative : null;
    }
}
