<?php

/**
 * Fail the job at once (no retry) when it throws one of the given exceptions.
 */
class CQueue_Middleware_FailOnException {
    /**
     * @var callable
     */
    protected $callback;

    /**
     * @param array|callable $callback exception classes, or a callback(Throwable $e, $job): bool
     */
    public function __construct($callback) {
        if (is_array($callback)) {
            $callback = $this->failForExceptions($callback);
        }

        $this->callback = $callback;
    }

    /**
     * @return Closure
     */
    protected function failForExceptions(array $exceptions) {
        return static function (Throwable $throwable) use ($exceptions) {
            foreach ($exceptions as $exception) {
                if ($throwable instanceof $exception) {
                    return true;
                }
            }

            return false;
        };
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
        try {
            return $next($job);
        } catch (Throwable $e) {
            if (call_user_func($this->callback, $e, $job) === true) {
                $job->fail($e);
            }

            throw $e;
        }
    }
}
