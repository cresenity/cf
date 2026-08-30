<?php

defined('SYSPATH') or die('No direct access allowed.');

class CQueue_Worker {
    use CDatabase_Trait_DetectLostConnection;

    const EXIT_SUCCESS = 0;

    const EXIT_ERROR = 1;

    const EXIT_MEMORY_LIMIT = 12;

    /**
     * Indicates if the worker should exit.
     *
     * @var bool
     */
    public $shouldQuit = false;

    /**
     * Indicates if the worker is paused.
     *
     * @var bool
     */
    public $paused = false;

    /**
     * Indicates if the worker lost its connection.
     *
     * @var bool
     */
    public $lostConnection = false;

    /**
     * The job currently being processed.
     *
     * @var null|CQueue_AbstractJob
     */
    public $currentJob;

    /**
     * The number of jobs processed by the worker.
     *
     * @var null|int
     */
    protected $jobsProcessed;

    /**
     * When the last job finished, as a high-resolution timestamp.
     *
     * @var null|float
     */
    protected $lastJobProcessedAt;

    /**
     * The exit code to use when the memory limit is exceeded.
     *
     * @var null|int
     */
    public static $memoryExceededExitCode;

    /**
     * The exit code to use when a job times out.
     *
     * @var null|int
     */
    public static $timedOutExitCode;

    /**
     * Indicates if the worker should report job exceptions.
     *
     * @var bool
     */
    public static $reportJobExceptions = true;

    /**
     * Indicates if the worker should stop when a lost connection is detected.
     *
     * @var bool
     */
    public static $stopOnLostConnection = true;

    /**
     * Indicates if the worker should check for the restart signal in the cache.
     *
     * @var bool
     */
    public static $restartable = true;

    /**
     * Indicates if the worker should check for the paused signal in the cache.
     *
     * @var bool
     */
    public static $pausable = true;

    /**
     * The name of the worker.
     *
     * @var string
     */
    protected $name;

    /**
     * The queue manager instance.
     *
     * @var CQueue_Manager
     */
    protected $manager;

    /**
     * The event dispatcher instance.
     *
     * @var CEvent_Dispatcher
     */
    protected $events;

    /**
     * The cache repository implementation.
     *
     * @var CCache_Repository
     */
    protected $cache;

    /**
     * The exception handler instance.
     *
     * @var CException_ExceptionHandler
     */
    protected $exceptions;

    /**
     * The callback used to determine if the application is in maintenance mode.
     *
     * @var callable
     */
    protected $isDownForMaintenance;

    /**
     * The callback used to reset the application's scope.
     *
     * @var callable
     */
    protected $resetScope;

    /**
     * The callbacks used to pop jobs from queues.
     *
     * @var callable[]
     */
    protected static $popCallbacks = [];

    /**
     * @var string
     */
    protected $currentJobName;

    /**
     * Create a new queue worker.
     *
     * @param CQueue_Manager              $manager
     * @param CEvent_Dispatcher           $events
     * @param CException_ExceptionHandler $exceptions
     * @param callable                    $isDownForMaintenance
     *
     * @return void
     */
    public function __construct(CQueue_Manager $manager, CEvent_Dispatcher $events, CException_ExceptionHandler $exceptions, callable $isDownForMaintenance, ?callable $resetScope = null) {
        $this->events = $events;
        $this->manager = $manager;
        $this->exceptions = $exceptions;
        $this->isDownForMaintenance = $isDownForMaintenance;
        $this->resetScope = $resetScope;
    }

