<?php

defined('SYSPATH') or die('No direct access allowed.');

trait CQueue_Trait_InteractsWithQueue {
    /**
     * The underlying queue job instance.
     *
     * @var CQueue_AbstractJob
     */
    public $job;

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts() {
        return $this->job ? $this->job->attempts() : 1;
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete() {
        if ($this->job) {
            return $this->job->delete();
        }
    }

    /**
     * Fail the job from the queue.
     *
     * @param null|\Throwable $exception
     *
     * @return void
     */
    public function fail($exception = null) {
        if ($this->job) {
            $this->job->fail($exception);
        }
    }

    /**
     * Release the job back into the queue.
     *
     * @param int $delay
     *
     * @return void
     */
    public function release($delay = 0) {
        if ($this->job) {
            return $this->job->release($delay);
        }
    }

    /**
     * Set the base queue job instance.
     *
     * @param CQueue_AbstractJob $job
     *
     * @return $this
     */
    public function setJob(CQueue_AbstractJob $job) {
        $this->job = $job;

        return $this;
    }

    /**
     * Pasang job palsu supaya delete()/release()/fail() tercatat tanpa antrean; untuk unit test.
     *
     * @return $this
     */
    public function withFakeQueueInteractions() {
        $this->setJob(new CQueue_Job_FakeJob());

        return $this;
    }

    /**
     * @return $this
     */
    public function assertDeleted() {
        \PHPUnit\Framework\Assert::assertTrue($this->fakeJob()->isDeleted(), 'Job was not deleted.');

        return $this;
    }

    /**
     * @return $this
     */
    public function assertNotDeleted() {
        \PHPUnit\Framework\Assert::assertTrue(!$this->fakeJob()->isDeleted(), 'Job was deleted unexpectedly.');

        return $this;
    }

    /**
     * @param null|int $delay detik yang diharapkan, null tidak diperiksa
     *
     * @return $this
     */
    public function assertReleased($delay = null) {
        $job = $this->fakeJob();
        \PHPUnit\Framework\Assert::assertTrue($job->isReleased(), 'Job was not released.');

        if ($delay !== null) {
            \PHPUnit\Framework\Assert::assertSame(
                $delay,
                $job->releaseDelay,
                "Expected job to be released with delay of [{$delay}] seconds, but was released with delay of [{$job->releaseDelay}] seconds."
            );
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function assertNotReleased() {
        \PHPUnit\Framework\Assert::assertTrue(!$this->fakeJob()->isReleased(), 'Job was released unexpectedly.');

        return $this;
    }

    /**
     * @return $this
     */
    public function assertFailed() {
        \PHPUnit\Framework\Assert::assertTrue($this->fakeJob()->hasFailed(), 'Job was not failed.');

        return $this;
    }

    /**
     * @param string|Throwable $exception kelas exception, atau instance (kelas dan pesan dibandingkan)
     *
     * @return $this
     */
    public function assertFailedWith($exception) {
        $job = $this->fakeJob();
        $this->assertFailed();

        if (is_string($exception)) {
            \PHPUnit\Framework\Assert::assertInstanceOf($exception, $job->failedWith);

            return $this;
        }

        \PHPUnit\Framework\Assert::assertInstanceOf(get_class($exception), $job->failedWith);
        \PHPUnit\Framework\Assert::assertSame($exception->getMessage(), $job->failedWith->getMessage());

        return $this;
    }

    /**
     * @return $this
     */
    public function assertNotFailed() {
        \PHPUnit\Framework\Assert::assertTrue(!$this->fakeJob()->hasFailed(), 'Job was failed unexpectedly.');

        return $this;
    }

    /**
     * @return CQueue_Job_FakeJob
     */
    protected function fakeJob() {
        if (!$this->job instanceof CQueue_Job_FakeJob) {
            throw new LogicException('Panggil withFakeQueueInteractions() sebelum memeriksa interaksi job.');
        }

        return $this->job;
    }
}
