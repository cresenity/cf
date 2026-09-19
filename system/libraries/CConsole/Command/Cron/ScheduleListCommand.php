<?php

use Cron\CronExpression;

class CConsole_Command_Cron_ScheduleListCommand extends CConsole_Command {
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'cron:list {--timezone= : The timezone that times should be displayed in}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List the scheduled commands';

    /**
     * Execute the console command.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function handle() {
        $rows = [];
        foreach (c::cron()->events() as $event) {
            $rows[] = [
                $event->command,
                $event->expression,
                $event->description,
                (new CronExpression($event->expression))
                    ->getNextRunDate(CCarbon::now()->setTimezone($event->timezone))
                    ->setTimezone($this->option('timezone') ?: CF::config('app.timezone'))
                    ->format('Y-m-d H:i:s P'),
            ];
        }

        $this->table([
            'Command',
            'Interval',
            'Description',
            'Next Due',
        ], $rows);
    }
}
