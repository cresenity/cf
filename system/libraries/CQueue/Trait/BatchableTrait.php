<?php

trait CQueue_Trait_BatchableTrait {
    /**
     * The batch ID (if applicable).
     *
     * @var string
     */
    public $batchId;

    /**
     * Batch palsu dari withFakeBatch(); kosong di luar test dan tidak ikut diserialisasi.
     *
     * @var array
     */
    protected static $fakeBatches = [];

    /**
     * Get the batch instance for the job, if applicable.
     *
     * @return null|\CQueue_Batch
     */
    public function batch() {
        if (count(static::$fakeBatches) > 0 && ($fake = $this->findFakeBatch()) !== null) {
            return $fake;
        }

        if ($this->batchId) {
            return CQueue::batchRepository()->find($this->batchId);
        }
    }

    /**
     * Determine if the batch is still active and processing.
     *
     * @return bool
     */
    public function batching() {
        $batch = $this->batch();

        return $batch && !$batch->cancelled();
    }

    /**
     * Set the batch ID on the job.
     *
     * @param string $batchId
     *
     * @return $this
     */
    public function withBatchId($batchId) {
        $this->batchId = $batchId;

        return $this;
    }

    /**
     * Pasang batch palsu untuk unit test.
     *
     * @param string                    $id
     * @param string                    $name
     * @param int                       $totalJobs
     * @param int                       $pendingJobs
     * @param int                       $failedJobs
     * @param array                     $failedJobIds
     * @param array                     $options
     * @param null|\Carbon\CarbonImmutable $createdAt
     * @param null|\Carbon\CarbonImmutable $cancelledAt
     * @param null|\Carbon\CarbonImmutable $finishedAt
     *
     * @return array [job ini, CTesting_Fake_Queue_BatchFake]
     */
    public function withFakeBatch($id = '', $name = '', $totalJobs = 0, $pendingJobs = 0, $failedJobs = 0, array $failedJobIds = [], array $options = [], $createdAt = null, $cancelledAt = null, $finishedAt = null) {
        $batch = new CTesting_Fake_Queue_BatchFake($id, $name, $totalJobs, $pendingJobs, $failedJobs, $failedJobIds, $options, $createdAt, $cancelledAt, $finishedAt);

        $this->batchId = $batch->id;
        static::$fakeBatches[spl_object_id($this)] = ['job' => WeakReference::create($this), 'batch' => $batch];

        return [$this, $batch];
    }

    /**
     * @return null|CTesting_Fake_Queue_BatchFake
     */
    protected function findFakeBatch() {
        $entry = carr::get(static::$fakeBatches, spl_object_id($this));

        return $entry !== null && $entry['job']->get() === $this ? $entry['batch'] : null;
    }
}