    /**
     * Listen to the given queue in a loop.
     *
     * @param string               $connectionName
     * @param string               $queue
     * @param CQueue_WorkerOptions $options
     *
     * @return void
     */
    public function daemon($connectionName, $queue, CQueue_WorkerOptions $options) {
        if ($supportsAsyncSignals = $this->supportsAsyncSignals()) {
            $this->listenForSignals($connectionName, $queue, $options);
        }
        $lastRestart = $this->getTimestampOfLastQueueRestart();
        $startTime = $this->currentTime();
        $this->jobsProcessed = 0;
        $this->raiseWorkerStartingEvent($connectionName, $queue, $options);
        while (true) {
            // Before reserving any jobs, we will make sure this queue is not paused and
            // if it is we will just pause this worker for a given amount of time and
            // make sure we do not need to kill this worker process off completely.
            if (!$this->daemonShouldRun($options, $connectionName, $queue)) {
                list($status, $reason) = $this->pauseWorker($options, $lastRestart, $startTime);
                if (!is_null($status)) {
                    return $this->stop($status, $options, $reason);
                }

                continue;
            }
            if (isset($this->resetScope)) {
                call_user_func($this->resetScope);
            }

            // First, we will attempt to get the next job off of the queue. We will also
            // register the timeout handler and reset the alarm for this job so it is
            // not stuck in a frozen state forever. Then, we can fire off this job.
            $job = $this->getNextJob(
                $this->manager->connection($connectionName),
                $queue
            );
            if ($supportsAsyncSignals) {
                $this->registerTimeoutHandler($job, $options);
            }
            // If the daemon should run (not in maintenance mode, etc.), then we can run
            // fire off this job for processing. Otherwise, we will need to sleep the
            // worker so no more jobs are processed until they should be processed.
            if ($job) {
                $this->jobsProcessed++;
                $this->runJob($job, $connectionName, $options);
                $this->lastJobProcessedAt = $this->currentTime();
                if ($options->rest > 0) {
                    $this->sleep($options->rest);
                }
            } else {
                $this->events->dispatch(new CQueue_Event_WorkerIdle($connectionName, $queue, $options));
                $this->sleep($options->sleep);
            }

            if ($supportsAsyncSignals) {
                $this->resetTimeoutHandler();
            }
            // Finally, we will check to see if we have exceeded our memory limits or if
            // the queue should restart based on other indications. If so, we'll stop
            // this worker and let whatever is "monitoring" it restart the process.
            list($status, $reason) = $this->stopIfNecessary($options, $lastRestart, $startTime, $job);

            if (!is_null($status)) {
                return $this->stop($status, $options, $reason);
            }
        }
    }

    /**
     * Register the worker timeout handler.
     *
     * @param null|CQueue_AbstractJob $job
     * @param CQueue_WorkerOptions    $options
     *
     * @return void
     */
    protected function registerTimeoutHandler($job, CQueue_WorkerOptions $options) {
        // We will register a signal handler for the alarm signal so that we can kill this
        // process if it is running too long because it has frozen. This uses the async
        // signals supported in recent versions of PHP to accomplish it conveniently.
        pcntl_signal(SIGALRM, function () use ($job, $options) {
            if ($job) {
                $this->markJobAsFailedIfWillExceedMaxAttempts(
                    $job->getConnectionName(),
                    $job,
                    (int) $options->maxTries,
                    $e = $this->timeoutExceededException($job)
                );

                $this->markJobAsFailedIfWillExceedMaxExceptions(
                    $job->getConnectionName(),
                    $job,
                    $e
                );

                $this->markJobAsFailedIfItShouldFailOnTimeout(
                    $job->getConnectionName(),
                    $job,
                    $e
                );

                $this->events->dispatch(new CQueue_Event_JobTimedOut(
                    $job->getConnectionName(),
                    $job
                ));
            }
            $exitCode = static::$timedOutExitCode !== null ? static::$timedOutExitCode : static::EXIT_ERROR;
            $this->kill($exitCode, $options, CQueue_WorkerStopReason::TIMED_OUT);
        }, true);
        pcntl_alarm(
            max($this->timeoutForJob($job, $options), 0)
        );
    }

    /**
     * Reset the worker timeout handler.
     *
     * @return void
     */
    protected function resetTimeoutHandler() {
        pcntl_alarm(0);
    }

    /**
     * Get the appropriate timeout for the given job.
     *
     * @param null|CQueue_AbstractJob $job
     * @param CQueue_WorkerOptions    $options
     *
     * @return int
     */
    protected function timeoutForJob($job, CQueue_WorkerOptions $options) {
        return $job && !is_null($job->timeout()) ? $job->timeout() : $options->timeout;
    }

