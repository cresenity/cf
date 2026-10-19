<?php

class CConsole_Command_Queue_RetryCommand extends CConsole_Command {
    use CConsole_Command_Queue_Trait_RetriesFailedJobsTrait;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'queue:retry
                            {id?* : The ID of the failed job or "all" to retry all jobs}
                            {--queue= : Retry all of the failed jobs for the specified queue}
                            {--range=* : Range of job IDs (numeric) to be retried (e.g. 1-5)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry a failed queue job';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle() {
        $ids = $this->getJobIds();
        if (count($ids) === 0) {
            $this->info('No retryable jobs found.');

            return;
        }

        $this->info('Pushing failed queue jobs back onto the queue.');
        $this->retryFailedJobs($ids);
    }

    /**
     * Get the job IDs to be retried.
     *
     * @return array
     */
    protected function getJobIds() {
        $ids = (array) $this->argument('id');

        if (count($ids) === 1 && $ids[0] === 'all') {
            return $this->failedJobIds();
        }

        if ($queue = $this->option('queue')) {
            $ids = array_merge($ids, $this->getJobIdsByQueue($queue));
        }

        if ($ranges = (array) $this->option('range')) {
            $ids = array_merge($ids, $this->getJobIdsByRanges($ranges));
        }

        return array_values(array_filter(array_unique($ids)));
    }

    /**
     * @param null|string $queue
     *
     * @return array
     */
    protected function failedJobIds($queue = null) {
        $failer = CQueue::failer();
        if (method_exists($failer, 'ids')) {
            return $failer->ids($queue);
        }

        return c::collect($failer->all())->when(!is_null($queue), function ($jobs) use ($queue) {
            return $jobs->where('queue', $queue);
        })->pluck('id')->all();
    }

    /**
     * Get the job IDs by queue, if applicable.
     *
     * @param string $queue
     *
     * @return array
     */
    protected function getJobIdsByQueue($queue) {
        $ids = $this->failedJobIds($queue);

        if (count($ids) === 0) {
            $this->error("Unable to find failed jobs for queue [{$queue}].");
        }

        return $ids;
    }

    /**
     * Get the job IDs ranges, if applicable.
     *
     * @return array
     */
    protected function getJobIdsByRanges(array $ranges) {
        $ids = [];

        foreach ($ranges as $range) {
            if (preg_match('/^[0-9]+\-[0-9]+$/', $range)) {
                $ids = array_merge($ids, range(...explode('-', $range)));
            }
        }

        return $ids;
    }
}
