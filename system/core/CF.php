<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * CF Class.
 * This class is core of Cresenity Framework loaded in Bootstrap.php.
 */
final class CF {
    use CFDeprecatedTrait;

    const CFCLI_CURRENT_DOMAIN_FILE = DOCROOT . 'data' . DS . 'current-domain';

    const CFCLI_CURRENT_APPCODE_FILE = DOCROOT . 'data' . DS . 'current-app';

    // Security check that is added to all generated PHP files
    const FILE_SECURITY = '<?php defined(\'SYSPATH\') OR die(\'No direct script access.\');';

    /**
     * Chartset used for this application.
     *
     * @var string
     */
    public static $charset = 'utf-8';

    /**
     * Logger Instance.
     *
     * @var CLogger_Manager logging object
     */
    public static $logger;

    /**
     * The current locale.
     *
     * @var string
     */
    private static $locale;

    /**
     * The fallback locale.
     *
     * @var string
     */
    private static $fallbackLocale;

    /**
     *  Internal caches for faster loading.
     *
     * @var array
     */
    private static $internalCache = [];

    /**
     * CF Data domain.
     *
     * @var array
     */
    private static $data;

    /**
     * List of Shared appCode used for CF.
     *
     * @var array
     */
    private static $sharedAppCode = [];

    /**
     * Shared app code declared by each application, keyed by app code.
     *
     * @var array
     */
    private static $declaredSharedAppCode = [];

    /**
     * Force appCode for CF.
     *
     * @var string
     */
    private static $forceAppCode = null;

    /**
     * The array of terminating callbacks.
     *
     * @var callable[]
     */
    private static $terminatingCallbacks = [];

    /**
     * The array of booting callbacks.
     *
     * @var callable[]
     */
    private static $bootingCallbacks = [];

    /**
     * The array of booted callbacks.
     *
     * @var callable[]
     */
    private static $bootedCallbacks = [];

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * Check CF is running on production.
     *
     * @return bool
     */
    public static function isProduction() {
        return static::environment() === CBase::ENVIRONMENT_PRODUCTION;
    }

    public static function getEnvironment() {
        if (defined('IN_PRODUCTION') && IN_PRODUCTION) {
            return CBase::ENVIRONMENT_PRODUCTION;
        }

        return c::env('ENVIRONMENT', CBase::ENVIRONMENT_DEVELOPMENT);
    }

    /**
     * Check CF is running on development.
     *
     * @param string|string[] ...$environments
     *
     * @return bool|string
     */
    public static function environment(...$environments) {
        if (count($environments) > 0) {
            $patterns = is_array($environments[0]) ? $environments[0] : $environments;

            return cstr::is($patterns, self::getEnvironment());
        }

        return self::getEnvironment();
    }

    /**
     * Check given domain exists or not.
     *
     * @param string $domain domain to check
     *
     * @return bool
     */
    public static function domainExists($domain) {
        return CFData::domain($domain) !== null;
    }

    /**
     * Create domain.
     *
     * @param string $domain     asd
     * @param array  $domainData asd
     *
     * @return bool
     */
    public static function createDomain($domain, array $domainData) {
        if (!static::domainExists($domain)) {
            CFData::set($domain, $domainData, 'domain');

            return true;
        }

        return false;
    }

    /**
     * Sets up the PHP environment. Adds error/exception handling, output
     * buffering, and adds an auto-loading method for loading classes.
     *
     * This method is run immediately when this file is loaded, and is
     * benchmarked as environment_setup.
     *
     * For security, this function also destroys the $_REQUEST global variable.
     * Using the proper global (GET, POST, COOKIE, etc) is inherently more secure.
     * The recommended way to fetch a global variable is using the Input library.
     *
     * @see http://www.php.net/globals
     *
     * @return void
     */
    public static function setup() {
        // This function can only be run once
        if (static::isBooted()) {
            return;
        }
        self::validateFileUpload();
        // Start the environment setup benchmark
        CFBenchmark::start(SYSTEM_BENCHMARK . '_environment_setup');

        // Set autoloader
        spl_autoload_register(['CF', 'autoLoad']);

        // Set and test the logger instance, we need to know whats wrong when CF Fail
        self::$logger = CLogger::logger();

        CFBenchmark::stop(SYSTEM_BENCHMARK . '_environment_setup');
        self::fireCallbacks(self::$bootingCallbacks);
        static::loadBootstrapFiles();
        // Setup is complete, prevent it from being run again
        self::$booted = true;
        // Stop the environment setup routine
        self::fireCallbacks(self::$bootedCallbacks);
    }

