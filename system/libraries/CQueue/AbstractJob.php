<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Sep 8, 2019, 2:18:49 AM
 */
abstract class CQueue_AbstractJob implements CQueue_JobInterface {
    use CTrait_Helper_InteractsWithTime;
    /**
     * The job handler instance.
     *
     * @var mixed
     */
    protected $instance;

    /**
     * The IoC container instance.
     *
     * @var CContainer_Container
     */
    protected $container;

    /**
     * Indicates if the job has been deleted.
     *
     * @var bool
     */
    protected $deleted = false;

    /**
     * Indicates if the job has been released.
     *
     * @var bool
     */
    protected $released = false;

    /**
     * Indicates if the job has failed.
     *
     * @var bool
     */
    protected $failed = false;

    /**
     * The name of the connection the job belongs to.
     *
     * @var string
     */
    protected $connectionName;

    /**
     * The name of the queue the job belongs to.
     *
     * @var string
     */
    protected $queue;

    /**
     * Get the job identifier.
     *
     * @return string
     */
    abstract public function getJobId();

    /**
     * Get the raw body of the job.
     *
     * @return string
     */
    abstract public function getRawBody();

    /**
     * Get the UUID of the job.
     *
     * @return null|string
     */
    public function uuid() {
        return isset($this->payload()['uuid']) ? $this->payload()['uuid'] : null;
    }

    /**
     * Fire the job.
     *
     * @return void
     */
    public function fire() {
        $payload = $this->payload();

        list($class, $method) = CQueue_JobName::parse($payload['job']);
        $this->instance = $this->resolve($class);

        $this->instance->{$method}($this, $payload['data']);
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete() {
        $this->deleted = true;
    }

    /**
     * Determine if the job has been deleted.
     *
     * @return bool
     */
    public function isDeleted() {
        return $this->deleted;
    }

    /**
     * Release the job back into the queue.
     *
     * @param int $delay
     *
     * @return void
     */
    public function release($delay = 0) {
        $this->released = true;
    }

    /**
     * Determine if the job was released back into the queue.
     *
     * @return bool
     */
    public function isReleased() {
        return $this->released;
    }

    /**
     * Determine if the job has been deleted or released.
     *
     * @return bool
     */
    public function isDeletedOrReleased() {
        return $this->isDeleted() || $this->isReleased();
    }

    /**
     * Determine if the job has been marked as a failure.
     *
     * @return bool
     */
    public function hasFailed() {
        return $this->failed;
    }

    /**
     * Mark the job as "failed".
     *
     * @return void
     */
    public function markAsFailed() {
        $this->failed = true;
    }

    /**
     * Delete the job, call the "failed" method, and raise the failed job event.
     *
     * @param null|\Throwable $e
     *
     * @return void
     */
    public function fail($e = null) {
        $this->markAsFailed();
        if ($this->isDeleted()) {
            return;
        }

        try {
            // If the job has failed, we will delete it, call the "failed" method and then call
            // an event indicating the job has failed so it can be logged if needed. This is
            // to allow every developer to better keep monitor of their failed queue jobs.
            $this->delete();
            $this->failed($e);
        } finally {
            CEvent::dispatch(new CQueue_Event_JobFailed(
                $this->connectionName,
                $this,
                $e ?: new CQueue_Exception_ManuallyFailedException()
            ));
        }
    }

    /**
     * Process an exception that caused the job to fail.
     *
     * @param null|\Throwable $e
     *
     * @return void
     */
    protected function failed($e) {
        $payload = $this->payload();
        list($class, $method) = CQueue_JobName::parse($payload['job']);

        if (method_exists($this->instance = $this->resolve($class), 'failed')) {
            $this->instance->failed($payload['data'], $e, isset($payload['uuid']) ? $payload['uuid'] : '');
        }
    }

    /**
     * Resolve the given class.
     *
     * @param string $class
     *
     * @return mixed
     */
    protected function resolve($class) {
        return $this->container->make($class);
    }

    /**
     * Get the resolved job handler instance.
     *
     * @return mixed
     */
    public function getResolvedJob() {
        return $this->instance;
    }

    /**
     * Get the decoded body of the job.
     *
     * @return array
     */
    public function payload() {
        return json_decode($this->getRawBody(), true);
    }

    /**
     * Get the number of times to attempt a job.
     *
     * @return null|int
     */
    public function maxTries() {
        return isset($this->payload()['maxTries']) ? $this->payload()['maxTries'] : null;
    }

    /**
     * Get the number of times to attempt a job after an exception.
     *
     * @return null|int
     */
    public function maxExceptions() {
        return isset($this->payload()['maxExceptions']) ? $this->payload()['maxExceptions'] : null;
    }

    /**
     * Determine if the job should fail when it timeouts.
     *
     * @return bool
     */
    public function shouldFailOnTimeout() {
        return isset($this->payload()['failOnTimeout']) ? $this->payload()['failOnTimeout'] : false;
    }

    /**
     * The number of seconds to wait before retrying a job that encountered an uncaught exception.
     *
     * @return null|int
     */
    public function backoff() {
        return isset($this->payload()['backoff']) ? $this->payload()['backoff'] : (isset($this->payload()['delay']) ? $this->payload()['delay'] : null);
    }

    /**
     * Get the number of seconds the job can run.
     *
     * @return null|int
     */
    public function timeout() {
        return isset($this->payload()['timeout']) ? $this->payload()['timeout'] : null;
    }

    /**
     * Get the timestamp indicating when the job should timeout.
     *
     * @return null|int
     */
    public function retryUntil() {
        return isset($this->payload()['retryUntil']) ? $this->payload()['retryUntil'] : (isset($this->payload()['timeoutAt']) ? $this->payload()['timeoutAt'] : null);
    }

    /**
     * Get the name of the queued job class.
     *
     * @return string
     */
    public function getName() {
        return $this->payload()['job'];
    }

    /**
     * Get the resolved name of the queued job class.
     *
     * Resolves the name of "wrapped" jobs such as class-based handlers.
     *
     * @return string
     */
    public function resolveName() {
        return CQueue_JobName::resolve($this->getName(), $this->payload());
    }

    /**
     * Get the name of the connection the job belongs to.
     *
     * @return string
     */
    public function getConnectionName() {
        return $this->connectionName;
    }

    /**
     * Get the name of the queue the job belongs to.
     *
     * @return string
     */
    public function getQueue() {
        return $this->queue;
    }

    /**
     * Get the service container instance.
     *
     * @return \CContainer_Container
     */
    public function getContainer() {
        return $this->container;
    }
}
