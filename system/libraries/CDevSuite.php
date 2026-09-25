<?php

class CDevSuite {
    use CDevSuite_Trait_ConsoleTrait,
        CDevSuite_Trait_WindowsTrait,
        CDevSuite_Trait_MacTrait,
        CDevSuite_Trait_LinuxTrait;

    /**
     * @var CDevSuite_Configuration
     */
    protected static $configuration;

    /**
     * @var CDevSuite_Filesystem
     */
    protected static $filesystem;

    /**
     * @var CDevSuite_CommandLine
     */
    protected static $commandLine;

    /**
     * @var CDevSuite_DevCloud
     */
    protected static $devCloud;

    /**
     * @var CDevSuite_Nginx
     */
    protected static $nginx;

    /**
     * @var CDevSuite_System
     */
    protected static $system;

    /**
     * @var CDevSuite_PackageManager
     */
    protected static $packageManager;

    /**
     * @var CDevSuite_ServiceManager
     */
    protected static $serviceManager;

    /**
     * @var CDevSuite_Site
     */
    protected static $site;

    /**
     * @var CDevSuite_PhpFpm
     */
    protected static $phpFpm;

    /**
     * @var CDevSuite_DnsMasq
     */
    protected static $dnsMasq;

    /**
     * @var CDevSuite_Db
     */
    protected static $db;

    /**
     * @var CDevSuite_Ssh
     */
    protected static $ssh;

    /**
     * @var CDevSuite_DevCloud_Api
     */
    protected static $devCloudApi;

    public static function bootstrap() {
        CDevSuite_Bootstrap::instance()->bootstrap();
    }

    public static function homePath($path = '') {
        $basePath = static::homeDirectory() . DS . '.config' . DS . 'devsuite' . DS;

        $basePath = str_replace('\\', '/', $basePath);

        return $basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    public static function legacyHomePath() {
        $path = static::homeDirectory() . DS . '.devsuite' . DS;

        return str_replace('\\', '/', $path);
    }

    /**
     * `$_SERVER['HOME']` is only ever set by *nix shells - on Windows it's `USERPROFILE`
     * instead, and `CDevSuite_Bootstrap_DevSuiteBootstrapper` (which backfills `HOME` from
     * it) only runs for the `devsuite` command, not every command that ends up calling
     * `homePath()` (e.g. `claude:install`) - callers on Windows PowerShell hit "Undefined
     * array key HOME" without this fallback. Same normalization the bootstrapper does.
     *
     * @return string
     */
    protected static function homeDirectory() {
        if (isset($_SERVER['HOME'])) {
            return $_SERVER['HOME'];
        }
        if (isset($_SERVER['USERPROFILE'])) {
            return $_SERVER['USERPROFILE'];
        }

        return '';
    }

    public static function binPath() {
        $binPath = DOCROOT . '.bin' . DS . 'devsuite' . DS . static::osFolder() . DS;
        $binPath = str_replace('\\', '/', $binPath);

        return $binPath;
    }

    public static function serverPath() {
        $path = DOCROOT . 'devsuite.php';

        return str_replace('\\', '/', $path);
    }

    public static function stubsPath() {
        $path = DOCROOT . 'system' . DS . 'data' . DS . 'devsuite' . DS . 'stubs' . DS . static::osFolder() . DS;

        return str_replace('\\', '/', $path);
    }

    public static function scriptsPath() {
        $path = DOCROOT . 'system' . DS . 'data' . DS . 'devsuite' . DS . 'scripts' . DS;

        return str_replace('\\', '/', $path);
    }

    /**
     * @return CDevSuite_Db
     */
    public static function db() {
        if (static::$db == null) {
            static::$db = new CDevSuite_Db();
        }

        return static::$db;
    }

    /**
     * @return CDevSuite_Ssh
     */
    public static function ssh() {
        if (static::$ssh == null) {
            static::$ssh = new CDevSuite_Ssh();
        }

        return static::$ssh;
    }

    /**
     * @return CDevSuite_DevCloud_Api
     */
    public static function devCloudApi() {
        if (static::$devCloudApi == null) {
            static::$devCloudApi = new CDevSuite_DevCloud_Api();
        }

        return static::$devCloudApi;
    }

    public static function configuration() {
        if (static::$configuration == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$configuration = new CDevSuite_Linux_Configuration();

                    break;
                case CServer::OS_WINNT:
                    static::$configuration = new CDevSuite_Windows_Configuration();

                    break;
                case CServer::OS_DARWIN:
                    static::$configuration = new CDevSuite_Mac_Configuration();

                    break;
            }
        }

        return static::$configuration;
    }

    public static function filesystem() {
        if (static::$filesystem == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$filesystem = new CDevSuite_Linux_Filesystem();

                    break;
                case CServer::OS_WINNT:
                    static::$filesystem = new CDevSuite_Windows_Filesystem();

                    break;
                case CServer::OS_DARWIN:
                    static::$filesystem = new CDevSuite_Mac_Filesystem();

                    break;
            }
        }

