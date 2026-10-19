<?php

class CConsole_Command_Queue_PruneFailedJobsCommand extends CConsole_Command {
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'queue:prune-failed {--hours=24 : The number of hours to retain failed jobs data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune stale entries from the failed jobs table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $failer = CQueue::failer();

        if (!$failer instanceof CQueue_Contract_PrunableFailedJobInterface) {
            $this->error('The [' . c::classBasename($failer) . '] failed job storage driver does not support pruning.');

            return 1;
        }

        $count = $failer->prune(CCarbon::now()->subHours($this->option('hours')));

        $this->info("{$count} entries deleted.");

        return 0;
    }
}