    /**
     * Determine if the daemon should process on this iteration.
     *
     * @param CQueue_WorkerOptions $options
     * @param string               $connectionName
     * @param string               $queue
     *
     * @return bool
     */
    protected function daemonShouldRun(CQueue_WorkerOptions $options, $connectionName, $queue) {
        //$isDownForMaintenance = $this->isDownForMaintenance();
        $isDownForMaintenance = CF::isDownForMaintenance();

        return !(($isDownForMaintenance && !$options->force)
                || $this->paused
                || $this->events->until(new CQueue_Event_Looping($connectionName, $queue, $options)) === false);
    }

    /**
     * Pause the worker for the current loop.
     *
     * @param CQueue_WorkerOptions $options
     * @param int                  $lastRestart
     * @param float                $startTime
     *
     * @return array a list as array(status, reason), both null when nothing is wrong
     */
    protected function pauseWorker(CQueue_WorkerOptions $options, $lastRestart, $startTime = 0) {
        $this->sleep($options->sleep > 0 ? $options->sleep : 1);

        return $this->stopIfNecessary($options, $lastRestart, $startTime);
    }

    /**
     * Determine the exit code to stop the process, if it should stop at all.
     *
     * @param CQueue_WorkerOptions     $options
     * @param int                      $lastRestart
     * @param float                    $startTime
     * @param null|CQueue_JobInterface $job
     *
     * @return array a list as array(status, reason), both null when nothing is wrong
     */
    protected function stopIfNecessary(CQueue_WorkerOptions $options, $lastRestart, $startTime = 0, $job = null) {
        $memoryExitCode = static::$memoryExceededExitCode !== null
            ? static::$memoryExceededExitCode
            : static::EXIT_MEMORY_LIMIT;
        $idleFor = $this->currentTime() - ($this->lastJobProcessedAt !== null ? $this->lastJobProcessedAt : $startTime);

        if ($this->lostConnection) {
            return [static::EXIT_SUCCESS, CQueue_WorkerStopReason::LOST_CONNECTION];
        }
        if ($this->shouldQuit) {
            return [static::EXIT_SUCCESS, CQueue_WorkerStopReason::INTERRUPTED];
        }
        if ($this->memoryExceeded($options->memory)) {
            return [$memoryExitCode, CQueue_WorkerStopReason::MAX_MEMORY_EXCEEDED];
        }
        if ($this->queueShouldRestart($lastRestart)) {
            return [static::EXIT_SUCCESS, CQueue_WorkerStopReason::RECEIVED_RESTART_SIGNAL];
        }
        if ($options->stopWhenEmpty && is_null($job)) {
            return [static::EXIT_SUCCESS, CQueue_WorkerStopReason::QUEUE_EMPTY];
        }
        if ($options->stopWhenEmptyFor && is_null($job) && $idleFor >= $options->stopWhenEmptyFor) {
            return [static::EXIT_SUCCESS, CQueue_WorkerStopReason::QUEUE_EMPTY_FOR];
        }
        if ($options->maxTime && $this->currentTime() - $startTime >= $options->maxTime) {
            return [static::EXIT_SUCCESS, CQueue_WorkerStopReason::MAX_TIME_EXCEEDED];
        }
        if ($options->maxJobs && $this->jobsProcessed >= $options->maxJobs) {
            return [static::EXIT_SUCCESS, CQueue_WorkerStopReason::MAX_JOBS_EXCEEDED];
        }

        return [null, null];
    }

    /**
     * Process the next job on the queue.
     *
     * @param string               $connectionName
     * @param string               $queue
     * @param CQueue_WorkerOptions $options
     *
     * @return void
     */
    public function runNextJob($connectionName, $queue, CQueue_WorkerOptions $options) {
        // Async signals have to be enabled here too, not only in daemon(). Without them
        // the alarm below is delivered but never dispatched, because dispatching would
        // take a pcntl_signal_dispatch() call that a frozen job never reaches.
        if ($supportsAsyncSignals = $this->supportsAsyncSignals()) {
            pcntl_async_signals(true);
        }
        $job = $this->getNextJob(
            $this->manager->connection($connectionName),
            $queue
        );
        if ($supportsAsyncSignals) {
            $this->registerTimeoutHandler($job, $options);
        }

        try {
            // If we're able to pull a job off of the stack, we will process it and then return
            // from this method. If there is no job on the queue, we will "sleep" the worker
            // for the specified number of seconds, then keep processing jobs after sleep.
            if ($job) {
                return $this->runJob($job, $connectionName, $options);
            }
            $this->sleep($options->sleep);
        } finally {
            // Unlike daemon(), this method returns to a caller that keeps running, so a
            // pending alarm left behind would fire later during unrelated work and kill
            // that process.
            if ($supportsAsyncSignals) {
                $this->resetTimeoutHandler();
            }
        }
    }

