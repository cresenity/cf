<?php

/**
 * @mixin \CServer_Process_Factory
 * @mixin \CServer_Process_PendingProcess
 */
class CServer_Process_Pool {
    /**
     * The process factory instance.
     *
     * @var \CServer_Process_Factory
     */
    protected $factory;

    /**
     * The callback that resolves the pending processes.
     *
     * @var callable
     */
    protected $callback;

    /**
     * The array of pending processes.
     *
     * @var array
     */
    protected $pendingProcesses = [];

    /**
     * Create a new process pool.
     *
     * @param \CServer_Process_Factory $factory
     * @param callable                 $callback
     *
     * @return void
     */
    public function __construct(CServer_Process_Factory $factory, callable $callback) {
        $this->factory = $factory;
        $this->callback = $callback;
    }

    /**
     * Add a process to the pool with a key.
     *
     * @param string $key
     *
     * @return \CServer_Process_PendingProcess
     */
    public function as(string $key) {
        return c::tap($this->factory->newPendingProcess(), function ($pendingProcess) use ($key) {
            $this->pendingProcesses[$key] = $pendingProcess;
        });
    }

    /**
     * Start all of the processes in the pool.
     *
     * @param null|callable $output
     *
     * @return \CServer_Process_InvokedProcessPool
     */
    public function start($output = null) {
        call_user_func($this->callback, $this);

        return new CServer_Process_InvokedProcessPool(
            c::collect($this->pendingProcesses)
                ->each(function ($pendingProcess) {
                    if (!$pendingProcess instanceof CServer_Process_PendingProcess) {
                        throw new InvalidArgumentException('Process pool must only contain pending processes.');
                    }
                })->mapWithKeys(function ($pendingProcess, $key) use ($output) {
                    return [$key => $pendingProcess->start(null, $output ? function ($type, $buffer) use ($key, $output) {
                        $output($type, $buffer, $key);
                    } : null)];
                })
                ->all()
        );
    }

    /**
     * Start and wait for the processes to finish.
     *
     * @return \CServer_Process_ProcessPoolResults
     */
    public function run() {
        return $this->wait();
    }

    /**
     * Start and wait for the processes to finish.
     *
     * @return \CServer_Process_ProcessPoolResults
     */
    public function wait() {
        return $this->start()->wait();
    }

    /**
     * Dynamically proxy methods calls to a new pending process.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return \CServer_Process_PendingProcess
     */
    public function __call($method, $parameters) {
        return c::tap($this->factory->{$method}(...$parameters), function ($pendingProcess) {
            $this->pendingProcesses[] = $pendingProcess;
        });
    }
}
