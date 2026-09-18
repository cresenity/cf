<?php

/**
 * Description of RedisLock.
 */
class CCache_Lock_RedisLock extends CCache_LockAbstract {
    /**
     * The Redis factory implementation.
     *
     * @var CRedis_AbstractConnection
     */
    protected $redis;

    /**
     * Create a new lock instance.
     *
     * @param CRedis_AbstractConnection $redis
     * @param string                    $name
     * @param int                       $seconds
     * @param null|string               $owner
     *
     * @return void
     */
    public function __construct($redis, $name, $seconds, $owner = null) {
        parent::__construct($name, $seconds, $owner);

        $this->redis = $redis;
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire() {
        if ($this->seconds > 0) {
            return $this->redis->set($this->name, $this->owner, 'EX', $this->seconds, 'NX') == true;
        } else {
            return $this->redis->setnx($this->name, $this->owner) === 1;
        }
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release() {
        return (bool) $this->redis->eval(CCache_LuaScripts::releaseLock(), 1, $this->name, $this->owner);
    }

    /**
     * Releases this lock in disregard of ownership.
     *
     * @return void
     */
    public function forceRelease() {
        $this->redis->del($this->name);
    }

    /**
     * Returns the owner value written into the driver for this lock.
     *
     * @return string
     */
    protected function getCurrentOwner() {
        return $this->redis->get($this->name);
    }
}
