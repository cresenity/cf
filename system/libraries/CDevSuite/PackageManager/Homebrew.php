<?php

class CDevSuite_PackageManager_Homebrew extends CDevSuite_PackageManager {
    const SUPPORTED_PHP_VERSIONS = [
        'php',
        'php@8.3',
        'php@8.1',
        'php@8.0',
        'php@7.4',
        'php@7.3',
        'php@7.2',
        'php@7.1',
        'php@7.0',
        'php73',
        'php72',
        'php71',
        'php70',
    ];

    public $cli;

    /**
     * Create a new Homebrew instance.
     *
     * @return void
     */
    public function __construct() {
        $this->cli = CDevSuite::commandLine();
    }

    /**
     * Get array of installed packages.
     *
     * @param string $package
     *
     * @return array
     */
    public function packages($package) {
        $query = "brew list --formula | grep {$package}";

        return explode(PHP_EOL, $this->cli->runAsUser($query));
    }

    /**
     * Determine if the given package is installed.
     *
     * @param string $package
     *
     * @return bool
     */
    public function installed($package) {
        // For php-fpm we need to tim the -fpm out of the string as
        // php-fpm gets installed among php
        $package = str_replace('-fpm', '', $package);

        return in_array($package, $this->packages($package));
    }

    /**
     * Ensure that the given package is installed.
     *
     * @param string $package
     *
     * @return void
     */
    public function ensureInstalled($package) {
        if (!$this->installed($package)) {
            $this->installOrFail($package);
        }
    }

    /**
     * Install the given package and throw an exception on failure.
     *
     * @param string $package
     *
     * @return void
     */
    public function installOrFail($package) {
        CDevSuite::output('<info>[' . $package . '] is not installed, installing it now via Brew...</info> 🍻');

        $this->cli->runAsUser(trim('brew install ' . $package), function ($exitCode, $errorOutput) use ($package) {
            CDevSuite::output($errorOutput);

            throw new DomainException('Brew was unable to install [' . $package . '].');
        });
    }

    /**
     * Configure package manager on valet install.
     *
     * @return void
     */
    public function setup() {
        // Nothing to do
    }

    /**
     * Restart dnsmasq in Ubuntu.
     *
     * @param mixed $sm
     */
    public function nmRestart($sm) {
        $sm->restart('NetworkManager');
    }

    /**
     * Determine if package manager is available on the system.
     *
     * @return bool
     */
    public function isAvailable() {
        try {
            $output = $this->cli->runAsUser('which brew', function ($exitCode, $output) {
                throw new DomainException('Brew not available');
            });

            return $output != '';
        } catch (DomainException $e) {
            return false;
        }
    }

    public function supportedPhpVersions() {
        return c::collect(static::SUPPORTED_PHP_VERSIONS);
    }
}
