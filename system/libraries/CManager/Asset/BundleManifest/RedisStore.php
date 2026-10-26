<?php

/**
 * Penyimpanan manifest bundel di koneksi Redis bersama.
 */
class CManager_Asset_BundleManifest_RedisStore {
    /**
     * @var CRedis_AbstractConnection
     */
    protected $connection;

    public function __construct($connection) {
        $this->connection = $connection;
    }

    /**
     * @param string $key
     *
     * @return null|string
     */
    public function get($key) {
        $value = $this->connection->get($key);

        return $value === false ? null : $value;
    }

    /**
     * @param string $key
     * @param string $value
     * @param int    $ttl
     *
     * @return void
     */
    public function set($key, $value, $ttl) {
        $this->connection->setex($key, $ttl, $value);
    }
}
