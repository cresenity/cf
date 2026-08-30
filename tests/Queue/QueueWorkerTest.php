<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__) . '/Support/WorkerFixture.php';

class QueueWorkerTest extends TestCase {
    protected function tearDown() {
        m::close();
    }

    /**
     * @param CQueue_AbstractJob[] $jobList
     *
     * @return QueueWorkerTestWorker
     */
    protected function makeWorker(array $jobList = []) {
        $handler = m::mock(CException_ExceptionHandler::class);
        $handler->shouldIgnoreMissing();

        return new QueueWorkerTestWorker(
            new QueueWorkerFakeManager(new QueueWorkerFakeConnection($jobList)),
            new CEvent_Dispatcher(),
            $handler,
            function () {
                return false;
            }
        );
    }

    /**
     * @return CQueue_WorkerOptions
     */
    protected function makeOptions(array $override = []) {
        $option = $override + [
            'name' => 'default',
            'backoff' => 0,
            'memory' => 128,
            'timeout' => 60,
            'sleep' => 3,
            'maxTries' => 5,
            'force' => false,
            'stopWhenEmpty' => false,
            'maxJobs' => 0,
            'maxTime' => 0,
            'rest' => 0,
        ];

        return new CQueue_WorkerOptions(
            $option['name'],
            $option['backoff'],
            $option['memory'],
            $option['timeout'],
            $option['sleep'],
            $option['maxTries'],
            $option['force'],
            $option['stopWhenEmpty'],
            $option['maxJobs'],
            $option['maxTime'],
            $option['rest']
        );
    }

    public function testJobIsFired() {
        $job = new QueueWorkerFakeJob();
        $worker = $this->makeWorker([$job]);

        $worker->runNextJob('test-connection', 'default', $this->makeOptions());

        $this->assertTrue($job->fired);
        $this->assertSame([], $worker->sleptFor);
    }

    public function testWorkerSleepsWhenQueueIsEmpty() {
        $worker = $this->makeWorker([]);

        $worker->runNextJob('test-connection', 'default', $this->makeOptions(['sleep' => 7]));

        $this->assertSame([7], $worker->sleptFor);
    }

    public function testJobIsNotFiredWhenAlreadyDeleted() {
        $job = new QueueWorkerFakeJob();
        $job->delete();
        $worker = $this->makeWorker([$job]);

        $worker->runNextJob('test-connection', 'default', $this->makeOptions());

        $this->assertFalse($job->fired);
    }

    public function testJobIsFailedWhenItAlreadyExceedsMaxAttempts() {
        $job = new QueueWorkerFakeJob();
        $job->attempts = 6;
        $worker = $this->makeWorker([$job]);

        $worker->runNextJob('test-connection', 'default', $this->makeOptions(['maxTries' => 5]));

        $this->assertFalse($job->fired);
        $this->assertTrue($job->hasFailed());
        $this->assertInstanceOf(CQueue_Exception_MaxAttemptsExceededException::class, $job->failedWith);
    }

    /**
     * `pauseWorker()` dropped the `return` that Laravel has, so its status never
     * reached `daemon()`. A worker paused or in maintenance mode therefore never
     * stopped for a memory limit, a queue restart or `maxTime` -- it just kept
     * looping. The status has to come back out of the method.
     */
    public function testPauseWorkerReturnsTheStopStatus() {
        $worker = $this->makeWorker([]);
        $worker->shouldQuit = false;

        $method = new ReflectionMethod(CQueue_Worker::class, 'pauseWorker');
        $method->setAccessible(true);

        // memory limit exceeded -> the daemon loop must be told to stop
        list($status, $reason) = $method->invoke($worker, $this->makeOptions(['memory' => 1]), null);
        $this->assertSame(CQueue_Worker::EXIT_MEMORY_LIMIT, $status);
        $this->assertSame(CQueue_WorkerStopReason::MAX_MEMORY_EXCEEDED, $reason);

        // nothing wrong -> nothing to report, and the loop keeps running
        list($status, $reason) = $method->invoke($worker, $this->makeOptions(['memory' => 4096]), null);
        $this->assertNull($status);
        $this->assertNull($reason);
    }

