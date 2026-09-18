<?php

/**
 * Description of DaemonStatusCommand
 */
class CConsole_Command_Daemon_DaemonStatusCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daemon:status {class}';

    public function handle() {
        CConsole::domainRequired($this);
        $class = $this->argument('class');

        if (!class_exists($class)) {
            $this->error($class . ' not found');
            return 1;
        }
        $daemonManager = CManager::daemon();
        if ($daemonManager->isRunning($class)) {
            $this->info($class . ' is running');
        } else {
            $this->info($class . ' is stopped');
        }

        return 0;
    }
}
