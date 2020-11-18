<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class CDevSuite_PackageManager_Eopkg extends CDevSuite_PackageManager {

    public $cli;

    /**
     * Create a new Eopkg instance.
     *
     * @param CommandLine $cli
     * @return void
     */
    public function __construct() {
        $this->cli = CDevSuite::commandLine();
    }

    /**
     * Get array of installed packages
     *
     * @param string $package
     * @return array
     */
    public function packages($package) {
        $query = "eopkg li | cut -d ' ' -f 1";

        return explode(PHP_EOL, $this->cli->run($query));
    }

    /**
     * Determine if the given package is installed.
     *
     * @param string $package
     * @return bool
     */
    public function installed($package) {
        return in_array($package, $this->packages($package));
    }

    /**
     * Ensure that the given package is installed.
     *
     * @param string $package
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
     * @return void
     */
    public function installOrFail($package) {
        CDevSuite::output('<info>[' . $package . '] is not installed, installing it now via Eopkg...</info> 🍻');

        $this->cli->run(trim('eopkg install -y ' . $package), function ($exitCode, $errorOutput) use ($package) {
            CDevSuite::output($errorOutput);

            throw new DomainException('Eopkg was unable to install [' . $package . '].');
        });
    }

    /**
     * Configure package manager on devsuite install.
     *
     * @return void
     */
    public function setup() {
        // Nothing to do
    }

    /**
     * Restart dnsmasq in Ubuntu.
     */
    public function nmRestart($sm) {
        $sm->restart(['NetworkManager']);
    }

    /**
     * Determine if package manager is available on the system.
     *
     * @return bool
     */
    public function isAvailable() {
        try {
            $output = $this->cli->run('which eopkg', function ($exitCode, $output) {
                throw new DomainException('Eopkg not available');
            });

            return $output != '';
        } catch (DomainException $e) {
            return false;
        }
    }

}
