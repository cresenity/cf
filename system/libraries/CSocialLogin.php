<?php

defined('SYSPATH') or die('No direct access allowed.');

class CSocialLogin {
    /**
     * Fake providers registered by fake(), keyed by driver name.
     *
     * @var array<string, CSocialLogin_Testing_FakeProvider>
     */
    protected static $fakes = [];

    /**
     * @param string $driverName
     * @param array  $options
     *
     * @return CSocialLogin_AbstractProviderInterface
     */
    public static function driver($driverName, $options = []) {
        if (isset(static::$fakes[$driverName])) {
            return static::$fakes[$driverName]->withConfig($options);
        }

        $driverManager = new CSocialLogin_DriverManager();

        return $driverManager->setConfig($options)->driver($driverName);
    }

    /**
     * Register a custom driver creator, see CSocialLogin_DriverManager::extend().
     *
     * @param string   $driver
     * @param \Closure $callback
     *
     * @return void
     */
    public static function extend($driver, Closure $callback) {
        (new CSocialLogin_DriverManager())->extend($driver, $callback);
    }

    /**
     * Replace a driver with a fake for tests: redirect() goes to a fake URL and user() returns
     * $user (a user instance, a closure returning one, or null for CSocialLogin_OAuth2_User::fake()).
     *
     * @param string                                             $driver
     * @param null|\Closure|CSocialLogin_Contract_UserInterface $user
     *
     * @return CSocialLogin_Testing_FakeProvider
     */
    public static function fake($driver, $user = null) {
        return static::$fakes[$driver] = new CSocialLogin_Testing_FakeProvider($driver, $user);
    }

    /**
     * Whether fake() registered a stand-in for the driver.
     *
     * @param string $driver
     *
     * @return bool
     */
    public static function hasFake($driver) {
        return isset(static::$fakes[$driver]);
    }

    /**
     * Remove every fake registered through fake().
     *
     * @return void
     */
    public static function forgetFakes() {
        static::$fakes = [];
    }
}
