<?php

defined('SYSPATH') or die('No direct access allowed.');

class CQueue_FailedJob_NullFailedJob extends CQueue_AbstractFailedJob implements CQueue_Contract_CountableFailedJobInterface {
    /**
     * Log a failed job into storage.
     *
     * @param string     $connection
     * @param string     $queue
     * @param string     $payload
     * @param \Exception $exception
     *
     * @return null|int
     */
    public function log($connection, $queue, $payload, $exception) {
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return array
     */
    public function all() {
        return [];
    }

    /**
     * Get a single failed job.
     *
     * @param mixed $id
     *
     * @return null|object
     */
    public function find($id) {
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function forget($id) {
        return true;
    }

    /**
     * Get the IDs of all of the failed jobs.
     *
     * @param null|string $queue
     *
     * @return array
     */
    public function ids($queue = null) {
        return [];
    }

    /**
     * Flush all of the failed jobs from storage.
     *
     * @param null|int $hours
     *
     * @return void
     */
    public function flush($hours = null) {
    }

    /**
     * @param null|string $connection
     * @param null|string $queue
     *
     * @return int
     */
    public function count($connection = null, $queue = null) {
        return 0;
    }
}
