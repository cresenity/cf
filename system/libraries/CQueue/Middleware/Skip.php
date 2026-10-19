<?php

/**
 * Skip the job when a condition holds: CQueue_Middleware_Skip::when($condition).
 */
class CQueue_Middleware_Skip {
    /**
     * @var bool
     */
    protected $skip;

    /**
     * @param bool $skip
     */
    public function __construct($skip = false) {
        $this->skip = (bool) $skip;
    }

    /**
     * @param bool|Closure $condition
     *
     * @return static
     */
    public static function when($condition) {
        return new static(c::value($condition));
    }

    /**
     * @param bool|Closure $condition
     *
     * @return static
     */
    public static function unless($condition) {
        return new static(!c::value($condition));
    }

    /**
     * @param mixed    $job
     * @param callable $next
     *
     * @return mixed
     */
    public function handle($job, $next) {
        if ($this->skip) {
            return false;
        }

        return $next($job);
    }
}
