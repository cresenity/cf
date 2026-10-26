<?php

class CQueue_Queue_RedisQueue extends CQueue_AbstractQueue implements CQueue_Contract_ClearableQueueInterface {
    /**
     * The Redis factory implementation.
     *
     * @var \CRedis_FactoryInterface
     */
    protected $redis;

    /**
     * The connection name.
     *
     * @var string
     */
    protected $connection;

    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $default;

    /**
     * The expiration time of a job.
     *
     * @var null|int
     */
    protected $retryAfter = 60;

    /**
     * The maximum number of seconds to block for a job.
     *
     * @var null|int
     */
    protected $blockFor = null;

    /**
     * Create a new Redis queue instance.
     *
     * @param \CRedis_FactoryInterface $redis
     * @param string                   $default
     * @param null|string              $connection
     * @param int                      $retryAfter
     * @param null|int                 $blockFor
     * @param bool                     $dispatchAfterCommit
     *
     * @return void
     */
    public function __construct(
        CRedis_FactoryInterface $redis,
        $default = 'default',
        $connection = null,
        $retryAfter = 60,
        $blockFor = null,
        $dispatchAfterCommit = false
    ) {
        $this->redis = $redis;
        $this->default = $default;
        $this->blockFor = $blockFor;
        $this->connection = $connection;
        $this->retryAfter = $retryAfter;
        $this->dispatchAfterCommit = $dispatchAfterCommit;
    }

