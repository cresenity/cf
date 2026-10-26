<?php

class CConsole_Command_Queue_ForgetFailedCommand extends CConsole_Command {
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'queue:forget {id : The ID of the failed job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a failed queue job';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        if (CQueue::failer()->forget($this->argument('id'))) {
            $this->info('Failed job deleted successfully.');

            return 0;
        }

        $this->error('No failed job matches the given ID.');

        return 1;
    }
}
