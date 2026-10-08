<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Elastic pool of forked child processes for a single daemon -- opt-in via
 * addPlugin('AutoScalePool'), built entirely on the existing
 * CDaemon_Plugin_ProcessManager primitives (fork/count/reap). No change to
 * CDaemon_Helper, CManager_Daemon or TBDaemon: a daemon that never calls
 * addPlugin('AutoScalePool') is completely unaffected.
 *
 * `decide` is app-supplied and only decides a target count; `min`/`max` always
 * clamp it, so a careless callback cannot grow the pool past what the server
 * can safely absorb. `every` throttles how often `decide` runs (it is cheap
 * for the plugin to check, but `decide` itself may hit an external API).
 * `overlapping=false` serializes `work` itself across the whole pool via the
 * same cache-backed mutex CCron_CacheEventMutex uses for ->withoutOverlapping()
 * -- several standby processes, but only one actually executing at a time.
 */
class CDaemon_Plugin_AutoScalePool extends CDaemon_PluginAbstract {
    /**
     * @var CDaemon_ServiceAbstract
     */
    public $service;

    /**
     * @var string
     */
    protected $group = 'default';

    /**
     * @var int
     */
    protected $min = 1;

    /**
     * @var int
     */
    protected $max = 1;

    /**
     * @var int
     */
    protected $every = 60;

    /**
     * @var null|callable
     */
    protected $decide;

    /**
     * @var null|callable
     */
    protected $work;

    /**
     * @var bool
     */
    protected $overlapping = true;

    /**
     * @var null|string
     */
    protected $mutexStore;

    /**
     * How long to keep re-asking a worker to stop (letting
     * CDaemon_Process::stop()'s own SIGTERM-then-SIGKILL escalation run)
     * before this plugin gives up waiting and kills it directly. A real fork
     * smoke test caught the handler reacting to SIGTERM in well under a
     * second but the OS still took ~3s to actually reap one of two children
     * -- both had already executed exit(0); a process that had inherited a
     * live DB/cache connection from the bootstrapped framework process it
     * was forked from simply took that long to finish closing it during
     * PHP's own shutdown sequence. 10s leaves real margin over that without
     * making a stuck worker linger for minutes.
     *
     * @var int seconds
     */
    protected $stopTimeout = 10;

    public function __construct(CDaemon_ServiceAbstract $service) {
        $this->service = $service;
    }

    public function __destruct() {
        unset($this->service);
    }

    /**
     * @return void
     */
    public function setup() {
    }

    /**
     * @return void
     */
    public function teardown() {
        if (!isset($this->service)) {
            return;
        }
        // $service->__destruct() -> teardown() on every plugin runs on BOTH
        // the master's own shutdown AND a forked child's exit() (the child
        // inherits the whole plugin graph) -- confirmed with a real fork
        // smoke test: without this guard, a child calling exit(0) re-entered
        // stopAll() on its own inherited (stale) ProcessManager state and
        // blocked for the full stopTimeout before the master ever saw it
        // reaped. Only the master process should ever stop the pool's
        // children here.
        if (!$this->service->isParent()) {
            return;
        }
        $this->stopAll();
    }

    /**
     * @param array $errors
     *
     * @return array
     */
    public function checkEnvironment(array $errors = []) {
        if (function_exists('pcntl_fork') == false) {
            $errors[] = 'CDaemon_Plugin_AutoScalePool requires the PCNTL extension';
        }

        return $errors;
    }

    /**
     * @param array{group?: string, min?: int, max?: int, every?: int, decide?: callable, work: callable, overlapping?: bool} $options
     *
     * @throws Exception
     *
     * @return $this
     */
    public function configure(array $options) {
        $this->group = (string) carr::get($options, 'group', 'default');
        $this->min = max(0, (int) carr::get($options, 'min', 1));
        $this->max = max($this->min, (int) carr::get($options, 'max', $this->min));
        $this->every = max(1, (int) carr::get($options, 'every', 60));
        $this->decide = carr::get($options, 'decide');
        $this->work = carr::get($options, 'work');
        $this->overlapping = (bool) carr::get($options, 'overlapping', true);
        $this->mutexStore = carr::get($options, 'mutexStore');
        $this->stopTimeout = max(1, (int) carr::get($options, 'stopTimeout', $this->stopTimeout));

        if (!is_callable($this->work)) {
            throw new Exception(__METHOD__ . ' requires a callable `work`');
        }
        if ($this->decide !== null && !is_callable($this->decide)) {
            throw new Exception(__METHOD__ . ' `decide` must be callable when given');
        }

        $this->service->on(CDaemon_ServiceAbstract::ON_IDLE, [$this, 'tick'], $this->every);

        return $this;
    }

    /**
     * @return CDaemon_Plugin_ProcessManager
     */
    protected function processManager() {
        return $this->service->getPlugin('ProcessManager');
    }

    /**
     * @return int currently alive worker count in this pool's group.
     */
    public function count() {
        return $this->processManager()->count($this->group);
    }

    /**
     * Clamp a raw desired count into [min, max] -- pure, no side effect, so it
     * can be unit-tested without forking anything.
     *
     * @param int $desired
     *
     * @return int
     */
    public function clamp($desired) {
        return max($this->min, min($this->max, (int) $desired));
    }

    /**
     * Reap dead children, ask `decide` for a target (defaulting to the
     * current count, i.e. no-op, when `decide` is not given), clamp it, and
     * fork/stop the difference. Registered on ON_IDLE with the `every`
     * throttle by configure(); can also be called directly in tests.
     *
     * @return void
     */
    public function tick() {
        $this->processManager()->reap();
        $current = $this->count();
        $desired = is_callable($this->decide) ? call_user_func($this->decide, $current) : $current;
        $this->scaleTo($this->clamp($desired));
    }

