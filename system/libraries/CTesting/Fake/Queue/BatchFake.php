<?php

use Carbon\CarbonImmutable;

/**
 * Batch palsu untuk unit test job yang memakai CQueue_Trait_BatchableTrait::withFakeBatch().
 */
class CTesting_Fake_Queue_BatchFake extends CQueue_Batch {
    /**
     * Job yang ditambahkan lewat add().
     *
     * @var array
     */
    public $added = [];

    /**
     * @var bool
     */
    public $deleted = false;

    /**
     * @param string               $id
     * @param string               $name
     * @param int                  $totalJobs
     * @param int                  $pendingJobs
     * @param int                  $failedJobs
     * @param array                $failedJobIds
     * @param array                $options
     * @param null|CarbonImmutable $createdAt
     * @param null|CarbonImmutable $cancelledAt
     * @param null|CarbonImmutable $finishedAt
     */
    public function __construct($id = '', $name = '', $totalJobs = 0, $pendingJobs = 0, $failedJobs = 0, array $failedJobIds = [], array $options = [], CarbonImmutable $createdAt = null, CarbonImmutable $cancelledAt = null, CarbonImmutable $finishedAt = null) {
        $this->id = $id;
        $this->name = $name;
        $this->totalJobs = $totalJobs;
        $this->pendingJobs = $pendingJobs;
        $this->failedJobs = $failedJobs;
        $this->failedJobIds = $failedJobIds;
        $this->options = $options;
        $this->createdAt = $createdAt ?: CarbonImmutable::now();
        $this->cancelledAt = $cancelledAt;
        $this->finishedAt = $finishedAt;
    }

    /**
     * @return $this
     */
    public function fresh() {
        return $this;
    }

    /**
     * @param array|CCollection $jobs
     *
     * @return $this
     */
    public function add($jobs) {
        $jobs = CCollection::wrap($jobs);

        foreach ($jobs as $job) {
            $this->added[] = $job;
        }

        $this->totalJobs += $jobs->count();
        $this->pendingJobs += $jobs->count();

        return $this;
    }

    /**
     * @return void
     */
    public function cancel() {
        $this->cancelledAt = CarbonImmutable::now();
    }

    /**
     * @return void
     */
    public function delete() {
        $this->deleted = true;
    }

    /**
     * @return bool
     */
    public function deleted() {
        return $this->deleted;
    }
}
