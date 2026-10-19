<?php

/**
 * Job Payload class
 * Copied from laravel horizon.
 */
class CDaemon_Supervisor_Queue_JobPayload implements ArrayAccess {
    /**
     * The raw payload string.
     *
     * @var string
     */
    public $value;

    /**
     * The decoded payload array.
     *
     * @var array
     */
    public $decoded;

    /**
     * Create a new raw job payload instance.
     *
     * @param string $value
     *
     * @return void
     */
    public function __construct($value) {
        $this->value = $value;

        $decoded = json_decode($value, true);
        $this->decoded = is_array($decoded) ? $decoded : [];
    }

    /**
     * Get the job ID from the payload.
     *
     * @return string
     */
    public function id() {
        return carr::get($this->decoded, 'uuid', carr::get($this->decoded, 'id'));
    }

    /**
     * Get the job tags from the payload.
     *
     * @return array
     */
    public function tags() {
        return carr::get($this->decoded, 'tags', []);
    }

    /**
     * Determine if the job is a retry of a previous job.
     *
     * @return bool
     */
    public function isRetry() {
        return isset($this->decoded['retry_of']);
    }

    /**
     * Get the ID of the job this job is a retry of.
     *
     * @return null|string
     */
    public function retryOf() {
        return $this->decoded['retry_of'] ?? null;
    }

    /**
     * Prepare the payload for storage on the queue by adding tags, etc.
     *
     * @param mixed $job
     *
     * @return $this
     */
    public function prepare($job) {
        return $this->set([
            'type' => $this->determineType($job),
            'tags' => $this->determineTags($job),
            'pushedAt' => str_replace(',', '.', microtime(true)),
        ]);
    }

    /**
     * Get the "type" of job being queued.
     *
     * @param mixed $job
     *
     * @return string
     */
    protected function determineType($job) {
        switch (true) {
            case $job instanceof CBroadcast_BroadcastEvent:
                return 'broadcast';
            case $job instanceof CEvent_CallQueuedListener:
                return 'event';
            case $job instanceof CEmail_TaskQueue_SendQueuedMailable:
                return 'mail';
            case $job instanceof CNotification_TaskQueue_NotificationSender:
                return 'notification';
            default:
                return 'job';
        }
    }

    /**
     * Get the appropriate tags for the job.
     *
     * @param mixed $job
     *
     * @return array
     */
    protected function determineTags($job) {
        return array_merge(
            $this->decoded['tags'] ?? [],
            !$job || is_string($job) ? [] : CDaemon_Supervisor_Queue_Tags::for($job)
        );
    }

    /**
     * Set the given key / value pairs on the payload.
     *
     * @param array $values
     *
     * @return $this
     */
    public function set(array $values) {
        $this->decoded = array_merge($this->decoded, $values);

        $this->value = json_encode($this->decoded);

        return $this;
    }

    /**
     * Get the "command name" for the job.
     *
     * @return string
     */
    public function commandName() {
        return carr::get($this->decoded, 'data.commandName');
    }

    /**
     * Get the "display name" for the job.
     *
     * @return string
     */
    public function displayName() {
        return carr::get($this->decoded, 'displayName');
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool {
        return array_key_exists($offset, $this->decoded);
    }

    /**
     * Get the value at the current offset.
     *
     * @param string $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->decoded[$offset];
    }

    /**
     * Set the value at the current offset.
     *
     * @param string $offset
     * @param mixed  $value
     *
     * @return void
     */
    public function offsetSet($offset, $value): void {
        $this->decoded[$offset] = $value;
    }

    /**
     * Unset the value at the current offset.
     *
     * @param string $offset
     *
     * @return void
     */
    public function offsetUnset($offset): void {
        unset($this->decoded[$offset]);
    }
}
