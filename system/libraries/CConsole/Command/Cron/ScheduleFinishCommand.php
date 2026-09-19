<?php

class CConsole_Command_Cron_ScheduleFinishCommand extends CConsole_Command {
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'cron:finish {id} {code=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle the completion of a scheduled command';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle() {
        c::collect(CCron::schedule()->events())->filter(function ($value) {
            return $value->mutexName() == $this->argument('id');
        })->each(function (CCron_Event $event) {
            $event->callAfterCallbacksWithExitCode($this->argument('code'));

            CEvent::dispatcher()->dispatch(new CCron_Event_ScheduledBackgroundTaskFinished($event));
        });
    }
}
