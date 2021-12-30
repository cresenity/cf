<?php

class CRedis_Limiter_DurationLimiterBuilder {
    use CTrait_Helper_InteractsWithTime;

    /**
     * The Redis connection.
     *
     * @var CRedis_ConnectionInterface
     */
    public $connection;

    /**
     * The name of the lock.
     *
     * @var string
     */
    public $name;

    /**
     * The maximum number of locks that can obtained per time window.
     *
     * @var int
     */
    public $maxLocks;

    /**
     * The amount of time the lock window is maintained.
     *
     * @var int
     */
    public $decay;

    /**
     * The amount of time to block until a lock is available.
     *
     * @var int
     */
    public $timeout = 3;

    /**
     * Create a new builder instance.
     *
     * @param \CRedis_AbstractConnection $connection
     * @param string                     $name
     *
     * @return void
     */
    public function __construct($connection, $name) {
        $this->name = $name;
        $this->connection = $connection;
    }

    /**
     * Set the maximum number of locks that can obtained per time window.
     *
     * @param int $maxLocks
     *
     * @return $this
     */
    public function allow($maxLocks) {
        $this->maxLocks = $maxLocks;

        return $this;
    }

    /**
     * Set the amount of time the lock window is maintained.
     *
     * @param int $decay
     *
     * @return $this
     */
    public function every($decay) {
        $this->decay = $this->secondsUntil($decay);

        return $this;
    }

    /**
     * Set the amount of time to block until a lock is available.
     *
     * @param int $timeout
     *
     * @return $this
     */
    public function block($timeout) {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Execute the given callback if a lock is obtained, otherwise call the failure callback.
     *
     * @param callable      $callback
     * @param null|callable $failure
     *
     * @throws CRedis_Exception_LimiterTimeoutException
     *
     * @return mixed
     */
    public function then(callable $callback, callable $failure = null) {
        try {
            return (new CRedis_Limiter_DurationLimiter(
                $this->connection,
                $this->name,
                $this->maxLocks,
                $this->decay
            ))->block($this->timeout, $callback);
        } catch (CRedis_Exception_LimiterTimeoutException $e) {
            if ($failure) {
                return $failure($e);
            }

            throw $e;
        }
    }
}
