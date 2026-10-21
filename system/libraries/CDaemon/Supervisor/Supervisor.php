<?php

use Carbon\CarbonImmutable;

class CDaemon_Supervisor_Supervisor implements CDaemon_Contract_PausableInterface, CDaemon_Contract_RestartableInterface, CDaemon_Contract_TerminableInterface {
    use CDaemon_Trait_ListenForSignals;

    /**
     * The name of this supervisor instance.
     *
     * @return string
     */
    public $name;

    /**
     * The SupervisorOptions that should be utilized.
     *
     * @var \CDaemon_Supervisor_SupervisorOptions
     */
    public $options;

    /**
     * All of the process pools being managed.
     *
     * @var \CCollection
     */
    public $processPools;

    /**
     * Indicates if the Supervisor processes are working.
     *
     * @var bool
     */
    public $working = true;

    /**
     * The time at which auto-scaling last ran for this supervisor.
     *
     * @var \Carbon\CarbonImmutable
     */
    public $lastAutoScaled;

    /**
     * The output handler.
     *
     * @var null|\Closure
     */
    public $output;

    /**
     * The master daemon class.
     *
     * @var null|string
     */
    public $masterDaemonClass;

    /**
     * Create a new supervisor instance.
     *
     * @param \CDaemon_Supervisor_SupervisorOptions $options
     *
     * @return void
     */
    public function __construct(CDaemon_Supervisor_SupervisorOptions $options) {
        $this->options = $options;
        $this->name = $options->name;
        $this->processPools = $this->createProcessPools();
        $this->output = function () {
        };

        CDaemon_Supervisor::supervisorCommandQueue()->flush($this->name);
    }

    /**
     * Create the supervisor's process pools.
     *
     * @return \CCollection
     */
    public function createProcessPools() {
        return $this->options->balancing()
                        ? $this->createProcessPoolPerQueue()
                        : $this->createSingleProcessPool();
    }

    /**
     * Create a process pool for each queue.
     *
     * @return \CCollection
     */
    protected function createProcessPoolPerQueue() {
        return c::collect(explode(',', $this->options->queue))->map(function ($queue) {
            return $this->createProcessPool($this->options->withQueue($queue));
        });
    }

    /**
     * Create a single process pool.
     *
     * @return \CCollection
     */
    protected function createSingleProcessPool() {
        return c::collect([$this->createProcessPool($this->options)]);
    }

    /**
     * Create a new process pool with the given options.
     *
     * @param \CDaemon_Supervisor_SupervisorOptions $options
     *
     * @return \CDaemon_Supervisor_ProcessPool
     */
    protected function createProcessPool(CDaemon_Supervisor_SupervisorOptions $options) {
        return new CDaemon_Supervisor_ProcessPool($options, function ($type, $line) {
            $this->output($type, $line);
        }, $this->masterDaemonClass);
    }

    /**
     * Scale the process count.
     *
     * @param int $processes
     *
     * @return void
     */
    public function scale($processes) {
        $this->options->maxProcesses = max(
            $this->options->maxProcesses,
            $processes,
            count($this->processPools)
        );

        $this->balance($this->processPools->mapWithKeys(function ($pool) use ($processes) {
            return [$pool->queue() => floor($processes / count($this->processPools))];
        })->all());
    }

    /**
     * Balance the process pool at the given scales.
     *
     * @param array $balance
     *
     * @return void
     */
    public function balance(array $balance) {
        foreach ($balance as $queue => $scale) {
            $this->processPools->first(function ($pool) use ($queue) {
                return $pool->queue() === $queue;
            }, new class() {
                public function __call($method, $arguments) {
                }
            })->scale($scale);
        }
    }

    /**
     * Terminate all current workers and start fresh ones.
     *
     * @return void
     */
    public function restart() {
        $this->working = true;

        $this->processPools->each->restart();
    }

    /**
     * Pause all of the worker processes.
     *
     * @return void
     */
    public function pause() {
        $this->working = false;

        $this->processPools->each->pause();
    }

    /**
     * Instruct all of the worker processes to continue working.
     *
     * @return void
     */
    public function continue() {
        $this->working = true;

        $this->processPools->each->continue();
    }

    /**
     * Terminate this supervisor process and all of its workers.
     *
     * @param int $status
     *
     * @return void
     */
    public function terminate($status = 0) {
        $this->working = false;

        // We will mark this supervisor as terminating so that any user interface can
        // correctly show the supervisor's status. Then, we will scale the process
        // pools down to zero workers to gracefully terminate them all out here.
        CDaemon_Supervisor::supervisorRepository()->forget($this->name);

        $this->processPools->each(function ($pool) {
            $pool->processes()->each(function ($process) {
                $process->terminate();
            });
        });

        if ($this->shouldWait()) {
            while ($this->processPools->map->runningProcesses()->collapse()->count()) {
                sleep(1);
            }
        }

        $this->exit($status);
    }

    /**
     * Check if the supervisor should wait for all its workers to terminate.
     *
     * @return bool
     */
    protected function shouldWait() {
        return !CF::config('daemon.supervisor.fast_termination')
               || CCache::manager()->store()->get('supervisor:terminate:wait');
    }

    /**
     * Monitor the worker processes.
     *
     * @return void
     */
    public function monitor() {
        $this->ensureNoDuplicateSupervisors();

        $this->listenForSignals();

        $this->persist();

        while (true) {
            sleep(1);

            $this->loop();
        }
    }

