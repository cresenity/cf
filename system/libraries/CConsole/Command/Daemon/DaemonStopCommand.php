<?php

/**
 * Description of DaemonStopCommand.
 */
class CConsole_Command_Daemon_DaemonStopCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daemon:stop {class}';

    public function handle() {
        CConsole::domainRequired($this);
        $class = $this->argument('class');

        $errCode = 0;
        $errMessage = '';
        $daemonRunner = CDaemon::createRunner($class);
        if (!$daemonRunner->isRunning()) {
            $errCode++;
            $errMessage = $class . ' already stopped';
        }
        if ($errCode == 0) {
            $this->info('Stopping ' . $class);

            try {
                $stopped = $daemonRunner->stop();

                $this->info('Daemon ' . $class . ' is stopped now');
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
