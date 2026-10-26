<?php

/**
 * Skip a batched job once its batch has been cancelled.
 */
class CQueue_Middleware_SkipIfBatchCancelled {
    /**
     * @param mixed    $job
     * @param callable $next
     *
     * @return void
     */
    public function handle($job, $next) {
        if (method_exists($job, 'batch') && ($batch = $job->batch()) && $batch->cancelled()) {
            return;
        }

        $next($job);
    }
}
