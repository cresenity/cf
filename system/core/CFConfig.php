<?php

use Symfony\Component\Finder\Finder;

class CFConfig {
    /**
     * Shape of the cached payload, bumped when that shape changes.
     *
     * @var int
     */
    const CACHE_VERSION = 1;

    /**
     * Ceiling on cache files per app, since the key carries the request's
     * domain and a forged Host would otherwise mint one file after another.
     *
     * @var int
     */
    const CACHE_VARIANT_LIMIT = 64;

    public static function bootstrap() {
        $repository = self::bootstrapRepository();

        $timezone = $repository->get('app.timezone');

        // Set default timezone, due to increased validation of date settings
        // which cause massive amounts of E_NOTICEs to be generated in PHP 5.2+
        date_default_timezone_set(empty($timezone) ? date_default_timezone_get() : $timezone);

        // Load locales
        $locale = $repository->get('app.locale');
        $fallbackLocale = $repository->get('app.fallback_locale');

        CF::setLocale($locale);
        CF::setFallbackLocale($fallbackLocale);

        mb_internal_encoding('UTF-8');
    }

    /**
     * Build the configuration repository, reusing the cache while it is current.
     *
     * @return CConfig_Repository
     */
    protected static function bootstrapRepository() {
        $cache = self::readCache();
        if ($cache !== null) {
            $repository = CConfig::manager()->newRepository($cache['items']);
            foreach ($cache['dynamic'] as $configKey) {
                if (isset($cache['files'][$configKey])) {
                    self::loadConfigurationFiles($configKey, $cache['files'][$configKey], $repository);
                }
            }

            return $repository;
        }

        $repository = CConfig::manager()->repository();
        $files = self::getConfigurationFiles();
        foreach ($files as $configKey => $configFiles) {
            self::loadConfigurationFiles($configKey, $configFiles, $repository);
        }
        self::writeCache($files, $repository);

        return $repository;
    }

    public static function loadConfiguration(CConfig_Repository $repository) {
        $allFiles = self::getConfigurationFiles();
        foreach ($allFiles as $configKey => $files) {
            self::loadConfigurationFiles($configKey, $files, $repository);
        }
    }

    /**
     * Load the configuration files for a given config key.
     *
     * @param string $configKey
     *
     * @return void
     */
    public static function loadConfigurationFiles($configKey, array $files, CConfig_Repository $repository) {
        $configs = [];
        foreach ($files as $path) {
            $config = require $path;
            if (!is_array($config)) {
                throw new Exception(c::__('Invalid config format in :file', ['file' => str_replace(DOCROOT, '', $path)]));
            } else {
                $configs = array_merge($configs, $config);
            }
        }
        $repository->set($configKey, $configs);
    }

    public static function getConfigurationFiles() {
        $files = [];
        $paths = array_reverse(CF::paths());
        foreach ($paths as $path) {
            $configPath = $path . 'config' . DS;
            if (is_dir($configPath)) {
                foreach (Finder::create()->files()->name('*.php')->in($configPath) as $file) {
                    $directory = self::getNestedDirectory($file, $configPath);
                    $configKey = basename($file->getRealPath(), '.php');
                    if (!isset($files[$configKey])) {
                        $files[$configKey] = [];
                    }
                    $files[$configKey][$directory . basename($file->getRealPath(), '.php')] = $file->getRealPath();
                }
            }
        }

        return $files;
    }

    /**
     * @param null|string $variant discriminator for one configuration composition
     *
     * @return null|string
     */
    public static function getCachedConfigPath($variant = null) {
        $appCode = CF::appCode();
        if ($appCode) {
            $suffix = ($variant === null || $variant === '') ? '' : '-' . $variant;

            return DOCROOT . 'temp/cache/' . CF::appCode() . '/config' . $suffix . '.php';
        }

        return null;
    }

    /**
     * Cache file for the composition this request resolves to. Keyed by domain
     * as well as paths, because a config value may hold the domain itself.
     *
     * @return null|string
     */
    protected static function cachePath() {
        if (CF::isTesting()) {
            return null;
        }

        $composition = CF::domain() . '|' . implode('|', CF::paths());

        return self::getCachedConfigPath(substr(md5($composition), 0, 12));
    }

    /**
     * Cached payload, or null when absent, unusable or no longer current.
     *
     * @return null|array
     */
    protected static function readCache() {
        $path = self::cachePath();
        if ($path === null || !is_file($path)) {
            return null;
        }

        $cache = require $path;
        if (!is_array($cache)
            || !isset($cache['version'], $cache['fingerprint'], $cache['watch'], $cache['files'], $cache['dynamic'], $cache['items'])
            || $cache['version'] !== self::CACHE_VERSION
        ) {
            return null;
        }
        if ($cache['fingerprint'] !== self::fingerprint($cache['watch'])) {
            return null;
        }

        return $cache;
    }

