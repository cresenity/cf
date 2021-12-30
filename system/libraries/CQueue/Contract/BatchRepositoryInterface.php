<?php

interface CQueue_Contract_BatchRepositoryInterface {
    /**
     * Retrieve a list of batches.
     *
     * @param int   $limit
     * @param mixed $before
     *
     * @return \CQueue_Batch[]
     */
    public function get($limit, $before);

    /**
     * Retrieve information about an existing batch.
     *
     * @param string $batchId
     *
     * @return null|\CQueue_Batch
     */
    public function find($batchId);

    /**
     * Store a new pending batch.
     *
     * @param \CQueue_PendingBatch $batch
     *
     * @return \CQueue_Batch
     */
    public function store(CQueue_PendingBatch $batch);

    /**
     * Increment the total number of jobs within the batch.
     *
     * @param string $batchId
     * @param int    $amount
     *
     * @return void
     */
    public function incrementTotalJobs($batchId, $amount);

    /**
     * Decrement the total number of pending jobs for the batch.
     *
     * @param string $batchId
     * @param string $jobId
     *
     * @return \CQueue_UpdatedBatchJobCounts
     */
    public function decrementPendingJobs($batchId, $jobId);

    /**
     * Increment the total number of failed jobs for the batch.
     *
     * @param string $batchId
     * @param string $jobId
     *
     * @return \CQueue_UpdatedBatchJobCounts
     */
    public function incrementFailedJobs($batchId, $jobId);

    /**
     * Mark the batch that has the given ID as finished.
     *
     * @param string $batchId
     *
     * @return void
     */
    public function markAsFinished($batchId);

    /**
     * Cancel the batch that has the given ID.
     *
     * @param string $batchId
     *
     * @return void
     */
    public function cancel($batchId);

    /**
     * Delete the batch that has the given ID.
     *
     * @param string $batchId
     *
     * @return void
     */
    public function delete($batchId);

    /**
     * Execute the given Closure within a storage specific transaction.
     *
     * @param \Closure $callback
     *
     * @return mixed
     */
    public function transaction($callback);
}
