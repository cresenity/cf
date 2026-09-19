<?php


class CTesting_Fake_Queue_PendingChainFake extends CQueue_PendingChain {
    /**
     * The fake bus instance.
     *
     * @var \CTesting_Fake_Base_BusFake
     */
    protected $bus;

    /**
     * Create a new pending chain instance.
     *
     * @param \CTesting_Fake_Base_BusFake $bus
     * @param mixed                       $job
     * @param array                       $chain
     *
     * @return void
     */
    public function __construct(CTesting_Fake_Base_BusFake $bus, $job, $chain) {
        $this->bus = $bus;
        $this->job = $job;
        $this->chain = $chain;
    }

    /**
     * Dispatch the job with the given arguments.
     *
     * @return \CQueue_PendingDispatch
     */
    public function dispatch() {
        if (is_string($this->job)) {
            $firstJob = new $this->job(...func_get_args());
        } elseif ($this->job instanceof Closure) {
            $firstJob = CQueue_CallQueuedClosure::create($this->job);
        } else {
            $firstJob = $this->job;
        }

        $firstJob->allOnConnection($this->connection);
        $firstJob->allOnQueue($this->queue);
        $firstJob->chain($this->chain);
        $firstJob->delay($this->delay);
        $firstJob->chainCatchCallbacks = $this->catchCallbacks();

        return $this->bus->dispatch($firstJob);
    }
}
