<?php

use PHPUnit\Framework\Assert as PHPUnit;

class CTesting_Fake_Queue_QueueManagerFake extends CQueue_Manager implements CQueue_QueueInterface {
    use CTrait_ReflectsClosureTrait;

    /**
     * All of the jobs that have been pushed.
     *
     * @var array
     */
    protected $jobs = [];

    /**
     * Jobs (kelas atau Closure penguji) yang diteruskan ke antrean sungguhan, bukan dicatat.
     *
     * @var array
     */
    protected $jobsToBeQueued = [];

    /**
     * @var bool
     */
    protected $serializeAndRestore = false;

    /**
     * @var null|CQueue_QueueInterface|CQueue_Manager
     */
    protected $realQueue;

    /**
     * Assert if a job was pushed based on a truth-test callback.
     *
     * @param string|\Closure   $job
     * @param null|callable|int $callback
     *
     * @return void
     */
    public function assertPushed($job, $callback = null) {
        if ($job instanceof Closure) {
            list($job, $callback) = [$this->firstClosureParameterType($job), $job];
        }

        if (is_numeric($callback)) {
            return $this->assertPushedTimes($job, $callback);
        }

        PHPUnit::assertTrue(
            $this->pushed($job, $callback)->count() > 0,
            "The expected [{$job}] job was not pushed."
        );
    }

    /**
     * Assert if a job was pushed a number of times.
     *
     * @param string $job
     * @param int    $times
     *
     * @return void
     */
    protected function assertPushedTimes($job, $times = 1) {
        $count = $this->pushed($job)->count();

        PHPUnit::assertSame(
            $times,
            $count,
            "The expected [{$job}] job was pushed {$count} times instead of {$times} times."
        );
    }

    /**
     * Assert if a job was pushed based on a truth-test callback.
     *
     * @param string          $queue
     * @param string|\Closure $job
     * @param null|callable   $callback
     *
     * @return void
     */
    public function assertPushedOn($queue, $job, $callback = null) {
        if ($job instanceof Closure) {
            list($job, $callback) = [$this->firstClosureParameterType($job), $job];
        }

        $this->assertPushed($job, function ($job, $pushedQueue) use ($callback, $queue) {
            if ($pushedQueue !== $queue) {
                return false;
            }

            return $callback ? $callback(...func_get_args()) : true;
        });
    }

    /**
     * Assert if a job was pushed with chained jobs based on a truth-test callback.
     *
     * @param string        $job
     * @param array         $expectedChain
     * @param null|callable $callback
     *
     * @return void
     */
    public function assertPushedWithChain($job, $expectedChain = [], $callback = null) {
        PHPUnit::assertTrue(
            $this->pushed($job, $callback)->isNotEmpty(),
            "The expected [{$job}] job was not pushed."
        );

        PHPUnit::assertTrue(
            c::collect($expectedChain)->isNotEmpty(),
            'The expected chain can not be empty.'
        );

        $this->isChainOfObjects($expectedChain)
                ? $this->assertPushedWithChainOfObjects($job, $expectedChain, $callback)
                : $this->assertPushedWithChainOfClasses($job, $expectedChain, $callback);
    }

    /**
     * Assert if a job was pushed with an empty chain based on a truth-test callback.
     *
     * @param string        $job
     * @param null|callable $callback
     *
     * @return void
     */
    public function assertPushedWithoutChain($job, $callback = null) {
        PHPUnit::assertTrue(
            $this->pushed($job, $callback)->isNotEmpty(),
            "The expected [{$job}] job was not pushed."
        );

        $this->assertPushedWithChainOfClasses($job, [], $callback);
    }

    /**
     * Assert if a job was pushed with chained jobs based on a truth-test callback.
     *
     * @param string        $job
     * @param array         $expectedChain
     * @param null|callable $callback
     *
     * @return void
     */
    protected function assertPushedWithChainOfObjects($job, $expectedChain, $callback) {
        $chain = c::collect($expectedChain)->map(function ($job) {
            return serialize($job);
        })->all();

        PHPUnit::assertTrue(
            $this->pushed($job, $callback)->filter(function ($job) use ($chain) {
                return $job->chained == $chain;
            })->isNotEmpty(),
            'The expected chain was not pushed.'
        );
    }

