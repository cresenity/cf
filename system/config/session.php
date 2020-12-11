<?php

defined('SYSPATH') OR die('No direct access allowed.');

return [
    /**
     * Session driver name. This option controls the default session "driver" that will be used on
     * requests. By default, we will use the lightweight native driver but
     * you may specify any of the other wonderful drivers provided here.
     * 
     * set null if you dont want to using the session 
     * 
     * Supported: "file", "cookie", "database", "apc",
     *            "memcached", "redis", "dynamodb", "array"
     */
    'driver' => 'file',
    /**
     * Session storage parameter, used by drivers.
     */
    'storage' => DOCROOT . 'temp' . DS . 'session',
    /**
     * used only when driver is database
     */
    'table' => 'session',
    /**
     * Session name.
     * It must contain only alphanumeric characters and underscores. At least one letter must be present.
     */
    'name' => 'cfsession',
    /**
     * Session parameters to validate: user_agent, ip_address, expiration.
     */
    'validate' => array('user_agent'),
    /**
     * Enable or disable session encryption.
     * Note: this has no effect on the native session driver.
     * Note: the cookie driver always encrypts session data. Set to TRUE for stronger encryption.
     */
    'encryption' => false,
    /**
     * Session lifetime. Number of seconds that each session will last.
     * A value of 0 will keep the session active until the browser is closed (with a limit of 24h).
     */
    'expiration' => 7200,
    'expire_on_close' => false,
    /**
     * Number of page loads before the session id is regenerated.
     * A value of 0 will disable automatic session id regeneration.
     */
    'regenerate' => 3,
    /**
     * Percentage probability that the gc (garbage collection) routine is started.
     */
    'gc_probability' => 2,
    /**
     * Enable this option to disable the cookie from being accessed when using a
     * secure protocol. This option is only available in PHP 5.2 and above.
     */
    'httponly' => false,
    /**
     * Restrict cookies to a specific path, typically the installation directory.
     */
    'path' => '/',
    /**
     * Domain, to restrict the cookie to a specific website domain. For security,
     * you are encouraged to set this option. An empty setting allows the cookie
     * to be read by any website domain.
     */
    'domain' => null,
    /**
     * Enable this option to only allow the cookie to be read when using the a
     * secure protocol.
     */
    'secure' => null,
    /**
     * This option determines how your cookies behave when cross-site requests
     * take place, and can be used to mitigate CSRF attacks. By default, we
     * will set this value to "lax" since this is a secure default value.
     *
     * Supported: "lax", "strict", "none", null
     */
    'same_site' => null,
];




