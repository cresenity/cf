<?php

use PHPUnit\Framework\Assert as PHPUnit;

class CTesting_Fake_Base_BusFake implements CQueue_QueueingDispatcherInterface {
    use CTrait_ReflectsClosureTrait;

    /**
     * The original Bus dispatcher implementation.
     *
     * @var \CQueue_Dispatcher
     */
    protected $dispatcher;

    /**
     * The job types that should be intercepted instead of dispatched.
     *
     * @var array
     */
    protected $jobsToFake;

    /**
     * Command (kelas atau Closure penguji) yang tetap dikirim ke dispatcher asli.
     *
     * @var array
     */
    protected $jobsToDispatch = [];

    /**
     * @var bool
     */
    protected $serializeAndRestore = false;

    /**
     * The commands that have been dispatched.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * The commands that have been dispatched synchronously.
     *
     * @var array
     */
    protected $commandsSync = [];

    /**
     * The commands that have been dispatched after the response has been sent.
     *
     * @var array
     */
    protected $commandsAfterResponse = [];

    /**
     * The batches that have been dispatched.
     *
     * @var array
     */
    protected $batches = [];

    /**
     * Create a new bus fake instance.
     *
     * @param \CQueue_QueueingDispatcherInterface $dispatcher
     * @param array|string                        $jobsToFake
     *
     * @return void
     */
    public function __construct(CQueue_QueueingDispatcherInterface $dispatcher, $jobsToFake = []) {
        $this->dispatcher = $dispatcher;

        $this->jobsToFake = carr::wrap($jobsToFake);
    }

    /**
     * Assert if a job was dispatched based on a truth-test callback.
     *
     * @param string|\Closure   $command
     * @param null|callable|int $callback
     *
     * @return void
     */
    public function assertDispatched($command, $callback = null) {
        if ($command instanceof Closure) {
            list($command, $callback) = [$this->firstClosureParameterType($command), $command];
        }

        if (is_numeric($callback)) {
            return $this->assertDispatchedTimes($command, $callback);
        }

        PHPUnit::assertTrue(
            $this->dispatched($command, $callback)->count() > 0
            || $this->dispatchedAfterResponse($command, $callback)->count() > 0
            || $this->dispatchedSync($command, $callback)->count() > 0,
            "The expected [{$command}] job was not dispatched."
        );
    }

    /**
     * Assert if a job was pushed a number of times.
     *
     * @param string $command
     * @param int    $times
     *
     * @return void
     */
    public function assertDispatchedTimes($command, $times = 1) {
        $count = $this->dispatched($command)->count()
                 + $this->dispatchedAfterResponse($command)->count()
                 + $this->dispatchedSync($command)->count();

        PHPUnit::assertSame(
            $times,
            $count,
            "The expected [{$command}] job was pushed {$count} times instead of {$times} times."
        );
    }

    /**
     * Determine if a job was dispatched based on a truth-test callback.
     *
     * @param string|\Closure $command
     * @param null|callable   $callback
     *
     * @return void
     */
    public function assertNotDispatched($command, $callback = null) {
        if ($command instanceof Closure) {
            list($command, $callback) = [$this->firstClosureParameterType($command), $command];
        }

        PHPUnit::assertTrue(
            $this->dispatched($command, $callback)->count() === 0
            && $this->dispatchedAfterResponse($command, $callback)->count() === 0
            && $this->dispatchedSync($command, $callback)->count() === 0,
            "The unexpected [{$command}] job was dispatched."
        );
    }

    /**
     * Assert that no jobs were dispatched.
     *
     * @return void
     */
    public function assertNothingDispatched() {
        PHPUnit::assertEmpty($this->commands, 'Jobs were dispatched unexpectedly.');
    }

    /**
     * Assert if a job was explicitly dispatched synchronously based on a truth-test callback.
     *
     * @param string|\Closure   $command
     * @param null|callable|int $callback
     *
     * @return void
     */
    public function assertDispatchedSync($command, $callback = null) {
        if ($command instanceof Closure) {
            list($command, $callback) = [$this->firstClosureParameterType($command), $command];
        }

        if (is_numeric($callback)) {
            return $this->assertDispatchedSyncTimes($command, $callback);
        }

        PHPUnit::assertTrue(
            $this->dispatchedSync($command, $callback)->count() > 0,
            "The expected [{$command}] job was not dispatched synchronously."
        );
    }