    /**
     * Get the next job from the queue connection.
     *
     * @param CQueue_AbstractQueue $connection
     * @param string               $queue
     *
     * @return null|CQueue_AbstractJob
     */
    protected function getNextJob($connection, $queue) {
        $popJobCallback = function ($queue) use ($connection) {
            return $connection->pop($queue);
        };
        $connectionName = method_exists($connection, 'getConnectionName') ? $connection->getConnectionName() : null;
        $this->raiseBeforeJobPopEvent($connectionName, $queue);

        try {
            $workerName = $this->name === null ? '' : $this->name;
            if (isset(static::$popCallbacks[$workerName])) {
                $job = call_user_func_array(static::$popCallbacks[$workerName], [$popJobCallback, $queue]);
                if (!is_null($job)) {
                    $this->raiseAfterJobPopEvent($connectionName, $job);
                }

                return $job;
            }
            $queueList = explode(',', $queue);
            $pausedList = array_flip($this->getPausedQueues($connectionName, $queueList));
            foreach ($queueList as $queue) {
                if (isset($pausedList[$queue])) {
                    continue;
                }
                if (!is_null($job = $popJobCallback($queue))) {
                    $this->raiseAfterJobPopEvent($connectionName, $job);

                    return $job;
                }
            }
        } catch (Throwable $e) {
            $this->exceptions->report($e);
            if (CDaemon::isDaemon()) {
                // CDaemon::handleException() menandai errNo -1 sebagai fatal dan
                // meng-exit(1) proses daemon secara default - padahal blok ini sudah
                // memutuskan kegagalan pop job recoverable (sleep lalu retry di bawah).
                // Sama seperti runJob() (lihat 95400fc41): cukup log, jangan fatal.
                CDaemon::log('Get Next Job Exception :' . $e->getMessage());
            }
            $this->stopWorkerIfLostConnection($e);
            $this->sleep(1);
        }
    }

    /**
     * Determine which of the given queues are currently paused.
     *
     * Skipped entirely when the worker has no cache, so a worker that never got
     * one does not pay a cache lookup per poll.
     *
     * @param null|string $connectionName
     * @param array       $queueList
     *
     * @return array
     */
    protected function getPausedQueues($connectionName, $queueList) {
        if (!static::$pausable || $this->cache === null) {
            return [];
        }

        return $this->manager->getPausedQueues($connectionName, $queueList);
    }