    /**
     * Load all bootstrap files.
     *
     * @return void
     */
    private static function loadBootstrapFiles() {
        CFBenchmark::start(SYSTEM_BENCHMARK . '_environment_bootstrap');

        CFBenchmark::start(SYSTEM_BENCHMARK . '_environment_system_bootstrap');
        $bootstrapPath = DOCROOT . 'system' . DS;
        if (file_exists($bootstrapPath . 'bootstrap' . EXT)) {
            include $bootstrapPath . 'bootstrap' . EXT;
        }
        CFBenchmark::stop(SYSTEM_BENCHMARK . '_environment_system_bootstrap');
        //try to locate bootstrap files for modules
        CFBenchmark::start(SYSTEM_BENCHMARK . '_environment_module_bootstrap');

        foreach (CF::modules() as $module) {
            $bootstrapPath = DOCROOT . 'modules' . DS . $module . DS;
            if (file_exists($bootstrapPath . 'bootstrap' . EXT)) {
                include $bootstrapPath . 'bootstrap' . EXT;
            }
        }

        CFBenchmark::stop(SYSTEM_BENCHMARK . '_environment_module_bootstrap');

        //try to locate bootstrap files for application
        CFBenchmark::start(SYSTEM_BENCHMARK . '_environment_application_bootstrap');
        $bootstrapPath = DOCROOT . 'application' . DS . CF::appCode() . DS;

        if (file_exists($bootstrapPath . 'bootstrap' . EXT)) {
            include $bootstrapPath . 'bootstrap' . EXT;
        }
        CFBenchmark::stop(SYSTEM_BENCHMARK . '_environment_application_bootstrap');

        //try to locate bootstrap files for org
        if (strlen((string) CF::orgCode()) > 0) {
            $bootstrapPath .= CF::orgCode() . DS;
            if (file_exists($bootstrapPath . 'bootstrap' . EXT)) {
                include $bootstrapPath . 'bootstrap' . EXT;
            }
        }
        CFBenchmark::stop(SYSTEM_BENCHMARK . '_environment_bootstrap');
    }

    /**
     * Invoke.
     *
     * @param mixed $uri
     *
     * @return void
     */
    public static function invoke($uri) {
        $oldRequest = c::request();
        $request = CHTTP_Request::create($uri, $oldRequest->method());

        return  c::router()->dispatchToRoute($request);
    }

    /**
     * Displays a 404 page.
     *
     * @param string $page     URI of page
     * @param string $template custom template
     *
     * @throws CHTTP_Exception_NotFoundHttpException
     *
     * @deprecated 1.8 use c::abort(404)
     *
     * @return void
     */
    public static function show404($page = false, $template = false) {
        return c::abort(404);
    }

