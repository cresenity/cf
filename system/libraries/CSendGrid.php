<?php

/**
 * This library allows you to quickly and easily send emails through SendGrid using PHP.
 *
 * @author    Elmer Thomas <dx@sendgrid.com>
 * @copyright 2017 SendGrid
 * @license   https://opensource.org/licenses/MIT The MIT License
 *
 * @version   GIT: <git_id>
 *
 * @link      http://packagist.org/packages/sendgrid/sendgrid
 */

/**
 * Interface to the SendGrid Web API.
 */
class CSendGrid {
    const VERSION = '6.0.0';

    /**
     * @var CSendGrid_HTTP_Client
     */
    public $client;

    /**
     * @var string
     */
    public $version = self::VERSION;

    /**
     * @var string
     */
    protected $namespace = 'SendGrid';

    /**
     * Setup the HTTP Client.
     *
     * @param string $apiKey  your SendGrid API Key
     * @param array  $options an array of options, currently only "host" and "curl" are implemented
     */
    public function __construct($apiKey, $options = []) {
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'User-Agent: sendgrid/' . $this->version . ';php',
            'Accept: application/json'
        ];

        $host = isset($options['host']) ? $options['host'] : 'https://api.sendgrid.com';

        $curlOptions = isset($options['curl']) ? $options['curl'] : null;

        $this->client = new CSendGrid_HTTP_Client($host, $headers, '/v3', null, $curlOptions);
    }
}
