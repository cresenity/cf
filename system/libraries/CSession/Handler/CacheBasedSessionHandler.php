<?php

/**
 * Description of CacheBasedSessionHandler.
 */
class CSession_Handler_CacheBasedSessionHandler implements SessionHandlerInterface {
    /**
     * The cache repository instance.
     *
     * @var CCache_Repository
     */
    protected $cache;

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
     *
     * @return void
     */
    public function __construct(CCache_Repository $cache, $seconds) {
        $this->cache = $cache;
        $this->seconds = $seconds;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function open($savePath, $sessionName) {
        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function close() {
        return true;
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function read($sessionId) {
        return $this->cache->get($sessionId, '');
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function write($sessionId, $data) {
        return $this->cache->put($sessionId, $data, $this->seconds);
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function destroy($sessionId) {
        return $this->cache->forget($sessionId);
    }

    /**
     * @inheritdoc
     */
    #[\ReturnTypeWillChange]
    public function gc($lifetime) {
        return true;
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
