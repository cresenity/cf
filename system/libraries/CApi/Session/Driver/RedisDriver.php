<?php

class CApi_Session_Driver_RedisDriver extends CApi_Session_DriverAbstract {
    /**
     * The cache repository instance.
     *
     * @var CCache_Repository
     */
    protected $cache;

    protected $apiGroup;

    /**
     * The number of seconds to store the data in the cache.
     *
     * @var int
     */
    protected $seconds;

    /**
     * Create a new cache driven handler instance.
     *
     * @param CCache_Repository $cache
     * @param int               $seconds
     * @param mixed             $apiGroup
     *
     * @return void
     */
    public function __construct($apiGroup, CCache_Repository $cache, $seconds = null) {
        if ($seconds == null) {
            $seconds = 60 * 60;
        }
        $this->cache = $cache;
        $this->seconds = $seconds;
        $this->apiGroup = $apiGroup;
    }

    public function close() {
        return true;
    }

    public function destroy($id) {
        return $this->cache->forget($this->apiGroup . '_' . $id);
    }

    public function gc($maxlifetime) {
        return true;
    }

    public function open($path, $name) {
        return true;
    }

    public function read($id) {
        return $this->cache->get($this->apiGroup . '_' . $id, []);
    }

    public function regenerate() {
    }

    public function write($id, $data) {
        if (is_array($data)) {
            $data['expiredTime'] = CCarbon::now()->addSeconds($this->seconds)->format('Y-m-d H:i:s');
        }

        return $this->cache->put($this->apiGroup . '_' . $id, $data, $this->seconds);
    }

    /**
     * Get the underlying cache repository.
     *
     * @return CCache_Repository
     */
    public function getCache() {
        return $this->cache;
    }
}
