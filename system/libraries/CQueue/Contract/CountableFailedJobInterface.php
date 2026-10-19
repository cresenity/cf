<?php
interface CQueue_Contract_CountableFailedJobInterface {
    /**
     * Count the failed jobs.
     *
     * @param null|string $connection
     * @param null|string $queue
     *
     * @return int
     */
    public function count($connection = null, $queue = null);
}