    /**
     * @return void
     */
    protected static function writeCache(array $files, CConfig_Repository $repository) {
        $path = self::cachePath();
        if ($path === null || !self::mayAddVariant($path)) {
            return;
        }

        try {
            $second = new CConfig_Repository([]);
            foreach ($files as $configKey => $configFiles) {
                self::loadConfigurationFiles($configKey, $configFiles, $second);
            }

            list($items, $dynamic) = self::partition($repository->all(), $second->all());

            $watch = self::watchList($files);
            self::putCache($path, [
                'version' => self::CACHE_VERSION,
                'fingerprint' => self::fingerprint($watch),
                'watch' => $watch,
                'files' => $files,
                'dynamic' => $dynamic,
                'items' => $items,
            ]);
        } catch (Exception $ex) {
            // A request that would have worked must still work without the cache.
        }
    }

    /**
     * Whether a cache file this app does not have yet may still be added.
     * Rebuilding one that already exists is always allowed.
     *
     * @param string $path
     *
     * @return bool
     */
    protected static function mayAddVariant($path) {
        if (is_file($path)) {
            return true;
        }

        $existing = glob(dirname($path) . DS . 'config-*.php');

        return $existing === false || count($existing) < self::CACHE_VARIANT_LIMIT;
    }

    /**
     * Paths whose modification time decides whether the cache is still current:
     * every config file, every directory holding one, and the app's env file.
     *
     * @return array
     */
    protected static function watchList(array $files) {
        $watch = [];
        foreach (CF::paths() as $path) {
            $watch[$path . 'config' . DS] = true;
        }
        foreach ($files as $configFiles) {
            foreach ($configFiles as $file) {
                $watch[$file] = true;
                $watch[dirname($file) . DS] = true;
            }
        }
        $appRoot = c::appRoot();
        if ($appRoot !== null) {
            $watch[$appRoot . 'env.php'] = true;
        }

        $watch = array_keys($watch);
        sort($watch);

        return $watch;
    }

    /**
     * @return string
     */
    protected static function fingerprint(array $watch) {
        $parts = [];
        foreach ($watch as $path) {
            $parts[] = $path . '|' . (string) @filemtime($path);
        }

        return md5(implode(';', $parts));
    }

    /**
     * Split configuration into what may be cached and what must be read from
     * file on every request.
     *
     * A key is excluded when var_export() cannot round-trip it, and when two
     * consecutive loads disagree - the latter is how a value built from
     * uniqid() or the clock gives itself away, since freezing one would turn a
     * deliberately per-request value into a constant.
     *
     * @return array [cacheable items, keys to reload]
     */
    protected static function partition(array $first, array $second) {
        $dynamic = [];
        foreach ($first as $configKey => $value) {
            if (!self::isExportable($value)) {
                $dynamic[] = $configKey;

                continue;
            }
            if (!array_key_exists($configKey, $second)
                || !self::isExportable($second[$configKey])
                || var_export($value, true) !== var_export($second[$configKey], true)
            ) {
                $dynamic[] = $configKey;
            }
        }
        foreach ($dynamic as $configKey) {
            unset($first[$configKey]);
        }

        return [$first, $dynamic];
    }

    /**
     * Whether var_export() can round-trip this value; a closure or object cannot.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected static function isExportable($value) {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!self::isExportable($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
    }

    /**
     * Write through a temporary file and rename, so no request ever reads a
     * half-written cache.
     *
     * @param string $path
     *
     * @return void
     */
    protected static function putCache($path, array $payload) {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $temporary = $path . '.' . getmypid() . '.tmp';
        $content = '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($payload, true) . ';' . PHP_EOL;
        if (@file_put_contents($temporary, $content) === false) {
            return;
        }
        if (!@rename($temporary, $path)) {
            @unlink($temporary);

            return;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    /**
     * Get the configuration file nesting path.
     *
     * @param string $configPath
     *
     * @return string
     */
    protected static function getNestedDirectory(SplFileInfo $file, $configPath) {
        $directory = $file->getPath();

        if ($nested = trim(str_replace($configPath, '', $directory), DIRECTORY_SEPARATOR)) {
            $nested = str_replace(DIRECTORY_SEPARATOR, '.', $nested) . '.';
        }

        return $nested;
    }
}