    /**
     * @param string      $directory
     * @param null|string $domain
     *
     * @return string
     */
    public static function getDir($directory = '', $domain = null) {
        $includePaths = CF::paths($domain);
        foreach ($includePaths as $p) {
            $path = $p;
            if (strlen($directory) > 0) {
                $path = $p . $directory . DS;
            }
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param string $directory
     * @param string $domain
     * @param mixed  $withShared
     *
     * @return array array of directory
     */
    public static function getDirs($directory, $domain = null, $withShared = true) {
        $includePaths = CF::paths($domain, false, $withShared);
        $dirs = [];
        foreach ($includePaths as $p) {
            $path = $p . $directory . DS;
            if (is_dir($path)) {
                $dirs[] = $path;
            }
        }

        return $dirs;
    }

    /**
     * @param string $directory
     * @param string $filename
     * @param string $domain
     * @param mixed  $force_reload
     *
     * @return string[]
     */
    public static function getFiles($directory, $filename, $domain = null, $force_reload = false) {
        if ($domain == null) {
            $domain = CF::domain();
        }

        $paths = CF::paths($domain, $force_reload);

        $result = [];
        foreach ($paths as $path) {
            if (file_exists($path . $directory . DS . $filename . EXT)) {
                $result[] = $path . $directory . DS . $filename . EXT;
            }
        }

        return $result;
    }

    /**
     * @param string $directory
     * @param string $filename
     * @param string $domain
     *
     * @return string
     */
    public static function getFile($directory, $filename, $domain = null) {
        $files = CF::getFiles($directory, $filename, $domain);
        if (count($files) > 0) {
            return $files[0];
        }

        return null;
    }

    /**
     * Get all include paths. APPPATH is the first path, followed by module
     * paths in the order they are configured, follow by the SYSPATH.
     *
     * @param null|mixed $domain
     * @param bool       $forceReload
     * @param mixed      $withShared
     *
     * @return array
     */
    public static function paths($domain = null, $forceReload = false, $withShared = true) {
        if ($domain == null) {
            $domain = CF::domain();
        }
        $isDiffAppCode = false;
        if (CF::appCode() != CF::appCode($domain)) {
            $isDiffAppCode = true;
        }
        if (CF::isTesting() || $isDiffAppCode) {
            $forceReload = true;
        }

        $cacheKey = 'paths.' . $domain . '.' . ($withShared ? 'withShared' : 'withoutShared');
        $paths = null;
        if (!$forceReload) {
            $paths = static::getInternalCache($cacheKey);
        }
        if ($paths === null) {
            //we try to search all paths for this domain
            $paths = [];
            $orgCode = CF::orgCode($domain);
            $appCode = $isDiffAppCode ? CF::appCode() : CF::appCode($domain);

            $modules = CF::modules($domain);
            //when this domain is org
            if ($orgCode != null && strlen($orgCode) > 0) {
                $paths[] = APPPATH . $appCode . DS . $orgCode . DS;
            }
            if ($appCode != null && strlen($appCode) > 0) {
                //add theme path if theme exists
                $paths[] = APPPATH . $appCode . DS . 'default' . DS;
            }
            if ($withShared) {
                $sharedAppCode = CF::getSharedApp($domain);
                foreach ($sharedAppCode as $key => $value) {
                    if (strlen($orgCode) > 0) {
                        //add theme path if theme exists
                        $paths[] = APPPATH . $value . DS . $orgCode . DS;
                    }
                    $paths[] = APPPATH . $value . DS . 'default' . DS;
                }
            }

            foreach ($modules as $module) {
                $paths[] = MODPATH . $module . DS;
            }
            $paths[] = SYSPATH;
            $paths[] = DOCROOT;
            static::setInternalCache($cacheKey, $paths);
        }

        return $paths;
    }

    /**
     * Get a config item or group.
     *
     * @param mixed      $key
     * @param null|mixed $default
     * @param mixed      $required
     *
     * @return CConfig|mixed
     */
    public static function config($key, $default = null, $required = true) {
        return CConfig::repository()->get($key, $default);
    }

    /**
     * Add a new message to the log.
     *
     * @param string $level
     * @param string $message
     * @param mixed  $context
     *
     * @return void
     */
    public static function log($level, $message, $context = []) {
        if (class_exists('CLogger')) {
            CLogger::instance()->log($level, $message, $context);
        }
    }

    /**
     * Controller file path candidates for the given class name segments.
     *
     * @param array $segments
     *
     * @return array
     */
    public static function controllerFileCandidates($segments) {
        $file = array_pop($segments);
        $lcFirstDirectories = array_map('lcfirst', $segments);
        $lowerDirectories = array_map('strtolower', $segments);

        // Kapitalisasi folder controller bebas, jadi siapkan varian lain sebagai cadangan
        $candidates = [
            array_merge($lcFirstDirectories, [lcfirst($file)]),
            array_merge($lowerDirectories, [lcfirst($file)]),
            array_merge($lowerDirectories, [strtolower($file)]),
            array_merge($lowerDirectories, [$file]),
        ];

        $paths = [];
        foreach ($candidates as $candidate) {
            $path = implode(DS, $candidate);
            $paths[$path] = $path;
        }

        return array_values($paths);
    }

    /**
     * Provides class auto-loading.
     *
     * @param string $class
     * @param string $directory
     *
     * @return bool
     */
    public static function autoLoad($class, $directory = 'libraries') {
        if (class_exists($class, false)) {
            return true;
        }

        if (($prefix = strpos($class, '_')) > 0) {
            // Find the class suffix
            $prefix = substr($class, 0, $prefix);
        } else {
            // No suffix
            $prefix = false;
        }
        if (($suffix = strrpos($class, '_')) > 0) {
            // Find the class suffix
            $suffix = substr($class, $suffix + 1);
        } else {
            // No suffix
            $suffix = false;
        }

        if ($suffix === 'Controller' || $prefix === 'Controller') {
            $type = 'controllers';
            $directory = 'controllers';
            // Lowercase filename

            $file = substr($class, 0, -11);
            if ($prefix) {
                $file = substr($class, 11);
            }
            //$file = str_replace('_', DS, $file);
            $fileCandidates = self::controllerFileCandidates(explode('_', $file));
        } else {
            // This could be either a library or a helper, but libraries must
            // always be capitalized, so we check if the first character is
            // uppercase. If it is, we are loading a library, not a helper.
            $type = ($class[0] < 'a') ? 'libraries' : 'helpers';
            $file = $class;
        }

        $classNotFound = false;
        if ($type == 'controllers') {
            foreach ($fileCandidates as $fileCandidate) {
                if ($filename = self::findFile($type, $fileCandidate, false, false, false, false)) {
                    require $filename;

                    return true;
                }
            }

            $type = 'libraries';
            $directory = 'libraries';
            $file = $class;
        }

        if ($filename = self::findFile($type, $file)) {
            require $filename;
            $classNotFound = true;

            return true;
        }

        if (!$classNotFound) {
            // Transform the class name according to PSR-0
            $routing_class = ltrim($class, '\\');
            $routingFile = '';
            $namespace = '';

            $is_namespace = false;
            if ($last_namespace_position = strripos($routing_class, '\\')) {
                $is_namespace = true;
                $namespace = substr($routing_class, 0, $last_namespace_position);

                $routing_class = substr($routing_class, $last_namespace_position + 1);
                $routingFile = str_replace('\\', DS, $namespace) . DS;
            }

            $routingFile .= str_replace('_', DS, $routing_class);

            if (substr($routingFile, strlen($routingFile) - 1, 1) == DS) {
                $routingFile = substr($routingFile, 0, strlen($routingFile) - 1) . '_';
            }

            if ($directory == 'libraries') {
                // find file at vendor first
                if ($path = self::findFile('vendor', $routingFile)) {
                    // Load the class file

                    require $path;

                    if (class_exists($class) || interface_exists($class)) {
                        $classNotFound = false;

                        return true;
                    }
                }
            }

            if ($directory == 'libraries') {
                if (static::isTesting()) {
                    if ($path = self::findFile('tests', $routingFile)) {
                        // Load the class file

                        require $path;

                        if (class_exists($class) || interface_exists($class)) {
                            $classNotFound = false;

                            return true;
                        }
                    }
                }
            }

            // find file at libraries
            if ($path = self::findFile($directory, $routingFile)) {
                // Load the class file
                require $path;
                $classNotFound = true;

                return true;
            }

            // check route file at helpers
            if (!$classNotFound) {
                $tempRoutingFile = explode(DS, $routingFile);
                if (strtolower($tempRoutingFile[0]) == 'helpers') {
                    $tempRoutingFile[0] = 'helpers';
                    $routingFile = str_replace('Helpers' . DS, '', $routingFile);
                    $directory = 'helpers';
                    if ($path = self::findFile($directory, $routingFile)) {
                        // Load the class file

                        require $path;
                        $classNotFound = true;

                        return true;
                    }
                }
            }
        }

        if (!$classNotFound) {
            // The class could not be found
            $appPath = DOCROOT . 'application' . DS . static::appCode() . DS;
            if (file_exists($appPath . 'composer.json')) {
                $autoLoadPath = $appPath . 'vendor' . DS . 'autoload.php';
                if (file_exists($autoLoadPath)) {
                    $composerLoader = require $autoLoadPath;
                    $result = $composerLoader->loadClass($class);
                    if ($result === true) {
                        return true;
                    }
                }
            }

            return false;
        }

        if ($filename = self::findFile($type, $class)) {
            // Load the class extension
            require $filename;
        } elseif ($suffix !== 'Core' and class_exists($class . '_Core', false)) {
            // Class extension to be evaluated
            $extension = 'class ' . $class . ' extends ' . $class . '_Core { }';

            // Start class analysis
            $core = new ReflectionClass($class . '_Core');

            if ($core->isAbstract()) {
                // Make the extension abstract
                $extension = 'abstract ' . $extension;
            }

            // Transparent class extensions are handled using eval. This is
            // a disgusting hack, but it gets the job done.
            eval($extension);
        }

        return true;
    }

    /**
     * Detect CF is running on console in cf command or not.
     *
     * @return bool
     */
    public static function isCFCli() {
        return defined('CFCLI');
    }

    /**
     * Detect CF is running on console or not.
     *
     * @return bool
     */
    public static function isCli() {
        return php_sapi_name() === 'cli';
    }

    /**
     * To get cliDomain.
     *
     * @return string
     */
    public static function cliDomain() {
        $domain = null;
        if (static::cliAppCode()) {
            $domain = static::cliAppCode() . '.test';
        }

        if (defined('CFCLI_APPCODE')) {
            return constant('CFCLI_APPCODE') . '.test';
        }
        if (file_exists(static::CFCLI_CURRENT_DOMAIN_FILE)) {
            $domain = trim(file_get_contents(static::CFCLI_CURRENT_DOMAIN_FILE));
        }

        return $domain;
    }

    /**
     * To get cliAppCode.
     *
     * @return string
     */
    public static function cliAppCode() {
        if (defined('CFCLI_APPCODE')) {
            return constant('CFCLI_APPCODE');
        }
        if (CF::isTesting()) {
            $serverArgv = $_SERVER['argv'];
            if (!is_array($serverArgv)) {
                $serverArgv = [$serverArgv];
            }
            foreach ($serverArgv as $argv) {
                if (substr($argv, -strlen('phpunit.xml')) === (string) 'phpunit.xml') {
                    if (file_exists($argv)) {
                        $content = file_get_contents($argv);
                        $regex = '#<server\s?name="APP_CODE"\s?value="(.+?)"\s?/>#i';
                        if (preg_match($regex, $content, $matches)) {
                            return trim($matches[1]);
                        }
                    }
                }
            }
        }

        return null;
    }

    public static function domain() {
        $domain = '';
        if (CF::isTesting()) {
            $serverArgv = $_SERVER['argv'];
            if (!is_array($serverArgv)) {
                $serverArgv = [$serverArgv];
            }
            foreach ($serverArgv as $argv) {
                if (substr($argv, -strlen('phpunit.xml')) === (string) 'phpunit.xml') {
                    if (file_exists($argv)) {
                        $content = file_get_contents($argv);
                        $regex = '#<server\s?name="APP_CODE"\s?value="(.+?)"\s?/>#i';
                        if (preg_match($regex, $content, $matches)) {
                            return trim($matches[1]) . '.test';
                        }
                    }
                }
            }
        }
        if (static::isCli() || static::isCFCli()) {
            // Command line requires a bit of hacking
            if (defined('CFCLI_APPCODE')) {
                return constant('CFCLI_APPCODE') . '.test';
            }

            if (static::isCFCli() || static::isTesting()) {
                $domain = static::cliDomain();
            } else {
                if (isset($_SERVER['argv'][2])) {
                    $domain = $_SERVER['argv'][2];
                }
            }
        } else {
            if (isset($_SERVER['SERVER_NAME'])) {
                $domain = $_SERVER['SERVER_NAME'];
            }
            if (strlen($domain) == 0) {
                if (isset($_SERVER['HTTP_HOST'])) {
                    $domain = $_SERVER['HTTP_HOST'];
                }
            }
        }

        return $domain;
    }

    /**
     * Find a resource file in a given directory. Files will be located according
     * to the order of the include paths. Configuration and i18n files will be
     * returned in reverse order.
     *
     * @param mixed $directory  directory to search in
     * @param mixed $filename   filename to look for (without extension)
     * @param mixed $required   file required
     * @param mixed $ext        file extension
     * @param mixed $reload
     * @param mixed $withShared
     *
     * @throws Exception if file is required and not found
     *
     * @return array|string|bool if the type is config, i18n or l10n,
     */
    public static function findFile($directory, $filename, $required = false, $ext = false, $reload = false, $withShared = true) {
        // NOTE: This test MUST be not be a strict comparison (===), or empty
        // extensions will be allowed!
        if ($ext == '') {
            // Use the default extension
            $ext = EXT;
        } else {
            // Add a period before the extension
            $ext = '.' . $ext;
        }

        // Search path
        $search = $directory . '/' . $filename . $ext;
        // Nothing found, yet
        $found = null;
        $cacheKey = 'find_file_paths.' . $search . '.' . ($withShared ? 'withShared' : 'withoutShared');
        if (!$reload) {
            $found = static::getInternalCache($cacheKey);
        }

        if ($found === null) {
            // Load include paths
            $paths = self::paths(null, $reload, $withShared);

            if ($directory === 'config' or $directory === 'i18n') {
                // Search in reverse, for merging
                $paths = array_reverse($paths);
                foreach ($paths as $path) {
                    if (static::isFile($path . $search)) {
                        // A matching file has been found
                        $found[] = $path . $search;
                    }
                }
            } else {
                foreach ($paths as $path) {
                    if (static::isFile($path . $search)) {
                        // A matching file has been found
                        $found = $path . $search;

                        // Stop searching
                        break;
                    }
                }
            }

            if ($found === null) {
                if ($required === true) {
                    // If the file is required, throw an exception
                    throw new Exception(c::__('core.resource_not_found', [':directory' => $directory, ':filename' => $filename]));
                } else {
                    // Nothing was found, return FALSE
                    $found = false;
                }
            }

            static::setInternalCache($cacheKey, $found);
        }

        return $found;
    }

    /**
     * Checks if given data is file, handles mixed input.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private static function isFile($value) {
        $value = strval(str_replace("\0", '', $value));

        return is_file($value);
    }

    /**
     * Get data domain.
     *
     * @param string $domain
     *
     * @return array
     */
    public static function data($domain = null) {
        $domain = $domain == null ? self::domain() : $domain;

        if (is_null($domain)) {
            return [];
        }
        if (!isset(self::$data[$domain])) {
            self::$data[$domain] = CFData::domain($domain);
            if (self::$data[$domain] == null) {
                //try to locate wildcard subdomain
                $wildcardDomain = implode('.', ['$'] + array_slice(explode('.', $domain), 0));
                self::$data[$domain] = CFData::domain($wildcardDomain);
            }
        }

        return self::$data[$domain];
    }

    /**
     * Get application id for domain.
     *
     * @param null|mixed $domain
     *
     * @return string
     */
    public static function appId($domain = null) {
        $data = self::data($domain);

        return isset($data['app_id']) ? $data['app_id'] : null;
    }

    /**
     * Whether the current request entrypoint lives inside an app's own
     * folder (`application/{app}/...`) rather than the shared front
     * controller. Used by {@see appCode()} to resolve app identity when
     * there's no domain/Host header to look up (e.g. CLI, or an app-embedded
     * entrypoint).
     *
     * @return bool
     */
    public static function isIndexInApp() {
        $relativeIndex = str_replace(DOCROOT, '', CFINDEX);

        return strpos($relativeIndex, 'application/') !== false;
    }

    /**
     * Get application code for domain.
     *
     * @param null|mixed $domain
     *
     * @return string
     */
    public static function appCode($domain = null) {
        if (static::$forceAppCode) {
            return static::$forceAppCode;
        }
        if (self::isIndexInApp()) {
            $relativeIndex = str_replace(DOCROOT, '', CFINDEX);
            $paths = explode('/', $relativeIndex);
            if ($paths[0] == 'application') {
                return $paths[1];
            }
        }
        if (defined('CF_APPCODE')) {
            return constant('CF_APPCODE');
        }

        if (CF::isCFCli() || CF::isTesting()) {
            if (CF::cliAppCode()) {
                return CF::cliAppCode();
            }
        }

        $data = self::data($domain);

        $appCode = isset($data['app_code']) ? $data['app_code'] : null;
        if ($appCode == null && CF::domain()) {
            if (substr(CF::domain(), -5) === '.test') {
                $appCode = substr(CF::domain(), 0, -5);
            }
        }

        return $appCode;
    }

    /**
     * Get org id for domain.
     *
     * @param string $domain
     *
     * @return int
     */
    public static function orgId($domain = null) {
        $data = self::data($domain);

        return isset($data['org_id']) ? $data['org_id'] : null;
    }

    /**
     * Get org code for this domain.
     *
     * @param string $domain
     *
     * @return string
     */
    public static function orgCode($domain = null) {
        $data = self::data($domain);

        return isset($data['org_code']) ? $data['org_code'] : null;
    }

    /**
     * Add Shared App in runtime.
     *
     * @param string $appCode
     */
    public static function addSharedApp($appCode) {
        if (!in_array($appCode, self::$sharedAppCode)) {
            self::$sharedAppCode[] = $appCode;
            //clear all internal cache
            static::clearInternalCache();
        }
    }

    /**
     * Get shared application code for this domain.
     *
     * @param string $domain
     *
     * @return array
     */
    public static function getSharedApp($domain = null) {
        $data = self::data($domain);
        if (!isset($data['shared_app_code'])) {
            $data['shared_app_code'] = [];
        }

        $data['shared_app_code'] = array_merge(
            $data['shared_app_code'],
            self::declaredSharedApp($domain),
            self::$sharedAppCode
        );

        return array_values(array_unique($data['shared_app_code']));
    }

    /**
     * Get the shared application code declared by the application itself.
     *
     * The file is read before any configuration is loaded, so an application
     * whose config refers to a class of the application it shares with can be
     * resolved. Registering the same code with addSharedApp() from the
     * application bootstrap is too late for that: by then every config file
     * has already been evaluated.
     *
     * @param string $domain
     *
     * @return array
     */
    protected static function declaredSharedApp($domain = null) {
        $appCode = self::appCode($domain);
        if (strlen((string) $appCode) == 0) {
            return [];
        }

        if (!isset(self::$declaredSharedAppCode[$appCode])) {
            $declared = [];
            $file = DOCROOT . 'application' . DS . $appCode . DS . 'shared' . EXT;
            if (file_exists($file)) {
                $declared = require $file;
                if (!is_array($declared)) {
                    $declared = [];
                }
            }

            self::$declaredSharedAppCode[$appCode] = $declared;
        }

        return self::$declaredSharedAppCode[$appCode];
    }

    /**
     * Get theme for this domain.
     *
     * @param null|mixed $domain
     *
     * @return array
     *
     * @deprecated since 1.2
     */
    public static function theme($domain = null) {
        $data = self::data($domain);

        return isset($data['theme']) ? $data['theme'] : null;
    }

    /**
     * Get modules for this domain.
     *
     * @param null|mixed $domain
     *
     * @return array
     */
    public static function modules($domain = null) {
        $data = self::data($domain);

        return isset($data['modules']) ? $data['modules'] : ['cresenity'];
    }

    /**
     * @return string
     */
    public static function version() {
        return CF_VERSION;
    }

    /**
     * @return string
     */
    public static function codeName() {
        return CF_CODENAME;
    }

    /**
     * @param string $domain
     *
     * @return string
     */
    public static function appPath($domain = null) {
        $appCode = static::appCode($domain);

        return DOCROOT . 'application' . DS . $appCode;
    }

    /**
     * Get current running controller.
     *
     * @return CController
     */
    public static function currentController() {
        return CHTTP::kernel()->controller();
    }

    /**
     * Get the current application locale.
     *
     * @return string
     */
    public static function getLocale() {
        return static::$locale;
    }

    /**
     * Get the current application charset.
     *
     * @return string
     */
    public static function getCharset() {
        return static::$charset;
    }

    /**
     * Get the current application fallback locale.
     *
     * @return string
     */
    public static function getFallbackLocale() {
        return static::$fallbackLocale;
    }

    /**
     * Set the current application locale.
     *
     * @param string $locale
     *
     * @return void
     */
    public static function setLocale($locale) {
        static::$locale = $locale;
        // setlocale(LC_ALL, $locale);
        CTranslation::translator()->setLocale($locale);
        CCarbon::setLocale($locale);
        CEvent::dispatch('cf.locale.updated');
    }

    /**
     * Set the current application fallback locale.
     *
     * @param string $fallbackLocale
     *
     * @return void
     */
    public static function setFallbackLocale($fallbackLocale) {
        static::$fallbackLocale = $fallbackLocale;
        CTranslation::translator()->setFallback($fallbackLocale);
    }

    /**
     * Get current application directory.
     *
     * @param null|string $appCode
     *
     * @return bool
     */
    public static function appDir($appCode = null) {
        if ($appCode == null) {
            $appCode = static::appCode();
        }

        return DOCROOT . 'application' . DS . $appCode;
    }

    /**
     * Check if CF is run under devsuite.
     *
     * @return bool
     */
    public static function isDevSuite() {
        return substr(CF::domain(), strlen('.test') * -1) === '.test';
    }

    /**
     * Check if CF is run under testing.
     *
     * @return bool
     */
    public static function isTesting() {
        if (defined('CFTesting')
            || (is_array($_SERVER) && isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'testing')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Return all app availables.
     *
     * @return array
     */
    public static function getAvailableAppCode() {
        $path = DOCROOT . 'application';
        $directories = CFile::directories($path);

        return c::collect($directories)->map(function ($v) {
            return basename($v);
        })->all();
    }

    /**
     * Check appCode is exits on directory.
     *
     * @param mixed $appCode
     *
     * @return bool
     */
    public static function appCodeExists($appCode) {
        return in_array($appCode, static::getAvailableAppCode());
    }

    /**
     * Get CF internal cache.
     *
     * @param string     $key
     * @param null|mixed $default
     *
     * @return mixed
     */
    private static function getInternalCache($key, $default = null) {
        if (isset(static::$internalCache[$key])) {
            return static::$internalCache[$key];
        }

        return $default;
    }

    /**
     * Set CF internal cache.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    private static function setInternalCache($key, $value) {
        static::$internalCache[$key] = $value;
    }

    /**
     * Clear CF internal cache.
     *
     * @return void
     */
    private static function clearInternalCache() {
        static::$internalCache = [];
    }

    const MAINTENANCE_COOKIE = 'cf_maintenance_bypass';

    const MAINTENANCE_UP = 'up';

    const MAINTENANCE_DOWN = 'down';

    const MAINTENANCE_BYPASS = 'bypass';

    const MAINTENANCE_GRANT = 'grant';

    /**
     * Determine if the application is down for maintenance.
     *
     * @return false|CHTTP_Response
     */
    public static function isDownForMaintenance() {
        $file = CF::findFile('data', 'down');
        if ($file == null) {
            return false;
        }

        $data = @include $file;
        $request = CHTTP::request();
        $decision = self::maintenanceDecision($data, (string) $request->path(), (array) $request->cookie());

        if ($decision == self::MAINTENANCE_UP || $decision == self::MAINTENANCE_BYPASS) {
            return false;
        }

        if ($decision == self::MAINTENANCE_GRANT) {
            return c::redirect(curl::base())->withCookie(
                CHTTP::cookie()->make(self::MAINTENANCE_COOKIE, (string) carr::get($data, 'secret'), 60 * 12)
            );
        }

        return c::response()->view(carr::get($data, 'view', 'system.maintenance'), ['data' => $data], 503);
    }

    /**
     * Resolve the maintenance decision from configuration and request values.
     *
     * @param mixed  $data   contents of `down.php`
     * @param string $path   request path
     * @param array  $cookie request cookies
     *
     * @return string one of the `MAINTENANCE_*` constants
     */
    public static function maintenanceDecision($data, $path, array $cookie = []) {
        if (!is_array($data) || !carr::get($data, 'down', false)) {
            return self::MAINTENANCE_UP;
        }

        $secret = (string) carr::get($data, 'secret', '');
        if (strlen($secret) > 0) {
            if (trim((string) $path, '/') === $secret) {
                return self::MAINTENANCE_GRANT;
            }

            $value = (string) carr::get($cookie, self::MAINTENANCE_COOKIE, '');
            if (strlen($value) > 0 && hash_equals($secret, $value)) {
                return self::MAINTENANCE_BYPASS;
            }
        }

        $legacy = (string) carr::get($data, 'cookie', '');
        if (strlen($legacy) > 0 && array_key_exists($legacy, $cookie)) {
            return self::MAINTENANCE_BYPASS;
        }

        return self::MAINTENANCE_DOWN;
    }

    /**
     * Run callback with specific appCode.
     *
     * @param string   $appCode
     * @param callable $callback
     *
     * @return mixed
     */
    public static function asAppCode($appCode, $callback) {
        if (is_callable($callback)) {
            $domain = CF::domain();
            $originalAppCode = static::appCode();
            $result = null;
            if ($originalAppCode) {
                static::$forceAppCode = $appCode;
                static::$data[$domain]['app_code'] = $appCode;
                $result = $callback();
                static::$data[$domain]['app_code'] = $originalAppCode;
                static::$forceAppCode = null;
            }

            return $result;
        }
    }

    protected static function validateFileNamesArray(array $names) {
        foreach ($names as $name) {
            if (!is_array($name)) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                if (strlen($ext) > 3) {
                    $ext = substr($ext, 0, 3);
                }
                if (in_array(strtolower($ext), ['php', 'sh', 'htm', 'pht'])) {
                    die('Not Allowed X_X');
                }
            } else {
                static::validateFileNamesArray($name);
            }
        }
    }

    public static function validateFileUpload() {
        if (isset($_FILES) && is_array($_FILES)) {
            foreach ($_FILES as $v) {
                if (isset($v['name'])) {
                    $t = $v['name'];

                    if (!is_array($t)) {
                        $t = [$t];
                    }
                    static::validateFileNamesArray($t);
                }
            }
        }
    }

    /**
     * Register a terminating callback with the application.
     *
     * @param callable|string $callback
     *
     * @return $this
     */
    public static function terminating($callback) {
        self::$terminatingCallbacks[] = $callback;
    }

    /**
     * Terminate the application.
     *
     * @return void
     */
    public static function terminate() {
        $index = 0;

        while ($index < count(self::$terminatingCallbacks)) {
            CContainer::getInstance()->call(self::$terminatingCallbacks[$index]);

            $index++;
        }
    }

    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    public static function isBooted() {
        return self::$booted;
    }

    /**
     * Register a new boot listener.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function booting($callback) {
        self::$bootingCallbacks[] = $callback;
    }

    /**
     * Register a new "booted" listener.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function booted($callback) {
        self::$bootedCallbacks[] = $callback;

        if (self::isBooted()) {
            $callback();
        }
    }

    /**
     * Call the booting callbacks for the application.
     *
     * @param callable[] $callbacks
     *
     * @return void
     */
    protected static function fireCallbacks(array &$callbacks) {
        $index = 0;

        while ($index < count($callbacks)) {
            $callbacks[$index]();

            $index++;
        }
    }
}