    /**
     * Assert if a job was pushed synchronously a number of times.
     *
     * @param string $command
     * @param int    $times
     *
     * @return void
     */
    public function assertDispatchedSyncTimes($command, $times = 1) {
        $count = $this->dispatchedSync($command)->count();

        PHPUnit::assertSame(
            $times,
            $count,
            "The expected [{$command}] job was synchronously pushed {$count} times instead of {$times} times."
        );
    }

    /**
     * Determine if a job was dispatched based on a truth-test callback.
     *
     * @param string|\Closure $command
     * @param null|callable   $callback
     *
     * @return void
     */
    public function assertNotDispatchedSync($command, $callback = null) {
        if ($command instanceof Closure) {
            list($command, $callback) = [$this->firstClosureParameterType($command), $command];
        }

        PHPUnit::assertCount(
            0,
            $this->dispatchedSync($command, $callback),
            "The unexpected [{$command}] job was dispatched synchronously."
        );
    }

    /**
     * Assert if a job was dispatched after the response was sent based on a truth-test callback.
     *
     * @param string|\Closure   $command
     * @param null|callable|int $callback
     *
     * @return void
     */
    public function assertDispatchedAfterResponse($command, $callback = null) {
        if ($command instanceof Closure) {
            list($command, $callback) = [$this->firstClosureParameterType($command), $command];
        }

        if (is_numeric($callback)) {
            return $this->assertDispatchedAfterResponseTimes($command, $callback);
        }

        PHPUnit::assertTrue(
            $this->dispatchedAfterResponse($command, $callback)->count() > 0,
            "The expected [{$command}] job was not dispatched for after sending the response."
        );
    }

    /**
     * Assert if a job was pushed after the response was sent a number of times.
     *
     * @param string $command
     * @param int    $times
     *
     * @return void
     */
    public function assertDispatchedAfterResponseTimes($command, $times = 1) {
        $count = $this->dispatchedAfterResponse($command)->count();

        PHPUnit::assertSame(
            $times,
            $count,
            "The expected [{$command}] job was pushed {$count} times instead of {$times} times."
        );
    }

    /**
     * Determine if a job was dispatched based on a truth-test callback.
     *
     * @param string|\Closure $command
     * @param null|callable   $callback
     *
     * @return void
     */
    public function assertNotDispatchedAfterResponse($command, $callback = null) {
        if ($command instanceof Closure) {
            list($command, $callback) = [$this->firstClosureParameterType($command), $command];
        }

        PHPUnit::assertCount(
            0,
            $this->dispatchedAfterResponse($command, $callback),
            "The unexpected [{$command}] job was dispatched for after sending the response."
        );
    }

    /**
     * Assert if a chain of jobs was dispatched.
     *
     * @param array $expectedChain
     *
     * @return void
     */
    public function assertChained(array $expectedChain) {
        $command = $expectedChain[0];

        $expectedChain = array_slice($expectedChain, 1);

        $callback = null;

        if ($command instanceof Closure) {
            list($command, $callback) = [$this->firstClosureParameterType($command), $command];
        } elseif (!is_string($command)) {
            $instance = $command;

            $command = get_class($instance);

            $callback = function ($job) use ($instance) {
                return serialize($this->resetChainPropertiesToDefaults($job)) === serialize($instance);
            };
        }

        PHPUnit::assertTrue(
            $this->dispatched($command, $callback)->isNotEmpty(),
            "The expected [{$command}] job was not dispatched."
        );

        PHPUnit::assertTrue(
            c::collect($expectedChain)->isNotEmpty(),
            'The expected chain can not be empty.'
        );

        $this->isChainOfObjects($expectedChain)
            ? $this->assertDispatchedWithChainOfObjects($command, $expectedChain, $callback)
            : $this->assertDispatchedWithChainOfClasses($command, $expectedChain, $callback);
    }

