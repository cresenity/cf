<?php

/**
 * Shared doubles for the CQueue_Worker tests.
 *
 * Modelled on Laravel's tests/Queue fakes rather than on Mockery: the worker
 * calls its collaborators many times per job, and hand-written fakes keep the
 * assertions about behaviour instead of about call counts.
 */
class QueueWorkerFakeJob extends CQueue_AbstractJob {
    /**
     * @var bool
     */
    public $fired = false;

    /**
     * @var null|Throwable
     */
    public $failedWith;

    /**
     * @var int
     */
    public $attempts = 1;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $body;

    /**
     * @var null|callable
     */
    protected $callback;

    /**
     * @param string        $id
     * @param array         $payload
     * @param null|callable $callback
     */
    public function __construct($id = 'job-1', array $payload = [], $callback = null) {
        $this->id = $id;
        $this->connectionName = 'test-connection';
        $this->queue = 'default';
        $this->callback = $callback;
        $this->body = json_encode($payload + [
            'uuid' => $id,
            'displayName' => 'QueueWorkerFakeJob',
            'job' => 'QueueWorkerFakeJob@handle',
            'maxTries' => null,
            'maxExceptions' => null,
            'timeout' => null,
            'failOnTimeout' => false,
            'data' => [],
        ]);
    }

    /**
     * @return string
     */
    public function getJobId() {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getRawBody() {
        return $this->body;
    }

    /**
     * @return int
     */
    public function attempts() {
        return $this->attempts;
    }

    /**
     * @return void
     */
    public function fire() {
        $this->fired = true;
        if ($this->callback != null) {
            call_user_func($this->callback, $this);
        }
    }

    /**
     * @param null|Throwable $e
     *
     * @return void
     */
    public function fail($e = null) {
        $this->failed = true;
        $this->failedWith = $e;
    }
}

class QueueWorkerFakeConnection {
    /**
     * @var CQueue_AbstractJob[]
     */
    public $jobList = [];

    /**
     * @var int
     */
    public $popCount = 0;

    /**
     * @param CQueue_AbstractJob[] $jobList
     */
    public function __construct(array $jobList = []) {
        $this->jobList = $jobList;
    }

    /**
     * @param null|string $queue
     *
     * @return null|CQueue_AbstractJob
     */
    public function pop($queue = null) {
        $this->popCount++;

        return array_shift($this->jobList);
    }

    /**
     * @return string
     */
    public function getConnectionName() {
        return 'test-connection';
    }
}

/**
 * Connection whose pop() always throws, to test getNextJob()'s catch block.
 */
class QueueWorkerThrowingFakeConnection extends QueueWorkerFakeConnection {
    /**
     * @var Throwable
     */
    protected $exception;

    /**
     * @param Throwable $exception
     */
    public function __construct(Throwable $exception) {
        parent::__construct([]);
        $this->exception = $exception;
    }

    /**
     * @param null|string $queue
     *
     * @return never
     */
    public function pop($queue = null) {
        $this->popCount++;

        throw $this->exception;
    }
}

/**
 * Stand-in for a running CDaemon service, just enough to observe log() calls
 * without any of the real process/pid machinery.
 */
class QueueWorkerFakeDaemonService {
    /**
     * @var array
     */
    public $logged = [];

    /**
     * @param string $msg
     * @param string $label
     *
     * @return void
     */
    public function log($msg, $label = '') {
        $this->logged[] = [$msg, $label];
    }
}

class QueueWorkerFakeManager extends CQueue_Manager {
    /**
     * @var QueueWorkerFakeConnection
     */
    protected $fakeConnection;

    /**
     * @param QueueWorkerFakeConnection $connection
     */
    public function __construct($connection) {
        $this->fakeConnection = $connection;
    }

    /**
     * @param null|string $name
     *
     * @return QueueWorkerFakeConnection
     */
    public function connection($name = null) {
        return $this->fakeConnection;
    }
}

/**
 * A worker that records the calls which would otherwise end the process.
 *
 * `stop()` and `kill()` both call `exit()` in production, so without this a
 * test that reaches either of them would take PHPUnit down with it.
 */
class QueueWorkerTestWorker extends CQueue_Worker {
    /**
     * @var int[]
     */
    public $sleptFor = [];

    /**
     * @var bool
     */
    public $stopped = false;

    /**
     * @var null|int
     */
    public $stoppedWith;

    /**
     * @var bool
     */
    public $killed = false;

    /**
     * @var null|int
     */
    public $killedWith;

    /**
     * @param int $seconds
     *
     * @return void
     */
    public function sleep($seconds) {
        $this->sleptFor[] = $seconds;
    }

    /**
     * @var null|string
     */
    public $stoppedReason;

    /**
     * @var null|string
     */
    public $killedReason;

    /**
     * @param int                       $status
     * @param null|CQueue_WorkerOptions $options
     * @param null|string               $reason
     *
     * @return int
     */
    public function stop($status = 0, $options = null, $reason = null) {
        $this->stopped = true;
        $this->stoppedWith = $status;
        $this->stoppedReason = $reason;

        return $status;
    }

    /**
     * @param int                       $status
     * @param null|CQueue_WorkerOptions $options
     * @param null|string               $reason
     *
     * @return int
     */
    public function kill($status = 0, $options = null, $reason = null) {
        $this->killed = true;
        $this->killedWith = $status;
        $this->killedReason = $reason;

        return $status;
    }

    /**
     * @param int $count
     *
     * @return void
     */
    public function setJobsProcessed($count) {
        $this->jobsProcessed = $count;
    }
}