    /**
     * @param int $target
     *
     * @return void
     */
    protected function scaleTo($target) {
        $current = $this->count();
        if ($target > $current) {
            for ($i = $current; $i < $target; $i++) {
                $this->forkOne();
            }
        } elseif ($target < $current) {
            $this->stopSome($current - $target);
        }
    }

    /**
     * @return void
     */
    protected function forkOne() {
        // fork() returns the literal boolean true to the CHILD, but a
        // CDaemon_Process OBJECT to the parent -- an object is truthy too, so
        // this must be a strict === true, or the parent takes this branch as
        // well and never returns from scaleTo() (caught by a real fork smoke
        // test: both parent and child ended up looping inside runChild()).
        if ($this->processManager()->fork($this->group) === true) {
            // Code from here on runs in the CHILD process only -- ON_FORK has
            // already been dispatched by ProcessManager::fork() before it
            // returned true to us.
            $this->runChild();
            exit(0);
        }
        // Parent continues here with a CDaemon_Process (or false if the fork
        // itself failed -- left alone, the next tick() will try again if the
        // backlog that asked for it is still there).
    }

    /**
     * @param int $count
     *
     * @return void
     */
    protected function stopSome($count) {
        $processes = $this->processManager()->processes($this->group);
        // Newest first -- arbitrary but deterministic; the pool is a set of
        // interchangeable workers, not an ordered queue, so which ones are
        // asked to stop first doesn't matter.
        krsort($processes);
        $this->waitForStop(array_slice($processes, 0, $count, true));
    }

    /**
     * CDaemon_Process::stop() is designed to be called repeatedly -- the
     * first call sends SIGTERM and records stop_time; a call made again
     * after $process->timeout() has elapsed escalates to SIGKILL. Calling it
     * exactly once (as a naive caller would) leaves a worker that doesn't
     * exit promptly simply running forever -- confirmed with a real fork in
     * a manual smoke test, not just reasoned about: one of two forked
     * children needed a hard SIGKILL from outside this class after a single
     * stop() call left it alive past the window the caller was willing to
     * wait. This re-calls stop() on whatever is still alive until either
     * every target is reaped or $stopTimeout elapses, so the escalation
     * CDaemon_Process already implements actually gets to run.
     *
     * @param CDaemon_Process[] $processes keyed by pid, as processes()
     *                                      returns them
     *
     * @return void
     */
    protected function waitForStop(array $processes) {
        if (count($processes) === 0) {
            return;
        }
        $deadline = microtime(true) + $this->stopTimeout;
        do {
            foreach ($processes as $process) {
                /* @var CDaemon_Process $process */
                $process->stop();
            }
            $this->processManager()->reap();
            $stillTracked = $this->processManager()->processes($this->group);
            $processes = array_intersect_key($processes, $stillTracked);
            if (count($processes) === 0) {
                return;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);
        // Still here past the deadline -- CDaemon_Process::stop() itself only
        // escalates to kill() once ITS OWN timeout() has elapsed, which may be
        // longer than $stopTimeout; force it directly rather than silently
        // leaving an orphan.
        foreach ($processes as $process) {
            $process->kill();
        }
        $this->processManager()->reap();
    }

    /**
     * Runs inside the forked child until signalled. Registers its own signal
     * handler -- the parent's handler (bound to $this->service's master-process
     * state) is not meaningful to re-run here.
     *
     * @return void
     */
    protected function runChild() {
        $stop = false;
        $handler = function () use (&$stop) {
            $stop = true;
        };
        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);

        while (!$stop) {
            pcntl_signal_dispatch();
            if ($stop) {
                break;
            }
            if (!$this->runOneUnitOfWork()) {
                usleep(200000);
            }
        }
    }

    /**
     * One iteration of `work` -- pulled out of runChild()'s loop so the
     * overlapping=false mutex dance can be exercised directly in a test
     * without actually forking a process.
     *
     * @return bool false when overlapping=false and the mutex was already
     *              held elsewhere (caller should back off before retrying),
     *              true otherwise (work ran, or overlapping=true so there was
     *              never a mutex to contend for).
     */
    public function runOneUnitOfWork() {
        if (!$this->overlapping && !$this->acquireMutex()) {
            return false;
        }
        try {
            call_user_func($this->work);
        } finally {
            if (!$this->overlapping) {
                $this->releaseMutex();
            }
        }

        return true;
    }

    /**
     * @return string
     */
    protected function mutexKey() {
        return 'daemon_autoscale_pool_' . CDaemon_Helper::escape(get_class($this->service) . '_' . $this->group);
    }

    /**
     * Same cache-backed mutex CCron_CacheEventMutex uses for
     * ->withoutOverlapping() -- reused rather than inventing a second locking
     * mechanism. TTL is generous (5 minutes): a worker that dies mid-`work()`
     * without releasing simply lets the lock expire instead of wedging the
     * pool forever.
     *
     * @return bool
     */
    protected function acquireMutex() {
        return CCache::manager()->store($this->mutexStore)->add($this->mutexKey(), getmypid(), 300);
    }

    /**
     * @return void
     */
    protected function releaseMutex() {
        CCache::manager()->store($this->mutexStore)->forget($this->mutexKey());
    }

    /**
     * Stop every worker currently in this pool's group -- called from
     * teardown() so a stopped master daemon doesn't leave orphaned children
     * behind.
     *
     * @return void
     */
    public function stopAll() {
        $this->waitForStop($this->processManager()->processes($this->group));
    }
}