    /**
     * Reset the chain properties to their default values on the job.
     *
     * @param mixed $job
     *
     * @return mixed
     */
    protected function resetChainPropertiesToDefaults($job) {
        return c::tap(clone $job, function ($job) {
            $job->chainConnection = null;
            $job->chainQueue = null;
            $job->chainCatchCallbacks = null;
            $job->chained = [];
        });
    }

    /**
     * Assert if a job was dispatched with an empty chain based on a truth-test callback.
     *
     * @param string|\Closure $command
     * @param null|callable   $callback
     *
     * @return void
     */
    public function assertDispatchedWithoutChain($command, $callback = null) {
        if ($command instanceof Closure) {
            list($command, $callback) = [$this->firstClosureParameterType($command), $command];
        }

        PHPUnit::assertTrue(
            $this->dispatched($command, $callback)->isNotEmpty(),
            "The expected [{$command}] job was not dispatched."
        );

        $this->assertDispatchedWithChainOfClasses($command, [], $callback);
    }

    /**
     * Assert if a job was dispatched with chained jobs based on a truth-test callback.
     *
     * @param string        $command
     * @param array         $expectedChain
     * @param null|callable $callback
     *
     * @return void
     */
    protected function assertDispatchedWithChainOfObjects($command, $expectedChain, $callback) {
        $chain = c::collect($expectedChain)->map(function ($job) {
            return serialize($job);
        })->all();

        PHPUnit::assertTrue(
            $this->dispatched($command, $callback)->filter(function ($job) use ($chain) {
                return $job->chained == $chain;
            })->isNotEmpty(),
            'The expected chain was not dispatched.'
        );
    }