    /**
     * Process the given job.
     *
     * @param CQueue_AbstractJob   $job
     * @param string               $connectionName
     * @param CQueue_WorkerOptions $options
     *
     * @return void
     */
    protected function runJob($job, $connectionName, CQueue_WorkerOptions $options) {
        try {
            $this->currentJob = $job;
            $this->currentJobName = $job->resolveName();

            return $this->process($connectionName, $job, $options);
        } catch (Throwable $e) {
            $this->currentJobName = null;
            if (!static::$reportJobExceptions) {
                $this->stopWorkerIfLostConnection($e);

                return;
            }
            if (CDaemon::getRunningService() != null) {
                CDaemon::log('Run Job Exception :' . $e->getMessage());
                if (!CF::isProduction()) {
                    CDaemon::log($e->getTraceAsString());
                }
                //Jejak di log daemon tetap ditulis apa pun jenisnya, karena di
                //sanalah riwayat satu daemon dibaca. Yang disaring adalah
                //pencatatan bertingkat ERROR: pengecualian yang sudah ditandai
                //"jangan dilaporkan" bukan kegagalan, dan sebelumnya tetap
                //tercatat sebagai ERROR di sini - kontraknya hanya dihormati
                //pada cabang non-daemon, yang memakai report().
                if ($this->exceptions->shouldReport($e)) {
                    CLogger::error('QueueException:' . $job->resolveName(), [
                        'job' => $job,
                        'connection' => $connectionName,
                        'options' => $options,
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    //Sebelumnya kegagalan job di dalam daemon tidak pernah
                    //sampai ke collector: hanya cabang non-daemon yang
                    //memanggil report(), padahal antrean CF lazimnya justru
                    //dijalankan daemon. Collector dipanggil langsung, bukan
                    //lewat report(), karena report() menulis lognya sendiri -
                    //memakainya di sini berarti dua baris ERROR untuk satu
                    //kegagalan, dan baris berkonteks di atas yang lebih
                    //berguna. collectException() punya penjaga konfigurasinya
                    //sendiri, jadi tetap diam ketika collector dimatikan.
                    CDebug::collector()->collectException($e);
                }
            } else {
                $this->exceptions->report($e);
            }
            $this->stopWorkerIfLostConnection($e);
        } finally {
            $this->currentJobName = null;
            $this->currentJob = null;
        }
    }

    /**
     * Stop the worker if we have lost connection to a database.
     *
     * @param Throwable $e
     *
     * @return void
     */
    protected function stopWorkerIfLostConnection($e) {
        if (static::$stopOnLostConnection && $this->causedByLostConnection($e)) {
            $this->lostConnection = true;
            $this->shouldQuit = true;
        }
    }

    /**
     * Process the given job from the queue.
     *
     * @param string               $connectionName
     * @param CQueue_AbstractJob   $job
     * @param CQueue_WorkerOptions $options
     *
     * @throws Throwable
     *
     * @return void
     */
    public function process($connectionName, $job, CQueue_WorkerOptions $options) {
        $exceptionOccurred = null;

        try {
            // First we will raise the before job event and determine if the job has already ran
            // over its maximum attempt limits, which could primarily happen when this job is
            // continually timing out and not actually throwing any exceptions from itself.
            $this->raiseBeforeJobEvent($connectionName, $job);
            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
                $connectionName,
                $job,
                (int) $options->maxTries
            );
            if ($job->isDeleted()) {
                return $this->raiseAfterJobEvent($connectionName, $job);
            }
            // Here we will fire off the job and let it process. We will catch any exceptions so
            // they can be reported to the developers logs, etc. Once the job is finished the
            // proper events will be fired to let any listeners know this job has finished.
            $job->fire();

            $this->raiseAfterJobEvent($connectionName, $job);
        } catch (Throwable $e) {
            $exceptionOccurred = $e;

            $this->handleJobException($connectionName, $job, $options, $e);
        } finally {
            // Fires whatever the outcome was, unlike JobProcessed and JobFailed.
            $this->events->dispatch(new CQueue_Event_JobAttempted(
                $connectionName,
                $job,
                $exceptionOccurred
            ));
        }
    }

    /**
     * Handle an exception that occurred while the job was running.
     *
     * @param string               $connectionName
     * @param CQueue_JobInterface  $job
     * @param CQueue_WorkerOptions $options
     * @param Exception            $e
     *
     * @throws Exception
     *
     * @return void
     */
    protected function handleJobException($connectionName, $job, CQueue_WorkerOptions $options, $e) {
        try {
            // First, we will go ahead and mark the job as failed if it will exceed the maximum
            // attempts it is allowed to run the next time we process it. If so we will just
            // go ahead and mark it as failed now so we do not have to release this again.

            if (!$job->hasFailed()) {
                $this->markJobAsFailedIfWillExceedMaxAttempts(
                    $connectionName,
                    $job,
                    (int) $options->maxTries,
                    $e
                );
                $this->markJobAsFailedIfWillExceedMaxExceptions(
                    $connectionName,
                    $job,
                    $e
                );
                $this->markJobAsFailedIfItShouldntBeRetried(
                    $connectionName,
                    $job,
                    $e
                );
            }
            $this->raiseExceptionOccurredJobEvent(
                $connectionName,
                $job,
                $e
            );
        } finally {
            // If we catch an exception, we will attempt to release the job back onto the queue
            // so it is not lost entirely. This'll let the job be retried at a later time by
            // another listener (or this same one). We will re-throw this exception after.
            //
            // The event belongs inside this branch: it used to fire on every exception,
            // announcing a release that had not happened whenever the job was deleted,
            // already released, or failed.
            if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                $backoff = $this->calculateBackoff($job, $options);
                $job->release($backoff);
                $this->events->dispatch(new CQueue_Event_JobReleasedAfterException(
                    $connectionName,
                    $job,
                    $backoff,
                    $e
                ));
            }
        }

        throw $e;
    }

