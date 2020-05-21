<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since Sep 8, 2019, 4:31:49 AM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
abstract class CQueue_AbstractQueue implements CQueue_QueueInterface{

    use CTrait_Helper_InteractsWithTime;

    /**
     * The IoC container instance.
     *
     * @var CContainer_Container
     */
    protected $container;

    /**
     * The connection name for the queue.
     *
     * @var string
     */
    protected $connectionName;

    /**
     * The create payload callbacks.
     *
     * @var callable[]
     */
    protected static $createPayloadCallbacks = [];

    /**
     * Push a new job onto the queue.
     *
     * @param  string  $queue
     * @param  string  $job
     * @param  mixed   $data
     * @return mixed
     */
    public function pushOn($queue, $job, $data = '') {
        return $this->push($job, $data, $queue);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  string  $queue
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string  $job
     * @param  mixed   $data
     * @return mixed
     */
    public function laterOn($queue, $delay, $job, $data = '') {
        return $this->later($delay, $job, $data, $queue);
    }

    /**
     * Push an array of jobs onto the queue.
     *
     * @param  array   $jobs
     * @param  mixed   $data
     * @param  string|null  $queue
     * @return void
     */
    public function bulk($jobs, $data = '', $queue = null) {
        foreach ((array) $jobs as $job) {
            $this->push($job, $data, $queue);
        }
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param  string|object  $job
     * @param  string  $queue
     * @param  mixed   $data
     * @return string
     *
     * @throws \Illuminate\Queue\InvalidPayloadException
     */
    protected function createPayload($job, $queue, $data = '') {
        $payload = json_encode($this->createPayloadArray($job, $queue, $data));
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new InvalidPayloadException(
            'Unable to JSON encode payload. Error code: ' . json_last_error()
            );
        }
        return $payload;
    }

    /**
     * Create a payload array from the given job and data.
     *
     * @param  string|object  $job
     * @param  string  $queue
     * @param  mixed  $data
     * @return array
     */
    protected function createPayloadArray($job, $queue, $data = '') {
        return is_object($job) ? $this->createObjectPayload($job, $queue) : $this->createStringPayload($job, $queue, $data);
    }

    /**
     * Create a payload for an object-based queue handler.
     *
     * @param  object  $job
     * @param  string  $queue
     * @return array
     */
    protected function createObjectPayload($job, $queue) {
        $payload = $this->withCreatePayloadHooks($queue, [
            'displayName' => $this->getDisplayName($job),
            'job' => 'CQueue_CallQueuedHandler@call',
            'maxTries' => isset($job->tries) ? $job->tries : null,
            'delay' => $this->getJobRetryDelay($job),
            'timeout' => isset($job->timeout) ? $job->timeout : null,
            'timeoutAt' => $this->getJobExpiration($job),
            'data' => [
                'commandName' => $job,
                'command' => $job,
            ],
        ]);
        return array_merge($payload, [
            'data' => [
                'commandName' => get_class($job),
                'command' => serialize(clone $job),
            ],
        ]);
    }

    /**
     * Get the display name for the given job.
     *
     * @param  object  $job
     * @return string
     */
    protected function getDisplayName($job) {
        return method_exists($job, 'displayName') ? $job->displayName() : get_class($job);
    }

    /**
     * Get the retry delay for an object-based queue handler.
     *
     * @param  mixed  $job
     * @return mixed
     */
    public function getJobRetryDelay($job) {
        if (!method_exists($job, 'retryAfter') && !isset($job->retryAfter)) {
            return;
        }
        $delay = isset($job->retryAfter) ? $job->retryAfter : $job->retryAfter();
        return $delay instanceof DateTimeInterface ? $this->secondsUntil($delay) : $delay;
    }

    /**
     * Get the expiration timestamp for an object-based queue handler.
     *
     * @param  mixed  $job
     * @return mixed
     */
    public function getJobExpiration($job) {
        if (!method_exists($job, 'retryUntil') && !isset($job->timeoutAt)) {
            return;
        }
        $expiration = isset($job->timeoutAt) ? $job->timeoutAt : $job->retryUntil();
        return $expiration instanceof DateTimeInterface ? $expiration->getTimestamp() : $expiration;
    }

    /**
     * Create a typical, string based queue payload array.
     *
     * @param  string  $job
     * @param  string  $queue
     * @param  mixed  $data
     * @return array
     */
    protected function createStringPayload($job, $queue, $data) {
        return $this->withCreatePayloadHooks($queue, [
                    'displayName' => is_string($job) ? explode('@', $job)[0] : null,
                    'job' => $job,
                    'maxTries' => null,
                    'delay' => null,
                    'timeout' => null,
                    'data' => $data,
        ]);
    }

    /**
     * Register a callback to be executed when creating job payloads.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function createPayloadUsing($callback) {
        if (is_null($callback)) {
            static::$createPayloadCallbacks = [];
        } else {
            static::$createPayloadCallbacks[] = $callback;
        }
    }

    /**
     * Create the given payload using any registered payload hooks.
     *
     * @param  string  $queue
     * @param  array  $payload
     * @return array
     */
    protected function withCreatePayloadHooks($queue, array $payload) {
        if (!empty(static::$createPayloadCallbacks)) {
            foreach (static::$createPayloadCallbacks as $callback) {
                $payload = array_merge($payload, call_user_func(
                                $callback, $this->getConnectionName(), $queue, $payload
                ));
            }
        }
        return $payload;
    }

    /**
     * Get the connection name for the queue.
     *
     * @return string
     */
    public function getConnectionName() {
        return $this->connectionName;
    }

    /**
     * Set the connection name for the queue.
     *
     * @param  string  $name
     * @return $this
     */
    public function setConnectionName($name) {
        $this->connectionName = $name;
        return $this;
    }

    /**
     * Set the IoC container instance.
     *
     * @param  CContainer_Container  $container
     * @return void
     */
    public function setContainer(CContainer_Container $container) {
        $this->container = $container;
    }

}
