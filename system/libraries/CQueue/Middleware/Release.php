<?php

/**
 * Release the job back onto the queue when a condition holds: CQueue_Middleware_Release::when($condition, $seconds).
 */
class CQueue_Middleware_Release {
    /**
     * @var bool
     */
    protected $release;

    /**
     * @var int
     */
    protected $releaseAfter;

    /**
     * @param bool $release
     * @param int  $releaseAfter
     */
    public function __construct($release = false, $releaseAfter = 0) {
        $this->release = (bool) $release;
        $this->releaseAfter = (int) $releaseAfter;
    }

    /**
     * @param bool|Closure $condition
     * @param int          $releaseAfter
     *
     * @return static
     */
    public static function when($condition, $releaseAfter = 0) {
        return new static(c::value($condition), $releaseAfter);
    }

    /**
     * @param bool|Closure $condition
     * @param int          $releaseAfter
     *
     * @return static
     */
    public static function unless($condition, $releaseAfter = 0) {
        return new static(!c::value($condition), $releaseAfter);
    }

    /**
     * @param mixed    $job
     * @param callable $next
     *
     * @return mixed
     */
    public function handle($job, $next) {
        if ($this->release) {
            return $job->release($this->releaseAfter);
        }

        return $next($job);
    }
}