    /**
     * Mark the given job as failed if the exception handler says it should not be retried.
     *
     * @param string              $connectionName
     * @param CQueue_JobInterface $job
     * @param Throwable           $e
     *
     * @return void
     */
    protected function markJobAsFailedIfItShouldntBeRetried($connectionName, $job, $e) {
        if ($this->exceptions->shouldStopRetries($e)) {
            $this->failJob($job, $e);
        }
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * This will likely be because the job previously exceeded a timeout.
     *
     * @param string             $connectionName
     * @param CQueue_AbstractJob $job
     * @param int                $maxTries
     *
     * @return void
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts($connectionName, $job, $maxTries) {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;
        $retryUntil = $job->retryUntil();
        if ($retryUntil && CCarbon::now()->getTimestamp() <= $retryUntil) {
            return;
        }

        if (!$retryUntil && ($maxTries === 0 || $job->attempts() <= $maxTries)) {
            return;
        }
        $this->failJob($job, $e = $this->maxAttemptsExceededException($job));

        throw $e;
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param string             $connectionName
     * @param CQueue_AbstractJob $job
     * @param int                $maxTries
     * @param Exception          $e
     *
     * @return void
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts($connectionName, $job, $maxTries, $e) {
        $maxTries = !is_null($job->maxTries()) ? $job->maxTries() : $maxTries;

        if ($job->retryUntil() && $job->retryUntil() <= CCarbon::now()->getTimestamp()) {
            $this->failJob($job, $e);
        }

        if (!$job->retryUntil() && $maxTries > 0 && $job->attempts() >= $maxTries) {
            $this->failJob($job, $e);
        }
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param string              $connectionName
     * @param CQueue_JobInterface $job
     * @param Throwable           $e
     *
     * @return void
     */
    protected function markJobAsFailedIfWillExceedMaxExceptions($connectionName, $job, $e) {
        if (!$this->cache || is_null($uuid = $job->uuid())
            || is_null($maxExceptions = $job->maxExceptions())
        ) {
            return;
        }

        if (!$this->cache->get('job-exceptions:' . $uuid)) {
            $this->cache->put('job-exceptions:' . $uuid, 0, CCarbon::now()->addDay());
        }

        if ($maxExceptions <= $this->cache->increment('job-exceptions:' . $uuid)) {
            $this->cache->forget('job-exceptions:' . $uuid);

            $this->failJob($job, $e);
        }
    }

    /**
     * Mark the given job as failed if it should fail on timeouts.
     *
     * @param string              $connectionName
     * @param CQueue_JobInterface $job
     * @param Throwable           $e
     *
     * @return void
     */
    protected function markJobAsFailedIfItShouldFailOnTimeout($connectionName, $job, $e) {
        if (method_exists($job, 'shouldFailOnTimeout') ? $job->shouldFailOnTimeout() : false) {
            $this->failJob($job, $e);
        }
    }

    /**
     * Mark the given job as failed and raise the relevant event.
     *
     * @param CQueue_AbstractJob $job
     * @param Exception          $e
     *
     * @return void
     */
    protected function failJob($job, $e) {
        return $job->fail($e);
    }

    /**
     * Calculate the backoff for the given job.
     *
     * @param CQueue_JobInterface  $job
     * @param CQueue_WorkerOptions $options
     *
     * @return int
     */
    protected function calculateBackoff($job, CQueue_WorkerOptions $options) {
        $backoff = explode(
            ',',
            method_exists($job, 'backoff') && !is_null($job->backoff())
                        ? $job->backoff()
                        : $options->backoff
        );

        return (int) (isset($backoff[$job->attempts() - 1]) ? $backoff[$job->attempts() - 1] : c::last($backoff));
    }

    /**
     * Raise an event indicating the worker is starting.
     *
     * @param string               $connectionName
     * @param string               $queue
     * @param CQueue_WorkerOptions $options
     *
     * @return void
     */
    protected function raiseWorkerStartingEvent($connectionName, $queue, $options) {
        $this->events->dispatch(new CQueue_Event_WorkerStarting($connectionName, $queue, $options));
    }

    /**
     * Raise an event indicating a job is about to be popped off the queue.
     *
     * @param null|string $connectionName
     * @param null|string $queue
     *
     * @return void
     */
    protected function raiseBeforeJobPopEvent($connectionName, $queue = null) {
        $this->events->dispatch(new CQueue_Event_JobPopping($connectionName, $queue));
    }

    /**
     * Raise an event indicating a job has been popped off the queue.
     *
     * @param null|string              $connectionName
     * @param null|CQueue_JobInterface $job
     *
     * @return void
     */
    protected function raiseAfterJobPopEvent($connectionName, $job) {
        $this->events->dispatch(new CQueue_Event_JobPopped($connectionName, $job));
    }

    /**
     * Raise the before queue job event.
     *
     * @param string             $connectionName
     * @param CQueue_AbstractJob $job
     *
     * @return void
     */
    protected function raiseBeforeJobEvent($connectionName, $job) {
        $this->events->dispatch(new CQueue_Event_JobProcessing(
            $connectionName,
            $job
        ));
    }

    /**
     * Raise the after queue job event.
     *
     * @param string             $connectionName
     * @param CQueue_AbstractJob $job
     *
     * @return void
     */
    protected function raiseAfterJobEvent($connectionName, $job) {
        $this->events->dispatch(new CQueue_Event_JobProcessed(
            $connectionName,
            $job
        ));
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param string             $connectionName
     * @param CQueue_AbstractJob $job
     * @param Exception          $e
     *
     * @return void
     */
    protected function raiseExceptionOccurredJobEvent($connectionName, $job, $e) {
        $this->events->dispatch(new CQueue_Event_JobExceptionOccurred(
            $connectionName,
            $job,
            $e
        ));
    }

    /**
     * Determine if the queue worker should restart.
     *
     * @param null|int $lastRestart
     *
     * @return bool
     */
    protected function queueShouldRestart($lastRestart) {
        if (!static::$restartable) {
            return false;
        }

        return $this->getTimestampOfLastQueueRestart() != $lastRestart;
    }

    /**
     * Get the last queue restart timestamp, or null.
     *
     * @return null|int
     */
    protected function getTimestampOfLastQueueRestart() {
        if (!static::$restartable) {
            return null;
        }
        if ($this->cache) {
            return $this->cache->get('cresenity:queue:restart');
        }
    }

    /**
     * Enable async signals for the process.
     *
     * @param null|string               $connectionName
     * @param null|string               $queue
     * @param null|CQueue_WorkerOptions $options
     *
     * @return void
     */
    protected function listenForSignals($connectionName = null, $queue = null, $options = null) {
        pcntl_async_signals(true);
        foreach ([SIGQUIT, SIGTERM, SIGINT] as $signal) {
            pcntl_signal($signal, function ($signal) use ($connectionName, $queue, $options) {
                $this->shouldQuit = true;
                $this->events->dispatch(new CQueue_Event_WorkerInterrupted($signal, $connectionName, $queue, $options));
                $this->notifyJobOfSignal($signal);
            });
        }
        pcntl_signal(SIGUSR2, function () use ($connectionName, $queue, $options) {
            $this->paused = true;
            $this->events->dispatch(new CQueue_Event_WorkerPausing($connectionName, $queue, $options));
        });
        pcntl_signal(SIGCONT, function () use ($connectionName, $queue, $options) {
            $this->paused = false;
            $this->events->dispatch(new CQueue_Event_WorkerResuming($connectionName, $queue, $options));
        });
    }

    /**
     * Pass the signal on to the job that is running, if it wants to know.
     *
     * @param int $signal
     *
     * @return void
     */
    protected function notifyJobOfSignal($signal) {
        if (!$this->currentJob) {
            return;
        }
        $handler = $this->currentJob->getResolvedJob();
        if (!$handler instanceof CQueue_CallQueuedHandler) {
            return;
        }
        if (!method_exists($handler, 'getRunningCommand')) {
            return;
        }
        $command = $handler->getRunningCommand();
        if ($command instanceof CQueue_InterruptibleInterface) {
            $command->interrupted($signal);
        }
    }

    /**
     * Determine if "async" signals are supported.
     *
     * @return bool
     */
    protected function supportsAsyncSignals() {
        return extension_loaded('pcntl');
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param int $memoryLimit
     *
     * @return bool
     */
    public function memoryExceeded($memoryLimit) {
        return ((int) $memoryLimit) > 0 && $this->currentMemoryUsage() >= ((int) $memoryLimit);
    }

    /**
     * Stop listening and bail out of the script.
     *
     * Laravel 13 returns the status here and leaves the exit to its console
     * command. CF keeps the exit: the caller is CQueue_Runner, which has no exit
     * of its own, so returning would put a worker that just hit its memory limit
     * straight back into the same loop.
     *
     * @param int                       $status
     * @param null|CQueue_WorkerOptions $options
     * @param null|string               $reason one of the CQueue_WorkerStopReason constants
     *
     * @return void
     */
    public function stop($status = 0, $options = null, $reason = null) {
        $this->events->dispatch($this->stoppingEvent($status, $options, $reason));
        exit($status);
    }

    /**
     * Kill the process.
     *
     * @param int                       $status
     * @param null|CQueue_WorkerOptions $options
     * @param null|string               $reason one of the CQueue_WorkerStopReason constants
     *
     * @return void
     */
    public function kill($status = 0, $options = null, $reason = null) {
        $this->events->dispatch($this->stoppingEvent($status, $options, $reason));
        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }
        exit($status);
    }

    /**
     * @param int                       $status
     * @param null|CQueue_WorkerOptions $options
     * @param null|string               $reason
     *
     * @return CQueue_Event_WorkerStopping
     */
    protected function stoppingEvent($status, $options, $reason) {
        return new CQueue_Event_WorkerStopping(
            $status,
            $options,
            $reason,
            $this->jobsProcessed,
            $this->lastJobProcessedAt,
            $this->currentMemoryUsage()
        );
    }

    /**
     * Create an instance of MaxAttemptsExceededException.
     *
     * @param null|CQueue_JobInterface $job
     *
     * @return CQueue_Exception_MaxAttemptsExceededException
     */
    protected function maxAttemptsExceededException($job) {
        return CQueue_Exception_MaxAttemptsExceededException::forJob($job);
    }

    /**
     * Create an instance of TimeoutExceededException.
     *
     * @param CQueue_JobInterface $job
     *
     * @return CQueue_Exception_TimeoutExceededException
     */
    protected function timeoutExceededException($job) {
        return CQueue_Exception_TimeoutExceededException::forJob($job);
    }

    /**
     * Get the current high-resolution timestamp, in seconds.
     *
     * @return float
     */
    protected function currentTime() {
        return hrtime(true) / 1e9;
    }

    /**
     * Get the current memory usage, in MB.
     *
     * @return float
     */
    protected function currentMemoryUsage() {
        return memory_get_usage(true) / 1024 / 1024;
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @param int|float $seconds
     *
     * @return void
     */
    public function sleep($seconds) {
        if ($seconds < 1) {
            usleep($seconds * 1000000);
        } else {
            sleep($seconds);
        }
    }

    /**
     * Set the cache repository implementation.
     *
     * @param CCache_Repository $cache
     *
     * @return $this
     */
    public function setCache(?CCache_Repository $cache = null) {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Set the name of the worker.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name) {
        $this->name = $name;

        return $this;
    }

    /**
     * Register a callback to be executed to pick jobs.
     *
     * @param string   $workerName
     * @param callable $callback
     *
     * @return void
     */
    public static function popUsing($workerName, $callback) {
        if (is_null($callback)) {
            unset(static::$popCallbacks[$workerName]);
        } else {
            static::$popCallbacks[$workerName] = $callback;
        }
    }

    /**
     * Get the queue manager instance.
     *
     * @return CQueue_Manager
     */
    public function getManager() {
        return $this->manager;
    }

    /**
     * Set the queue manager instance.
     *
     * @param CQueue_Manager $manager
     *
     * @return void
     */
    public function setManager(CQueue_Manager $manager) {
        $this->manager = $manager;
    }

    public function getCurrentJobName() {
        return $this->currentJobName;
    }
}
