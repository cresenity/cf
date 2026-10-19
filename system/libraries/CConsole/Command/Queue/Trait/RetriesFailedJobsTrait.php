<?php

/**
 * Push failed jobs back onto their queue (queue:retry, queue:retry-batch).
 */
trait CConsole_Command_Queue_Trait_RetriesFailedJobsTrait {
    /**
     * @return int number of jobs pushed back
     */
    protected function retryFailedJobs(array $ids) {
        $failer = CQueue::failer();
        $retried = 0;
        foreach ($ids as $id) {
            $job = $failer->find($id);
            if (is_null($job)) {
                $this->error("Unable to find failed job with ID [{$id}].");

                continue;
            }
            CEvent::dispatcher()->dispatch(new CQueue_Event_JobRetryRequested($job));
            $this->retryJob($job);
            $failer->forget($id);
            $this->line("Failed job [{$id}] pushed back onto the [{$job->queue}] queue.");
            $retried++;
        }

        return $retried;
    }

    /**
     * @param object $job
     *
     * @return void
     */
    protected function retryJob($job) {
        CQueue::queuer()->connection($job->connection)->pushRaw(
            $this->refreshRetryUntil($this->resetAttempts($job->payload)),
            $job->queue
        );
    }

    /**
     * Reset the payload attempts.
     *
     * Applicable to Redis and other jobs which store attempts in their payload.
     *
     * @param string $payload
     *
     * @return string
     */
    protected function resetAttempts($payload) {
        $payload = json_decode($payload, true);

        if (isset($payload['attempts'])) {
            $payload['attempts'] = 0;
        }

        return json_encode($payload);
    }

    /**
     * Get the job's retryUntil from its command, so a retried job is not expired at once.
     *
     * @param string $payload
     *
     * @throws RuntimeException
     *
     * @return string
     */
    protected function refreshRetryUntil($payload) {
        $payload = json_decode($payload, true);

        if (!isset($payload['data']['command'])) {
            return json_encode($payload);
        }

        if (cstr::startsWith($payload['data']['command'], 'O:')) {
            $instance = unserialize($payload['data']['command']);
        } else {
            $instance = unserialize(CCrypt::encrypter()->decrypt($payload['data']['command']));
        }

        if (!is_object($instance)) {
            throw new RuntimeException('Unable to extract job payload.');
        }

        if (!$instance instanceof __PHP_Incomplete_Class && method_exists($instance, 'retryUntil')) {
            $retryUntil = $instance->retryUntil();

            $payload['retryUntil'] = $retryUntil instanceof DateTimeInterface
                ? $retryUntil->getTimestamp()
                : $retryUntil;
        }

        return json_encode($payload);
    }
}
