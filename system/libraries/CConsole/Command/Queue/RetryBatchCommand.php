<?php

class CConsole_Command_Queue_RetryBatchCommand extends CConsole_Command {
    use CConsole_Command_Queue_Trait_RetriesFailedJobsTrait;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'queue:retry-batch {id* : The ID of the batch whose failed jobs should be retried}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry the failed jobs for a batch';

    /**
     * Execute the console command.
     *
     * @return null|int
     */
    public function handle() {
        $failed = false;
        foreach (array_unique((array) $this->argument('id')) as $id) {
            $batch = CQueue::batchRepository()->find($id);

            if (!$batch) {
                $this->error("Unable to find a batch with ID [{$id}].");
                $failed = true;
            } elseif (empty($batch->failedJobIds)) {
                $this->error('The given batch does not contain any failed jobs.');
                $failed = true;
            } else {
                $this->info("Pushing failed queue jobs of the batch [{$id}] back onto the queue.");
                $this->retryFailedJobs($this->failedJobIdsOf($batch));
            }
        }

        return $failed ? 1 : 0;
    }

    /**
     * Batch::$failedJobIds are job uuids; the database failer keys by its own id.
     *
     * @param CQueue_Batch $batch
     *
     * @return array
     */
    protected function failedJobIdsOf($batch) {
        $failer = CQueue::failer();
        if (!method_exists($failer, 'findIdByUuid')) {
            return $batch->failedJobIds;
        }

        return c::collect($batch->failedJobIds)->map(function ($uuid) use ($failer) {
            $id = $failer->findIdByUuid($uuid);
            if (is_null($id)) {
                $this->error("Unable to find failed job with UUID [{$uuid}].");
            }

            return $id;
        })->filter()->values()->all();
    }
}