        return static::$filesystem;
    }

    /**
     * Get CommandLine instance.
     *
     * @return CDevSuite_CommandLine
     */
    public static function commandLine() {
        if (static::$commandLine == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$commandLine = new CDevSuite_Linux_CommandLine();

                    break;
                case CServer::OS_WINNT:
                    static::$commandLine = new CDevSuite_Windows_CommandLine();

                    break;
                case CServer::OS_DARWIN:
                    static::$commandLine = new CDevSuite_Mac_CommandLine();

                    break;
            }
        }

        return static::$commandLine;
    }

    public static function devCloud() {
        if (static::$devCloud == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$devCloud = new CDevSuite_Linux_DevCloud();

                    break;
                case CServer::OS_WINNT:
                    static::$devCloud = new CDevSuite_Windows_DevCloud();

                    break;
                case CServer::OS_DARWIN:
                    static::$devCloud = new CDevSuite_Mac_DevCloud();

                    break;
            }
        }

        return static::$devCloud;
    }

    public static function system() {
        if (static::$system == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$system = new CDevSuite_Linux_System();

                    break;
                case CServer::OS_WINNT:
                    static::$system = new CDevSuite_Windows_System();

                    break;
                case CServer::OS_DARWIN:
                    static::$system = new CDevSuite_Mac_System();

                    break;
            }
        }

        return static::$system;
    }

    /**
     * @return CDevSuite_Site
     */
    public static function site() {
        if (static::$site == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$site = new CDevSuite_Linux_Site();

                    break;
                case CServer::OS_WINNT:
                    static::$site = new CDevSuite_Windows_Site();

                    break;
                case CServer::OS_DARWIN:
                    static::$site = new CDevSuite_Mac_Site();

                    break;
            }
        }

        return static::$site;
    }

    /**
     * @throws Exception
     *
     * @return CDevSuite_DnsMasq
     */
    public static function dnsMasq() {
        if (static::$dnsMasq == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$dnsMasq = new CDevSuite_Linux_DnsMasq();

                    break;
                case CServer::OS_DARWIN:
                    static::$dnsMasq = new CDevSuite_Mac_DnsMasq();

                    break;
                default:
                    throw new Exception('Dev Suite DnsMasq not available for this OS:' . CServer::getOS());

                    break;
            }
        }

        return static::$dnsMasq;
    }

    /**
     * @return CDevSuite_Nginx
     */
    public static function nginx() {
        if (static::$nginx == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$nginx = new CDevSuite_Linux_Nginx();

                    break;
                case CServer::OS_WINNT:
                    static::$nginx = new CDevSuite_Windows_Nginx();

                    break;
                case CServer::OS_DARWIN:
                    static::$nginx = new CDevSuite_Mac_Nginx();

                    break;
            }
        }

        return static::$nginx;
    }

    /**
     * Get PhpFpm instance.
     *
     * @return CDevSuite_PhpFpm
     */
    public static function phpFpm() {
        if (static::$phpFpm == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$phpFpm = new CDevSuite_Linux_PhpFpm();

                    break;
                case CServer::OS_WINNT:
                    static::$phpFpm = new CDevSuite_Windows_PhpFpm();

                    break;
                case CServer::OS_DARWIN:
                    static::$phpFpm = new CDevSuite_Mac_PhpFpm();

                    break;
            }
        }

        return static::$phpFpm;
    }

    public static function packageManager() {
        if (static::$packageManager == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$packageManager = c::collect([
                        CDevSuite_PackageManager_Apt::class,
                        CDevSuite_PackageManager_AptGet::class,
                        CDevSuite_PackageManager_Dnf::class,
                        CDevSuite_PackageManager_Pacman::class,
                        CDevSuite_PackageManager_Yum::class,
                        CDevSuite_PackageManager_PackageKit::class,
                        CDevSuite_PackageManager_Eopkg::class,
                    ])->map(function ($className) {
                        return CDevSuite_Helper::resolve($className);
                    })->first(static function ($pm) {
                        return $pm->isAvailable();
                    }, static function () {
                        throw new DomainException('No compatible package manager found.');
                    });

                    break;
            }
        }

        return static::$packageManager;
    }

    public static function serviceManager() {
        if (static::$serviceManager == null) {
            switch (CServer::getOS()) {
                case CServer::OS_LINUX:
                    static::$serviceManager = c::collect([
                        CDevSuite_ServiceManager_LinuxService::class,
                        CDevSuite_ServiceManager_Systemd::class,
                    ])->map(function ($className) {
                        return CDevSuite_Helper::resolve($className);
                    })->first(static function ($pm) {
                        return $pm->isAvailable();
                    }, static function () {
                        throw new DomainException('No compatible service manager found.');
                    });

                    break;
            }
        }

        return static::$serviceManager;
    }

    public static function user() {
        if (!isset($_SERVER['SUDO_USER'])) {
            return $_SERVER['USER'];
        }

        return $_SERVER['SUDO_USER'];
    }

    /**
     * Get the user's group.
     */
    public static function group() {
        switch (CServer::getOS()) {
            case CServer::OS_LINUX:
                if (!isset($_SERVER['SUDO_USER'])) {
                    return exec('id -gn ' . $_SERVER['USER']);
                }

                return exec('id -gn ' . $_SERVER['SUDO_USER']);
            default:
                return '';
        }
    }

    /**
     * Verify that the script is currently running as "sudo".
     *
     * @return void
     */
    public static function shouldBeSudo() {
        if (!isset($_SERVER['SUDO_USER'])) {
            throw new Exception('This command must be run with sudo.');
        }
    }

    public static function staticPrefix() {
        return '41c270e4-5535-4daa-b23e-c269744c2f45';
    }

    public static function osFolder() {
        $os = CServer::getOs();
        if ($os == CServer::OS_WINNT) {
            return 'win';
        }
        if ($os == CServer::OS_DARWIN) {
            return 'mac';
        }
        if ($os == CServer::OS_LINUX) {
            return 'linux';
        }

        return 'linux';
    }

    public static function tld() {
        return CDevSuite::configuration()->read()['tld'];
    }

    public static function isInstalled() {
        return static::filesystem()->isDir(static::homePath());
    }

    public static function loopback() {
        return '127.0.0.1';
    }
}