    /**
     * Assert if a job was dispatched with chained jobs based on a truth-test callback.
     *
     * @param string        $command
     * @param array         $expectedChain
     * @param null|callable $callback
     *
     * @return void
     */
    protected function assertDispatchedWithChainOfClasses($command, $expectedChain, $callback) {
        $matching = $this->dispatched($command, $callback)->map->chained->map(function ($chain) {
            return c::collect($chain)->map(function ($job) {
                return get_class(unserialize($job));
            });
        })->filter(function ($chain) use ($expectedChain) {
            return $chain->all() === $expectedChain;
        });

        PHPUnit::assertTrue(
            $matching->isNotEmpty(),
            'The expected chain was not dispatched.'
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
     * Assert if a batch was dispatched based on a truth-test callback.
     *
     * @param callable $callback
     *
     * @return void
     */
    public function assertBatched(callable $callback) {
        PHPUnit::assertTrue(
            $this->batched($callback)->count() > 0,
            'The expected batch was not dispatched.'
        );
    }

    /**
     * Get all of the jobs matching a truth-test callback.
     *
     * @param string        $command
     * @param null|callable $callback
     *
     * @return \CCollection
     */
    public function dispatched($command, $callback = null) {
        if (!$this->hasDispatched($command)) {
            return c::collect();
        }

        $callback = $callback ?: function () {
            return true;
        };

        return c::collect($this->commands[$command])->filter(function ($command) use ($callback) {
            return $callback($command);
        });
    }

    /**
     * Get all of the jobs dispatched synchronously matching a truth-test callback.
     *
     * @param string        $command
     * @param null|callable $callback
     *
     * @return \CCollection
     */
    public function dispatchedSync(string $command, $callback = null) {
        if (!$this->hasDispatchedSync($command)) {
            return c::collect();
        }

        $callback = $callback ?: function () {
            return true;
        };

        return c::collect($this->commandsSync[$command])->filter(function ($command) use ($callback) {
            return $callback($command);
        });
    }

    /**
     * Get all of the jobs dispatched after the response was sent matching a truth-test callback.
     *
     * @param string        $command
     * @param null|callable $callback
     *
     * @return \CCollection
     */
    public function dispatchedAfterResponse(string $command, $callback = null) {
        if (!$this->hasDispatchedAfterResponse($command)) {
            return c::collect();
        }

        $callback = $callback ?: function () {
            return true;
        };

        return c::collect($this->commandsAfterResponse[$command])->filter(function ($command) use ($callback) {
            return $callback($command);
        });
    }

    /**
     * Get all of the pending batches matching a truth-test callback.
     *
     * @param callable $callback
     *
     * @return \CCollection
     */
    public function batched(callable $callback) {
        if (empty($this->batches)) {
            return c::collect();
        }

        return c::collect($this->batches)->filter(function ($batch) use ($callback) {
            return $callback($batch);
        });
    }

    /**
     * Determine if there are any stored commands for a given class.
     *
     * @param string $command
     *
     * @return bool
     */
    public function hasDispatched($command) {
        return isset($this->commands[$command]) && !empty($this->commands[$command]);
    }

    /**
     * Determine if there are any stored commands for a given class.
     *
     * @param string $command
     *
     * @return bool
     */
    public function hasDispatchedSync($command) {
        return isset($this->commandsSync[$command]) && !empty($this->commandsSync[$command]);
    }

    /**
     * Determine if there are any stored commands for a given class.
     *
     * @param string $command
     *
     * @return bool
     */
    public function hasDispatchedAfterResponse($command) {
        return isset($this->commandsAfterResponse[$command]) && !empty($this->commandsAfterResponse[$command]);
    }

    /**
     * Dispatch a command to its appropriate handler.
     *
     * @param mixed $command
     *
     * @return mixed
     */
    public function dispatch($command) {
        if ($this->shouldFakeJob($command)) {
            $this->commands[get_class($command)][] = $this->serializeAndRestoreCommand($command);
        } else {
            return $this->dispatcher->dispatch($command);
        }
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * Queueable jobs will be dispatched to the "sync" queue.
     *
     * @param mixed $command
     * @param mixed $handler
     *
     * @return mixed
     */
    public function dispatchSync($command, $handler = null) {
        if ($this->shouldFakeJob($command)) {
            $this->commandsSync[get_class($command)][] = $this->serializeAndRestoreCommand($command);
        } else {
            return $this->dispatcher->dispatchSync($command, $handler);
        }
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @param mixed $command
     * @param mixed $handler
     *
     * @return mixed
     */
    public function dispatchNow($command, $handler = null) {
        if ($this->shouldFakeJob($command)) {
            $this->commands[get_class($command)][] = $this->serializeAndRestoreCommand($command);
        } else {
            return $this->dispatcher->dispatchNow($command, $handler);
        }
    }

    /**
     * Dispatch a command to its appropriate handler behind a queue.
     *
     * @param mixed $command
     *
     * @return mixed
     */
    public function dispatchToQueue($command) {
        if ($this->shouldFakeJob($command)) {
            $this->commands[get_class($command)][] = $this->serializeAndRestoreCommand($command);
        } else {
            return $this->dispatcher->dispatchToQueue($command);
        }
    }

    /**
     * Dispatch a command to its appropriate handler.
     *
     * @param mixed $command
     *
     * @return mixed
     */
    public function dispatchAfterResponse($command) {
        if ($this->shouldFakeJob($command)) {
            $this->commandsAfterResponse[get_class($command)][] = $this->serializeAndRestoreCommand($command);
        } else {
            return $this->dispatcher->dispatch($command);
        }
    }

    /**
     * Create a new chain of queueable jobs.
     *
     * @param \CCollection|array $jobs
     *
     * @return \CQueue_PendingChain
     */
    public function chain($jobs) {
        $jobs = CCollection::wrap($jobs);

        return new CTesting_Fake_Queue_PendingChainFake($this, $jobs->shift(), $jobs->toArray());
    }

    /**
     * Attempt to find the batch with the given ID.
     *
     * @param string $batchId
     *
     * @return null|\CQueue_Batch
     */
    public function findBatch(string $batchId) {
    }

    /**
     * Create a new batch of queueable jobs.
     *
     * @param \CCollection|array $jobs
     *
     * @return \CQueue_PendingBatch
     */
    public function batch($jobs) {
        return new CTesting_Fake_Queue_PendingBatchFake($this, CCollection::wrap($jobs));
    }

    /**
     * Record the fake pending batch dispatch.
     *
     * @param \CQueue_PendingBatch $pendingBatch
     *
     * @return \CQueue_Batch
     */
    public function recordPendingBatch(CQueue_PendingBatch $pendingBatch) {
        $this->batches[] = $pendingBatch;

        return (new CTesting_Fake_Queue_BatchRepositoryFake())->store($pendingBatch);
    }

    /**
     * Determine if a command should be faked or actually dispatched.
     *
     * @param mixed $command
     *
     * @return bool
     */
    protected function shouldFakeJob($command) {
        if ($this->shouldDispatchCommand($command)) {
            return false;
        }

        if (empty($this->jobsToFake)) {
            return true;
        }

        return c::collect($this->jobsToFake)
            ->filter(function ($job) use ($command) {
                return $job instanceof Closure
                            ? $job($command)
                            : $job === get_class($command);
            })->isNotEmpty();
    }

    /**
     * Set the pipes commands should be piped through before dispatching.
     *
     * @param array $pipes
     *
     * @return $this
     */
    /**
     * Teruskan command tertentu ke dispatcher asli (kelas, atau Closure yang menerima command); sisanya dicatat.
     *
     * @param array|Closure|string $jobsToDispatch
     *
     * @return $this
     */
    public function except($jobsToDispatch) {
        $this->jobsToDispatch = array_merge($this->jobsToDispatch, carr::wrap($jobsToDispatch));

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
     * @param mixed $command
     *
     * @return mixed
     */
    protected function serializeAndRestoreCommand($command) {
        return $this->serializeAndRestore ? unserialize(serialize($command)) : $command;
    }

    /**
     * @param mixed $command
     *
     * @return bool
     */
    protected function shouldDispatchCommand($command) {
        foreach ($this->jobsToDispatch as $candidate) {
            if ($candidate instanceof Closure ? $candidate($command) : $candidate === get_class($command)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|Closure $command
     * @param null|callable  $callback
     *
     * @return void
     */
    public function assertDispatchedOnce($command, $callback = null) {
        if ($command instanceof Closure) {
            list($command, $callback) = [$this->firstClosureParameterType($command), $command];
        }

        $count = $this->dispatched($command, $callback)->count();

        PHPUnit::assertSame(1, $count, "The expected [{$command}] job was dispatched {$count} times instead of 1 times.");
    }

    /**
     * @param int $count
     *
     * @return void
     */
    public function assertBatchCount($count) {
        $actual = count($this->batches);

        PHPUnit::assertCount($count, $this->batches, "Expected {$count} batches to be dispatched, but found {$actual} instead.");
    }

    /**
     * @return void
     */
    public function assertNothingBatched() {
        $jobNames = c::collect($this->batches)->map(function ($batch) {
            return c::collect($batch->jobs)->map(function ($job) {
                return is_object($job) ? get_class($job) : (string) $job;
            });
        })->flatten()->implode("\n- ");

        PHPUnit::assertEmpty($this->batches, "The following batched jobs were dispatched unexpectedly:\n\n- " . $jobNames . "\n");
    }

    /**
     * Tidak ada command terkirim yang membawa chain.
     *
     * @return void
     */
    public function assertNothingChained() {
        $chained = [];
        foreach ([$this->commands, $this->commandsSync, $this->commandsAfterResponse] as $recorded) {
            foreach ($recorded as $class => $commands) {
                foreach ($commands as $command) {
                    if (!empty($command->chained)) {
                        $chained[] = $class;
                    }
                }
            }
        }

        PHPUnit::assertEmpty($chained, "The following jobs were chained unexpectedly:\n\n- " . implode("\n- ", array_unique($chained)) . "\n");
    }

    public function pipeThrough(array $pipes) {
        $this->dispatcher->pipeThrough($pipes);

        return $this;
    }

    /**
     * Determine if the given command has a handler.
     *
     * @param mixed $command
     *
     * @return bool
     */
    public function hasCommandHandler($command) {
        return $this->dispatcher->hasCommandHandler($command);
    }

    /**
     * Retrieve the handler for a command.
     *
     * @param mixed $command
     *
     * @return mixed
     */
    public function getCommandHandler($command) {
        return $this->dispatcher->getCommandHandler($command);
    }

    /**
     * Map a command to a handler.
     *
     * @param array $map
     *
     * @return $this
     */
    public function map(array $map) {
        $this->dispatcher->map($map);

        return $this;
    }
}
