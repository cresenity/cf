<?php

/**
 * Job palsu untuk unit test: mencatat delete(), release() dan fail() tanpa antrean sungguhan.
 * Dipasang lewat withFakeQueueInteractions() pada job yang memakai CQueue_Trait_InteractsWithQueue.
 */
class CQueue_Job_FakeJob extends CQueue_AbstractJob {
    /**
     * @var null|int
     */
    public $releaseDelay;

    /**
     * @var null|Throwable|string
     */
    public $failedWith;

    /**
     * @var int
     */
    public $attemptCount = 1;

    /**
     * @var string
     */
    protected $jobId;

    /**
     * @return string
     */
    public function getJobId() {
        if ($this->jobId === null) {
            $this->jobId = (string) cstr::uuid();
        }

        return $this->jobId;
    }

    /**
     * @return string
     */
    public function getRawBody() {
        return '';
    }

    /**
     * @return int
     */
    public function attempts() {
        return $this->attemptCount;
    }

    /**
     * @param int $delay
     *
     * @return void
     */
    public function release($delay = 0) {
        parent::release($delay);

        $this->releaseDelay = $delay;
    }

    /**
     * Tidak memanggil failed() job maupun memancarkan event; hanya mencatat.
     *
     * @param null|Throwable $e
     *
     * @return void
     */
    public function fail($e = null) {
        $this->markAsFailed();

        $this->failedWith = $e;
    }
}
