<?php

/**
 * Description of DaemonStartCommand.
 */
class CConsole_Command_Daemon_DaemonStartCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daemon:start {class}';

    public function handle() {
        CConsole::domainRequired($this);
        $class = $this->argument('class');

        $errCode = 0;
        $errMessage = '';
        $daemonRunner = CDaemon::createRunner($class);
        if ($daemonRunner->isRunning()) {
            $errCode++;
            $errMessage = $class . ' already running';
        }
        if ($errCode == 0) {
            $this->info('Starting ' . $class);

            try {
                $started = $daemonRunner->run();
                $this->info('Daemon ' . $class . ' is running now');
            } catch (Exception $ex) {
                $errCode++;
                $errMessage = $ex->getMessage();
            }
        }

        if ($errCode > 0) {
            $this->error($errMessage);

            return 1;
        }

        return 0;
    }
}
