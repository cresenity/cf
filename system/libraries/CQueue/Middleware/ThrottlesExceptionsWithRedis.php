<?php

/**
 * CQueue_Middleware_ThrottlesExceptions counted atomically in Redis.
 */
class CQueue_Middleware_ThrottlesExceptionsWithRedis extends CQueue_Middleware_ThrottlesExceptions {
    /**
     * @var null|string
     */
    protected $connectionName = null;

    /**
     * @var CRedis_Limiter_DurationLimiter
     */
    protected $limiter;

    /**
     * @param mixed    $job
     * @param callable $next
     *
     * @throws Throwable
     *
     * @return mixed
     */
    public function handle($job, $next) {
        $this->limiter = $this->durationLimiter($this->getKey($job));

        if ($this->limiter->tooManyAttempts()) {
            return $job->release($this->limiter->decaysAt - time());
        }

        try {
            $next($job);

            $this->limiter->clear();
        } catch (Throwable $throwable) {
            if ($this->whenCallback && !call_user_func($this->whenCallback, $throwable, $this->limiter)) {
                throw $throwable;
            }

            if ($this->reportCallback && call_user_func($this->reportCallback, $throwable, $this->limiter)) {
                c::report($throwable);
            }

            if ($this->shouldDelete($throwable)) {
                return $job->delete();
            }

            if ($this->shouldFail($throwable)) {
                return $job->fail($throwable);
            }

            $this->limiter->acquire();

            return $job->release($this->getTimeUntilNextRetryAfterException($throwable));
        }
    }

    /**
     * @param string $key
     *
     * @return CRedis_Limiter_DurationLimiter
     */
    protected function durationLimiter($key) {
        return new CRedis_Limiter_DurationLimiter(
            CRedis::instance()->connection($this->connectionName),
            $key,
            $this->maxAttempts,
            $this->decaySeconds
        );
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
}