    /**
     * Assert if a job was pushed with chained jobs based on a truth-test callback.
     *
     * @param string        $job
     * @param array         $expectedChain
     * @param null|callable $callback
     *
     * @return void
     */
    protected function assertPushedWithChainOfClasses($job, $expectedChain, $callback) {
        $matching = $this->pushed($job, $callback)->map->chained->map(function ($chain) {
            return c::collect($chain)->map(function ($job) {
                return get_class(unserialize($job));
            });
        })->filter(function ($chain) use ($expectedChain) {
            return $chain->all() === $expectedChain;
        });

        PHPUnit::assertTrue(
            $matching->isNotEmpty(),
            'The expected chain was not pushed.'
        );
    }

    /**
     * Determine if the given chain is entirely composed of objects.
     *
     * @param array $chain
     *
     * @return bool
     */
    protected function isChainOfObjects($chain) {
        return !c::collect($chain)->contains(function ($job) {
            return !is_object($job);
        });
    }

    /**
     * Determine if a job was pushed based on a truth-test callback.
     *
     * @param string|\Closure $job
     * @param null|callable   $callback
     *
     * @return void
     */
    public function assertNotPushed($job, $callback = null) {
        if ($job instanceof Closure) {
            list($job, $callback) = [$this->firstClosureParameterType($job), $job];
        }

        PHPUnit::assertCount(
            0,
            $this->pushed($job, $callback),
            "The unexpected [{$job}] job was pushed."
        );
    }

    /**
     * Assert that no jobs were pushed.
     *
     * @return void
     */
    public function assertNothingPushed() {
        PHPUnit::assertEmpty($this->jobs, 'Jobs were pushed unexpectedly.');
    }

    /**
     * Get all of the jobs matching a truth-test callback.
     *
     * @param string        $job
     * @param null|callable $callback
     *
     * @return \CCollection
     */
    public function pushed($job, $callback = null) {
        if (!$this->hasPushed($job)) {
            return c::collect();
        }

        $callback = $callback ?: function () {
            return true;
        };

        return c::collect($this->jobs[$job])->filter(function ($data) use ($callback) {
            return $callback($data['job'], $data['queue']);
        })->pluck('job');
    }

    /**
     * Determine if there are any stored jobs for a given class.
     *
     * @param string $job
     *
     * @return bool
     */
    public function hasPushed($job) {
        return isset($this->jobs[$job]) && !empty($this->jobs[$job]);
    }

    /**
     * Resolve a queue connection instance.
     *
     * @param mixed $value
     *
     * @return \CQueue_QueueInterface
     */
    public function connection($value = null) {
        return $this;
    }

