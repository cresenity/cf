<?php

defined('SYSPATH') or die('No direct access allowed.');

class CGeo_IP {
    /**
     * Remote Machine IP address.
     *
     * @var float
     */
    protected $remote_ip = null;

    /**
     * Options of CGeo_IP.
     *
     * @var array
     */
    protected $options = null;

    /**
     * Create a new Geo_IP instance.
     *
     * @param array $options
     */
    public function __construct($options = []) {
        // Set IP
        $this->remote_ip = $this->getClientIP();
        $this->options = $options;
    }

    /**
     * Get the location from the provided IP.
     *
     * @param string $ip
     *
     * @return CGeo_Model_AddressCollection
     */
    public function getLocation($ip = null) {
        // Get location data
        return $this->find($ip);
    }

    /**
     * Find location from IP.
     *
     * @param string $ip
     *
     * @throws \Exception
     *
     * @return CGeo_Model_AddressCollection
     */
    private function find($ip = null) {
        // If IP not set, user remote IP
        $ip = $ip ?: $this->remote_ip;

        // Check if the ip is not local or empty
        if ($this->isValid($ip)) {
            try {
                // Find location
                $query = CGeo_Query_GeocodeQuery::create($ip);
                $location = $this->getProvider()->geocodeQuery($query);
                // Set currency if not already set by the service

                return $location;
            } catch (\Exception $e) {
                throw $e;
            }
        }

        throw new CGeo_Exception_InvalidArgument('Invalid IP Address:' . $ip);
    }

    /**
     * Get provider instance.
     *
     * @param null|mixed $providerClass
     *
     * @throws Exception
     *
     * @return CGeo_ProviderAbstract
     */
    public function getProvider($providerClass = null) {
        if ($providerClass == null) {
            $providerClass = 'CGeo_Provider_GeoIP2';
        }
        // Sanity check
        if ($providerClass === null) {
            throw new Exception('The GeoIP service is not valid.');
        }
        // Create service instance
        $adapter = new \Http\Adapter\Guzzle6\Client();
        switch ($providerClass) {
            case 'CGeo_Provider_GeoIP2':
                $databaseFile = SYSPATH . 'data/GeoLite2/Country.mmdb';
                $adapter = new CGeo_Provider_GeoIP2_Adapter(new \GeoIp2\Database\Reader($databaseFile), CGeo_Provider_GeoIP2_Adapter::GEOIP2_MODEL_COUNTRY);

                break;
        }
        $provider = new $providerClass($adapter);

        return $provider;
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    public function getClientIP() {
        $remotes_keys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
            'HTTP_X_CLUSTER_CLIENT_IP',
        ];
        foreach ($remotes_keys as $key) {
            if ($address = getenv($key)) {
                foreach (explode(',', $address) as $ip) {
                    if ($this->isValid($ip)) {
                        return $ip;
                    }
                }
            }
        }

        return '127.0.0.0';
    }

    /**
     * Checks if the ip is valid.
     *
     * @param string $ip
     *
     * @return bool
     */
    private function isValid($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE)
        ) {
            return false;
        }

        return true;
    }
}