    /**
     * Ensure no other supervisors are running with the same name.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function ensureNoDuplicateSupervisors() {
        return true;
        if (CDaemon_Supervisor::supervisorRepository()->find($this->name) !== null) {
            throw new Exception("A supervisor with the name [{$this->name}] is already running.");
        }
    }

    /**
     * Perform a monitor loop.
     *
     * @return void
     */
    public function loop() {
        try {
            $this->ensureParentIsRunning();

            $this->processPendingSignals();

            $this->processPendingCommands();

            // If the supervisor is working, we will perform any needed scaling operations and
            // monitor all of these underlying worker processes to make sure they are still
            // processing queued jobs. If they have died, we will restart them each here.
            if ($this->working) {
                $this->autoScale();

                $this->processPools->each->monitor();
            }

            // Next, we'll persist the supervisor state to storage so that it can be read by a
            // user interface. This contains information on the specific options for it and
            // the current number of worker processes per queue for easy load monitoring.
            $this->persist();

            c::event(new CDaemon_Supervisor_Event_SupervisorLooped($this));
        } catch (Throwable $e) {
            CException::exceptionHandler()->report($e);
        }
    }

    /**
     * Ensure the parent process is still running.
     *
     * @return void
     */
    protected function ensureParentIsRunning() {
        if ($this->options->parentId <= 1) {
            return;
        }
        //lewat php-worker-wrapper.sh induk langsungnya shell wrapper yang tetap
        //hidup saat master mati, jadi ppid saja tidak cukup - cek PID master (ESRCH = 3)
        $masterGone = !posix_kill($this->options->parentId, 0) && posix_get_last_error() === 3;
        if (posix_getppid() <= 1 || $masterGone) {
            $this->terminate();
        }
    }

    /**
     * Handle any pending commands for the supervisor.
     *
     * @return void
     */
    protected function processPendingCommands() {
        foreach (CDaemon_Supervisor::supervisorCommandQueue()->pending($this->name) as $command) {
            c::container($command->command)->process($this, $command->options);
        }
    }

    /**
     * Run the auto-scaling routine for the supervisor.
     *
     * @return void
     */
    protected function autoScale() {
        $this->lastAutoScaled = $this->lastAutoScaled
                    ?: CarbonImmutable::now()->subSeconds($this->options->balanceCooldown + 1);

        if (CarbonImmutable::now()->subSeconds($this->options->balanceCooldown)->gte($this->lastAutoScaled)) {
            $this->lastAutoScaled = CarbonImmutable::now();

            CDaemon_Supervisor::autoScaler()->scale($this);
        }
    }

    /**
     * Persist information about this supervisor instance.
     *
     * @return void
     */
    public function persist() {
        CDaemon_Supervisor::supervisorRepository()->update($this);
    }

    /**
     * Prune all terminating processes and return the total process count.
     *
     * @return int
     */
    public function pruneAndGetTotalProcesses() {
        $this->pruneTerminatingProcesses();

        return $this->totalProcessCount();
    }

    /**
     * Prune any terminating processes that have finished terminating.
     *
     * @return void
     */
    public function pruneTerminatingProcesses() {
        $this->processPools->each->pruneTerminatingProcesses();
    }

    /**
     * Get all of the current processes as a collection.
     *
     * @return \CCollection
     */
    public function processes() {
        return $this->processPools->map->processes()->collapse();
    }

    /**
     * Get the processes that are still terminating.
     *
     * @return \CCollection
     */
    public function terminatingProcesses() {
        return $this->processPools->map->terminatingProcesses()->collapse();
    }

    /**
     * Get the total active process count, including processes pending termination.
     *
     * @return int
     */
    public function totalProcessCount() {
        return $this->processPools->sum->totalProcessCount();
    }

    /**
     * Get the total active process count by asking the OS.
     *
     * @return int
     */
    public function totalSystemProcessCount() {
        return CDaemon_Supervisor::systemProcessCounter()->get($this->name);
    }

    /**
     * Get the process ID for this supervisor.
     *
     * @return int
     */
    public function pid() {
        return getmypid();
    }

    /**
     * Get the current memory usage (in megabytes).
     *
     * @return float
     */
    public function memoryUsage() {
        return memory_get_usage() / 1024 / 1024;
    }

    /**
     * Determine if the supervisor is paused.
     *
     * @return bool
     */
    public function isPaused() {
        return !$this->working;
    }

    /**
     * Set the output handler.
     *
     * @param \Closure $callback
     *
     * @return $this
     */
    public function handleOutputUsing(Closure $callback) {
        $this->output = $callback;

        return $this;
    }

    /**
     * Handle the given output.
     *
     * @param string $type
     * @param string $line
     *
     * @return void
     */
    public function output($type, $line) {
        call_user_func($this->output, $type, $line);
    }

    /**
     * Shutdown the supervisor.
     *
     * @param int $status
     *
     * @return void
     */
    protected function exit($status = 0) {
        $this->exitProcess($status);
    }

    /**
     * Exit the PHP process.
     *
     * @param int $status
     *
     * @return void
     */
    protected function exitProcess($status = 0) {
        exit((int) $status);
    }

    public function setMasterDaemonClass($masterDaemonClass) {
        $this->masterDaemonClass = $masterDaemonClass;

        return $this;
    }

    public function getMasterDaemonClass() {
        return $this->masterDaemonClass;
    }
}
