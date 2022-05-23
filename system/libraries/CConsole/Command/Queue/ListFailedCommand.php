<?php

class CConsole_Command_Queue_ListFailedCommand extends CConsole_Command {
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'queue:failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all of the failed queue jobs';

    /**
     * The table headers for the command.
     *
     * @var string[]
     */
    protected $headers = ['ID', 'Connection', 'Queue', 'Class', 'Failed At'];

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle() {
        if (count($jobs = $this->getFailedJobs()) === 0) {
            return $this->info('No failed jobs!');
        }

        $this->displayFailedJobs($jobs);
    }

    /**
     * Compile the failed jobs into a displayable format.
     *
     * @return array
     */
    protected function getFailedJobs() {
        $failed = CQueue::failer()->all();

        return c::collect($failed)->map(function ($failed) {
            return $this->parseFailedJob((array) $failed);
        })->filter()->all();
    }

    /**
     * Parse the failed job row.
     *
     * @param array $failed
     *
     * @return array
     */
    protected function parseFailedJob(array $failed) {
        $row = array_values(carr::except($failed, ['payload', 'exception']));

        array_splice($row, 3, 0, $this->extractJobName($failed['payload']) ?: '');

        return $row;
    }

    /**
     * Extract the failed job name from payload.
     *
     * @param string $payload
     *
     * @return null|string
     */
    private function extractJobName($payload) {
        $payload = json_decode($payload, true);

        if ($payload && (!isset($payload['data']['command']))) {
            return isset($payload['job']) ? $payload['job'] : null;
        } elseif ($payload && isset($payload['data']['command'])) {
            return $this->matchJobName($payload);
        }
    }

    /**
     * Match the job name from the payload.
     *
     * @param array $payload
     *
     * @return null|string
     */
    protected function matchJobName($payload) {
        preg_match('/"([^"]+)"/', $payload['data']['command'], $matches);

        return isset($matches[1]) ? $matches[1] : (isset($payload['job']) ? $payload['job'] : null);
    }

    /**
     * Display the failed jobs in the console.
     *
     * @param array $jobs
     *
     * @return void
     */
    protected function displayFailedJobs(array $jobs) {
        $this->table($this->headers, $jobs);
    }
}