    /**
     * Get the size of the queue.
     *
     * @param null|string $queue
     *
     * @return int
     */
    public function size($queue = null) {
        return c::collect($this->jobs)->flatten(1)->filter(function ($job) use ($queue) {
            return $job['queue'] === $queue;
        })->count();
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string      $job
     * @param mixed       $data
     * @param null|string $queue
     *
     * @return mixed
     */
    public function push($job, $data = '', $queue = null) {
        if ($this->shouldPassThrough($job)) {
            return $this->realQueue()->push($job, $data, $queue);
        }

        if ($job instanceof Closure) {
            $job = CQueue_CallQueuedClosure::create($job);
        }

        $this->jobs[is_object($job) ? get_class($job) : $job][] = [
            'job' => $this->serializeAndRestore ? $this->serializeAndRestoreJob($job) : $job,
            'queue' => $queue,
        ];
    }

    /**
     * Teruskan job tertentu ke antrean sungguhan (kelas, atau Closure yang menerima job); sisanya tetap dicatat.
     *
     * @param array|Closure|string $jobsToBeQueued
     *
     * @return $this
     */
    public function except($jobsToBeQueued) {
        $this->jobsToBeQueued = array_merge($this->jobsToBeQueued, carr::wrap($jobsToBeQueued));

        return $this;
    }

    /**
     * Antrean sungguhan untuk job pada except(); bawaan CQueue::queuer().
     *
     * @param CQueue_QueueInterface|CQueue_Manager $queue
     *
     * @return $this
     */
    public function passThroughTo($queue) {
        $this->realQueue = $queue;

        return $this;
    }

    /**
     * Catat salinan hasil serialize/unserialize, seperti yang akan dilihat worker.
     *
     * @param bool $serializeAndRestore
     *
     * @return $this
     */
    public function serializeAndRestore($serializeAndRestore = true) {
        $this->serializeAndRestore = $serializeAndRestore;

        return $this;
    }

    /**
     * @param mixed $job
     *
     * @return mixed
     */
    protected function serializeAndRestoreJob($job) {
        return is_object($job) ? unserialize(serialize($job)) : $job;
    }

    /**
     * @param mixed $job
     *
     * @return bool
     */
    protected function shouldPassThrough($job) {
        foreach ($this->jobsToBeQueued as $candidate) {
            if ($candidate instanceof Closure ? $candidate($job) : (is_object($job) && $candidate === get_class($job))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return CQueue_QueueInterface|CQueue_Manager
     */
    protected function realQueue() {
        return $this->realQueue ?: CQueue::queuer();
    }

    /**
     * @param int $expectedCount
     *
     * @return void
     */
    public function assertCount($expectedCount) {
        $actual = c::collect($this->jobs)->flatten(1)->count();

        PHPUnit::assertSame(
            $expectedCount,
            $actual,
            "Expected {$expectedCount} jobs to be pushed, but found {$actual} instead."
        );
    }

    /**
     * @param string|Closure $job
     * @param null|callable  $callback
     *
     * @return void
     */
    public function assertPushedOnce($job, $callback = null) {
        if ($job instanceof Closure) {
            list($job, $callback) = [$this->firstClosureParameterType($job), $job];
        }

        $count = $this->pushed($job, $callback)->count();

        PHPUnit::assertSame(1, $count, "The expected [{$job}] job was pushed {$count} times instead of 1 times.");
    }

    /**
     * @param null|callable $callback
     *
     * @return void
     */
    public function assertClosurePushed($callback = null) {
        $this->assertPushed(CQueue_CallQueuedClosure::class, $callback);
    }

    /**
     * @param null|callable $callback
     *
     * @return void
     */
    public function assertClosureNotPushed($callback = null) {
        $this->assertNotPushed(CQueue_CallQueuedClosure::class, $callback);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string      $payload
     * @param null|string $queue
     * @param array       $options
     *
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = []) {
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string                               $job
     * @param mixed                                $data
     * @param null|string                          $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null) {
        return $this->push($job, $data, $queue);
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string $queue
     * @param string $job
     * @param mixed  $data
     *
     * @return mixed
     */
    public function pushOn($queue, $job, $data = '') {
        return $this->push($job, $data, $queue);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param string                               $queue
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string                               $job
     * @param mixed                                $data
     *
     * @return mixed
     */
    public function laterOn($queue, $delay, $job, $data = '') {
        return $this->push($job, $data, $queue);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param null|string $queue
     *
     * @return null|\CQueue_JobInterface
     */
    public function pop($queue = null) {
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param array       $jobs
     * @param mixed       $data
     * @param null|string $queue
     *
     * @return mixed
     */
    public function bulk($jobs, $data = '', $queue = null) {
        foreach ($jobs as $job) {
            $this->push($job, $data, $queue);
        }
    }

    /**
     * Get the jobs that have been pushed.
     *
     * @return array
     */
    public function pushedJobs() {
        return $this->jobs;
    }

    /**
     * Get the connection name for the queue.
     *
     * @return string
     */
    public function getConnectionName() {
    }

    /**
     * Set the connection name for the queue.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setConnectionName($name) {
        return $this;
    }

    /**
     * Override the QueueManager to prevent circular dependency.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        throw new BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()',
            static::class,
            $method
        ));
    }
}