    /**
     * Get the size of the queue.
     *
     * @param null|string $queue
     *
     * @return int
     */
    public function size($queue = null) {
        $queue = $this->getQueue($queue);

        return $this->getConnection()->eval(
            CQueue_LuaScripts::size(),
            3,
            $queue,
            $queue . ':delayed',
            $queue . ':reserved'
        );
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param array       $jobs
     * @param mixed       $data
     * @param null|string $queue
     *
     * @return void
     */
    public function bulk($jobs, $data = '', $queue = null) {
        $this->getConnection()->pipeline(function () use ($jobs, $data, $queue) {
            $this->getConnection()->transaction(function () use ($jobs, $data, $queue) {
                foreach ((array) $jobs as $job) {
                    if (isset($job->delay)) {
                        $this->later($job->delay, $job, $data, $queue);
                    } else {
                        $this->push($job, $data, $queue);
                    }
                }
            });
        });
    }

    /**
     * Push a new job onto the queue.
     *
     * @param object|string $job
     * @param mixed         $data
     * @param null|string   $queue
     *
     * @return mixed
     */
    public function push($job, $data = '', $queue = null) {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            function ($payload, $queue) {
                return $this->pushRaw($payload, $queue);
            }
        );
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
        $this->getConnection()->eval(
            CQueue_LuaScripts::push(),
            2,
            $this->getQueue($queue),
            $this->getQueue($queue) . ':notify',
            $payload
        );

        return carr::get(json_decode($payload, true), 'id');
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param object|string                        $job
     * @param mixed                                $data
     * @param null|string                          $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null) {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            $delay,
            function ($payload, $queue, $delay) {
                return $this->laterRaw($delay, $payload, $queue);
            }
        );
    }

    /**
     * Push a raw job onto the queue after a delay.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string                               $payload
     * @param null|string                          $queue
     *
     * @return mixed
     */
    protected function laterRaw($delay, $payload, $queue = null) {
        $this->getConnection()->zadd(
            $this->getQueue($queue) . ':delayed',
            $this->availableAt($delay),
            $payload
        );

        $decoded = json_decode($payload, true);

        return isset($decoded['id']) ? $decoded['id'] : null;
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param string $job
     * @param string $queue
     * @param mixed  $data
     *
     * @return array
     */
    protected function createPayloadArray($job, $queue, $data = '') {
        return array_merge(parent::createPayloadArray($job, $queue, $data), [
            'id' => $this->getRandomId(),
            'attempts' => 0,
        ]);
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param null|string $queue
     *
     * @return null|\CQueue_JobInterface
     */
    public function pop($queue = null) {
        $this->migrate($prefixed = $this->getQueue($queue));

        list($job, $reserved) = $this->retrieveNextJob($prefixed);

        if ($reserved) {
            return new CQueue_Job_RedisJob(
                $this->container,
                $this,
                $job,
                $reserved,
                $this->connectionName,
                $queue ?: $this->default
            );
        }
    }

    /**
     * Migrate any delayed or expired jobs onto the primary queue.
     *
     * @param string $queue
     *
     * @return void
     */
    protected function migrate($queue) {
        $this->migrateExpiredJobs($queue . ':delayed', $queue);

        if (!is_null($this->retryAfter)) {
            $this->migrateExpiredJobs($queue . ':reserved', $queue);
        }
    }

    /**
     * Migrate the delayed jobs that are ready to the regular queue.
     *
     * @param string $from
     * @param string $to
     *
     * @return array
     */
    public function migrateExpiredJobs($from, $to) {
        return $this->getConnection()->eval(
            CQueue_LuaScripts::migrateExpiredJobs(),
            3,
            $from,
            $to,
            $to . ':notify',
            $this->currentTime()
        );
    }

    /**
     * Retrieve the next job from the queue.
     *
     * @param string $queue
     * @param bool   $block
     *
     * @return array
     */
    protected function retrieveNextJob($queue, $block = true) {
        $nextJob = $this->getConnection()->eval(
            CQueue_LuaScripts::pop(),
            3,
            $queue,
            $queue . ':reserved',
            $queue . ':notify',
            $this->availableAt($this->retryAfter)
        );

        if (empty($nextJob)) {
            return [null, null];
        }

        //$nextJob datang dari EVAL Lua (CQueue_LuaScripts::pop()) - jangan destructure
        //langsung dengan list(), reply Redis untuk tabel Lua kadang pendek dari 2 elemen
        //yang diharapkan.
        $job = carr::get($nextJob, 0);
        $reserved = carr::get($nextJob, 1);

        if (!$job && !is_null($this->blockFor) && $block
            && $this->getConnection()->blpop([$queue . ':notify'], $this->blockFor)
        ) {
            return $this->retrieveNextJob($queue, false);
        }

        return [$job, $reserved];
    }

    /**
     * Delete a reserved job from the queue.
     *
     * @param string               $queue
     * @param \CQueue_Job_RedisJob $job
     *
     * @return void
     */
    public function deleteReserved($queue, $job) {
        $this->getConnection()->zrem($this->getQueue($queue) . ':reserved', $job->getReservedJob());
    }

    /**
     * Delete a reserved job from the reserved queue and release it.
     *
     * @param string               $queue
     * @param \CQueue_Job_RedisJob $job
     * @param int                  $delay
     *
     * @return void
     */
    public function deleteAndRelease($queue, $job, $delay) {
        $queue = $this->getQueue($queue);

        $this->getConnection()->eval(
            CQueue_LuaScripts::release(),
            2,
            $queue . ':delayed',
            $queue . ':reserved',
            $job->getReservedJob(),
            $this->availableAt($delay)
        );
    }

    /**
     * Delete all of the jobs from the queue.
     *
     * @param string $queue
     *
     * @return int
     */
    public function clear($queue) {
        $queue = $this->getQueue($queue);

        return $this->getConnection()->eval(
            CQueue_LuaScripts::clear(),
            4,
            $queue,
            $queue . ':delayed',
            $queue . ':reserved',
            $queue . ':notify'
        );
    }

    /**
     * Get a random ID string.
     *
     * @return string
     */
    protected function getRandomId() {
        return cstr::random(32);
    }

    /**
     * Get the queue or return the default.
     *
     * @param null|string $queue
     *
     * @return string
     */
    public function getQueue($queue) {
        return 'queues:' . ($queue ?: $this->default);
    }

    /**
     * Get the connection for the queue.
     *
     * @return \CRedis_AbstractConnection
     */
    public function getConnection() {
        return $this->redis->connection($this->connection);
    }

    /**
     * Get the underlying Redis instance.
     *
     * @return \CRedis_FactoryInterface
     */
    public function getRedis() {
        return $this->redis;
    }
}