    public function testStopIfNecessaryReportsEachStopConditionWithItsReason() {
        $worker = $this->makeWorker([]);
        $method = new ReflectionMethod(CQueue_Worker::class, 'stopIfNecessary');
        $method->setAccessible(true);

        $this->assertSame(
            [CQueue_Worker::EXIT_MEMORY_LIMIT, CQueue_WorkerStopReason::MAX_MEMORY_EXCEEDED],
            $method->invoke($worker, $this->makeOptions(['memory' => 1]), null)
        );
        $this->assertSame(
            [CQueue_Worker::EXIT_SUCCESS, CQueue_WorkerStopReason::QUEUE_EMPTY],
            $method->invoke($worker, $this->makeOptions(['stopWhenEmpty' => true]), null, 0, null)
        );

        $worker->setJobsProcessed(2);
        $this->assertSame(
            [CQueue_Worker::EXIT_SUCCESS, CQueue_WorkerStopReason::MAX_JOBS_EXCEEDED],
            $method->invoke($worker, $this->makeOptions(['maxJobs' => 2]), null, 0, new QueueWorkerFakeJob())
        );

        $worker->setJobsProcessed(1);
        $this->assertSame(
            [null, null],
            $method->invoke($worker, $this->makeOptions(['maxJobs' => 2]), null, 0, new QueueWorkerFakeJob())
        );

        $worker->lostConnection = true;
        $this->assertSame(
            [CQueue_Worker::EXIT_SUCCESS, CQueue_WorkerStopReason::LOST_CONNECTION],
            $method->invoke($worker, $this->makeOptions(), null, 0, new QueueWorkerFakeJob())
        );
        $worker->lostConnection = false;

        $worker->shouldQuit = true;
        $this->assertSame(
            [CQueue_Worker::EXIT_SUCCESS, CQueue_WorkerStopReason::INTERRUPTED],
            $method->invoke($worker, $this->makeOptions(), null, 0, new QueueWorkerFakeJob())
        );
    }

    /**
     * The new events have to actually reach listeners, otherwise porting the
     * classes across bought nothing.
     */
    public function testWorkerLifecycleEventsAreDispatched() {
        $seen = [];
        $dispatcher = new CEvent_Dispatcher();
        foreach ([
            CQueue_Event_WorkerStarting::class,
            CQueue_Event_WorkerIdle::class,
            CQueue_Event_JobPopping::class,
            CQueue_Event_JobPopped::class,
            CQueue_Event_JobAttempted::class,
        ] as $event) {
            $dispatcher->listen($event, function ($e) use (&$seen, $event) {
                $seen[$event] = $e;
            });
        }

        $handler = m::mock(CException_ExceptionHandler::class);
        $handler->shouldIgnoreMissing();
        $worker = new QueueWorkerTestWorker(
            new QueueWorkerFakeManager(new QueueWorkerFakeConnection([new QueueWorkerFakeJob()])),
            $dispatcher,
            $handler,
            function () {
                return false;
            }
        );
        // one job, then an empty iteration that both raises WorkerIdle and ends the loop
        $worker->daemon('test-connection', 'default', new CQueue_WorkerOptions('default', 0, 4096, 30, 0, 5, false, true));

        $this->assertArrayHasKey(CQueue_Event_WorkerStarting::class, $seen);
        $this->assertArrayHasKey(CQueue_Event_WorkerIdle::class, $seen);
        $this->assertArrayHasKey(CQueue_Event_JobPopping::class, $seen);
        $this->assertArrayHasKey(CQueue_Event_JobPopped::class, $seen);
        $this->assertArrayHasKey(CQueue_Event_JobAttempted::class, $seen);

        $this->assertSame('default', $seen[CQueue_Event_WorkerStarting::class]->queue);
        $this->assertTrue($seen[CQueue_Event_JobAttempted::class]->successful());
        $this->assertNull($seen[CQueue_Event_JobAttempted::class]->exceptionOccurred);
    }

    /**
     * JobReleasedAfterException used to fire on every exception, announcing a
     * release that had not happened whenever the job was deleted, already
     * released, or failed.
     */
    public function testJobReleasedAfterExceptionOnlyFiresWhenTheJobIsActuallyReleased() {
        $dispatched = [];
        $dispatcher = new CEvent_Dispatcher();
        $dispatcher->listen(CQueue_Event_JobReleasedAfterException::class, function ($e) use (&$dispatched) {
            $dispatched[] = $e;
        });

        $handler = m::mock(CException_ExceptionHandler::class);
        $handler->shouldIgnoreMissing();
        $job = new QueueWorkerFakeJob('boom', [], function ($job) {
            $job->delete();

            throw new RuntimeException('boom');
        });
        $worker = new QueueWorkerTestWorker(
            new QueueWorkerFakeManager(new QueueWorkerFakeConnection([$job])),
            $dispatcher,
            $handler,
            function () {
                return false;
            }
        );

        $worker->runNextJob('test-connection', 'default', $this->makeOptions());

        $this->assertTrue($job->isDeleted());
        $this->assertSame([], $dispatched, 'A deleted job was reported as released.');
    }

