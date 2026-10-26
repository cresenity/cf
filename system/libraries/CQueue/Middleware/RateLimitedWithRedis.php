<?php

/**
 * CQueue_Middleware_RateLimited counted atomically in Redis.
 */
class CQueue_Middleware_RateLimitedWithRedis extends CQueue_Middleware_RateLimited {
    /**
     * The timestamp of the end of the current duration by key.
     *
     * @var array
     */
    public $decaysAt = [];

    /**
     * @var null|string
     */
    protected $connectionName = null;

    /**
     * @param string      $limiterName
     * @param null|string $connection
     */
    public function __construct($limiterName, $connection = null) {
        parent::__construct($limiterName);

        $this->connectionName = $connection;
    }

    /**
     * @param mixed    $job
     * @param callable $next
     *
     * @return mixed
     */
    protected function handleJob($job, $next, array $limits) {
        foreach ($limits as $limit) {
            if ($this->tooManyAttempts($limit->key, $limit->maxAttempts, $limit->decayMinutes * 60)) {
                return $this->shouldRelease
                    ? $job->release($this->getTimeUntilNextRetry($limit->key))
                    : false;
            }
        }

        foreach ($limits as $limit) {
            if (!$this->acquire($limit->key, $limit->maxAttempts, $limit->decayMinutes * 60)) {
                return $this->shouldRelease
                    ? $job->release($this->getTimeUntilNextRetry($limit->key))
                    : false;
            }
        }

        return $next($job);
    }

    /**
     * @param string $key
     * @param int    $maxAttempts
     * @param int    $decaySeconds
     *
     * @return bool
     */
    protected function tooManyAttempts($key, $maxAttempts, $decaySeconds) {
        $limiter = $this->durationLimiter($key, $maxAttempts, $decaySeconds);
        $tooMany = $limiter->tooManyAttempts();
        $this->decaysAt[$key] = $limiter->decaysAt;

        return $tooMany;
    }

    /**
     * @param string $key
     * @param int    $maxAttempts
     * @param int    $decaySeconds
     *
     * @return bool
     */
    protected function acquire($key, $maxAttempts, $decaySeconds) {
        $limiter = $this->durationLimiter($key, $maxAttempts, $decaySeconds);
        $acquired = $limiter->acquire();
        $this->decaysAt[$key] = $limiter->decaysAt;

        return $acquired;
    }

    /**
     * @param string $key
     * @param int    $maxAttempts
     * @param int    $decaySeconds
     *
     * @return CRedis_Limiter_DurationLimiter
     */
    protected function durationLimiter($key, $maxAttempts, $decaySeconds) {
        return new CRedis_Limiter_DurationLimiter(
            CRedis::instance()->connection($this->connectionName),
            $key,
            $maxAttempts,
            $decaySeconds
        );
    }

    /**
     * @param string $key
     *
     * @return int
     */
    protected function getTimeUntilNextRetry($key) {
        return ($this->decaysAt[$key] - time()) + 3;
    }

    /**
     * Specify the Redis connection that should be used.
     *
     * @param string $name
     *
     * @return $this
     */
    public function connection($name) {
        $this->connectionName = $name;

        return $this;
    }

    /**
     * @return array
     */
    public function __sleep() {
        return array_merge(parent::__sleep(), ['connectionName']);
    }
}
