<?php

/**
 * After maxAttempts exceptions within decaySeconds, release the job without running it
 * until the window passes (circuit breaker for jobs calling a flaky third party).
 */
class CQueue_Middleware_ThrottlesExceptions {
    /**
     * The developer specified key that the rate limiter should use.
     *
     * @var string
     */
    protected $key;

    /**
     * Indicates whether the throttle key should use the job's UUID.
     *
     * @var bool
     */
    protected $byJob = false;

    /**
     * The maximum number of attempts allowed before rate limiting applies.
     *
     * @var int
     */
    protected $maxAttempts;

    /**
     * The number of seconds until the maximum attempts are reset.
     *
     * @var int
     */
    protected $decaySeconds;

    /**
     * The number of minutes to wait before retrying the job after an exception.
     *
     * @var Closure|int
     */
    protected $retryAfterMinutes = 0;

    /**
     * The callback that determines if the exception should be reported.
     *
     * @var callable
     */
    protected $reportCallback;

    /**
     * The callback that determines if rate limiting should apply.
     *
     * @var callable
     */
    protected $whenCallback;

    /**
     * @var callable[]
     */
    protected $deleteWhenCallbacks = [];

    /**
     * @var callable[]
     */
    protected $failWhenCallbacks = [];

    /**
     * The prefix of the rate limiter key.
     *
     * @var string
     */
    protected $prefix = 'cresenity_throttles_exceptions:';

    /**
     * The rate limiter instance.
     *
     * @var CCache_RateLimiter
     */
    protected $limiter;

    /**
     * @param int $maxAttempts
     * @param int $decaySeconds
     */
    public function __construct($maxAttempts = 10, $decaySeconds = 600) {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }

    /**
     * @param mixed    $job
     * @param callable $next
     *
     * @throws Throwable
     *
     * @return mixed
     */
    public function handle($job, $next) {
        $this->limiter = CCache::rateLimiter();

        if ($this->limiter->tooManyAttempts($jobKey = $this->getKey($job), $this->maxAttempts)) {
            return $job->release($this->getTimeUntilNextRetry($jobKey));
        }

        try {
            $next($job);

            $this->limiter->clear($jobKey);
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

            $this->limiter->hit($jobKey, $this->decaySeconds);

            return $job->release($this->getTimeUntilNextRetryAfterException($throwable));
        }
    }

    /**
     * Specify a callback that should determine if rate limiting behavior should apply.
     *
     * @return $this
     */
    public function when(callable $callback) {
        $this->whenCallback = $callback;

        return $this;
    }

    /**
     * Delete the job instead of releasing it when the callback returns true (or the exception is of the given class).
     *
     * @param callable|string $callback
     *
     * @return $this
     */
    public function deleteWhen($callback) {
        $this->deleteWhenCallbacks[] = $this->exceptionMatcher($callback);

        return $this;
    }

    /**
     * Fail the job instead of releasing it when the callback returns true (or the exception is of the given class).
     *
     * @param callable|string $callback
     *
     * @return $this
     */
    public function failWhen($callback) {
        $this->failWhenCallbacks[] = $this->exceptionMatcher($callback);

        return $this;
    }

    /**
     * @param callable|string $callback
     *
     * @return callable
     */
    protected function exceptionMatcher($callback) {
        if (!is_string($callback)) {
            return $callback;
        }

        return function (Throwable $e) use ($callback) {
            return $e instanceof $callback;
        };
    }

    /**
     * @return bool
     */
    protected function shouldDelete(Throwable $throwable) {
        return $this->anyCallbackMatches($this->deleteWhenCallbacks, $throwable);
    }

    /**
     * @return bool
     */
    protected function shouldFail(Throwable $throwable) {
        return $this->anyCallbackMatches($this->failWhenCallbacks, $throwable);
    }

    /**
     * @return bool
     */
    protected function anyCallbackMatches(array $callbacks, Throwable $throwable) {
        foreach ($callbacks as $callback) {
            if (call_user_func($callback, $throwable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the prefix of the rate limiter key.
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function withPrefix($prefix) {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Specify the number of minutes a job should be delayed when it is released (before it has reached its max exceptions).
     *
     * @param Closure|int $backoff
     *
     * @return $this
     */
    public function backoff($backoff) {
        $this->retryAfterMinutes = $backoff;

        return $this;
    }

    /**
     * @return int
     */
    protected function getTimeUntilNextRetryAfterException(Throwable $throwable) {
        $backoff = $this->retryAfterMinutes instanceof Closure
            ? call_user_func($this->retryAfterMinutes, $throwable)
            : $this->retryAfterMinutes;

        return $backoff * 60;
    }

    /**
     * Get the cache key associated for the rate limiter.
     *
     * @param mixed $job
     *
     * @return string
     */
    protected function getKey($job) {
        if ($this->key) {
            return $this->prefix . $this->key;
        } elseif ($this->byJob) {
            return $this->prefix . $job->job->uuid();
        }

        $jobName = method_exists($job, 'displayName')
            ? $job->displayName()
            : get_class($job);

        return $this->prefix . md5($jobName);
    }

    /**
     * Set the value that the rate limiter should be keyed by.
     *
     * @param string $key
     *
     * @return $this
     */
    public function by($key) {
        $this->key = $key;

        return $this;
    }

    /**
     * Indicate that the throttle key should use the job's UUID.
     *
     * @return $this
     */
    public function byJob() {
        $this->byJob = true;

        return $this;
    }

    /**
     * Report exceptions and optionally specify a callback that determines if the exception should be reported.
     *
     * @return $this
     */
    public function report(?callable $callback = null) {
        $this->reportCallback = $callback ?: function () {
            return true;
        };

        return $this;
    }

    /**
     * Get the number of seconds that should elapse before the job is retried.
     *
     * @param string $key
     *
     * @return int
     */
    protected function getTimeUntilNextRetry($key) {
        return $this->limiter->availableIn($key) + 3;
    }
}