    /**
     * An exception type registered with `dontRetry()` must fail the job on the
     * spot instead of burning its remaining attempts.
     */
    public function testAJobIsFailedWhenTheHandlerSaysItShouldNotBeRetried() {
        $handler = new CException_ExceptionHandler();
        $handler->dontRetry(RuntimeException::class);

        $job = new QueueWorkerFakeJob('boom', [], function () {
            throw new RuntimeException('malformed payload');
        });
        $worker = new QueueWorkerTestWorker(
            new QueueWorkerFakeManager(new QueueWorkerFakeConnection([$job])),
            new CEvent_Dispatcher(),
            $handler,
            function () {
                return false;
            }
        );

        $worker->runNextJob('test-connection', 'default', $this->makeOptions(['maxTries' => 5]));

        $this->assertTrue($job->hasFailed(), 'A non-retryable exception did not fail the job.');
        $this->assertFalse($job->isReleased(), 'A failed job must not go back on the queue.');
    }

    public function testAJobIsStillRetriedForAnExceptionTypeThatWasNotRegistered() {
        $handler = new CException_ExceptionHandler();
        $handler->dontRetry(LogicException::class);

        $job = new QueueWorkerFakeJob('boom', [], function () {
            throw new RuntimeException('transient');
        });
        $worker = new QueueWorkerTestWorker(
            new QueueWorkerFakeManager(new QueueWorkerFakeConnection([$job])),
            new CEvent_Dispatcher(),
            $handler,
            function () {
                return false;
            }
        );

        $worker->runNextJob('test-connection', 'default', $this->makeOptions(['maxTries' => 5]));

        $this->assertFalse($job->hasFailed());
        $this->assertTrue($job->isReleased(), 'A retryable job should go back on the queue.');
    }

    public function testDontRetryAlsoAcceptsACallback() {
        $handler = new CException_ExceptionHandler();
        $handler->dontRetry(function ($e) {
            return strpos($e->getMessage(), 'malformed') !== false;
        });

        $this->assertTrue($handler->shouldStopRetries(new RuntimeException('malformed payload')));
        $this->assertFalse($handler->shouldStopRetries(new RuntimeException('timed out')));
    }

    /**
     * `CQueue_Runner` defaults to `once => true`, so `CQueue::run()` without
     * options goes through `runNextJob()` and not `daemon()`. Every guarantee
     * tested in QueueWorkerTimeoutTest hangs on this default, so pin it here.
     */
    public function testRunnerDefaultsToTheOnceCodePath() {
        $worker = $this->makeWorker([]);
        $runner = new CQueue_Runner($worker, null, []);

        $method = new ReflectionMethod(CQueue_Runner::class, 'getOption');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($runner, 'once'));
    }

    /**
     * The `timeout` option used to be accepted and then silently ignored on that
     * same code path. It must at least survive the trip into CQueue_WorkerOptions.
     */
    public function testRunnerPassesTimeoutOptionThrough() {
        $worker = $this->makeWorker([]);
        $runner = new CQueue_Runner($worker, null, ['timeout' => 600]);

        $method = new ReflectionMethod(CQueue_Runner::class, 'gatherWorkerOptions');
        $method->setAccessible(true);
        $options = $method->invoke($runner);

        $this->assertInstanceOf(CQueue_WorkerOptions::class, $options);
        $this->assertSame(600, $options->timeout);
    }

    /**
     * @return void
     */
    protected function tearDownDaemonRunningService() {
        $property = new ReflectionProperty(CDaemon::class, 'runningService');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * `getNextJob()` used to call `CDaemon::handleException()` when a pop
     * failed inside a daemon - errNo -1 is mapped fatal, and the daemon's
     * default `isDaemonContinueOnFatalError=false` turns that into `exit(1)`,
     * killing the whole process over a transient error the catch block had
     * already decided was recoverable (it sleeps and retries right after).
     * It must log through the running service instead, the same fix already
     * applied to runJob() in 95400fc41.
     *
     * @return void
     */
    public function testGetNextJobLogsInsteadOfHandlingExceptionAsFatal() {
        $property = new ReflectionProperty(CDaemon::class, 'runningService');
        $property->setAccessible(true);
        $property->setValue(null, new QueueWorkerFakeDaemonService());

        try {
            $worker = $this->makeWorker([]);
            $connection = new QueueWorkerThrowingFakeConnection(new RuntimeException('socket error on read socket'));
            $worker->setManager(new QueueWorkerFakeManager($connection));

            $worker->runNextJob('test-connection', 'default', $this->makeOptions());

            $service = CDaemon::getRunningService();
            $this->assertNotEmpty($service->logged, 'kegagalan pop job harus tercatat di log daemon');
            $this->assertStringContainsString('socket error on read socket', $service->logged[0][0]);
            $this->assertSame(
                [1, 3],
                $worker->sleptFor,
                'harus tidur 1s di getNextJob() lalu 3s di runNextJob() karena tak ada job, bukan berhenti'
            );
        } finally {
            $this->tearDownDaemonRunningService();
        }
    }
}
