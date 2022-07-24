<?php

use Symfony\Component\Process\Process;

class CDaemon_Supervisor_SystemProcessCounter {
    /**
     * The base command to search for.
     *
     * @var string
     */
    public static $command = 'supervisor:work';

    /**
     * Get the number of Horizon workers for a given supervisor.
     *
     * @param string $name
     *
     * @return int
     */
    public function get($name) {
        $process = Process::fromShellCommandline('exec ps aux | grep ' . static::$command, null, ['COLUMNS' => '2000']);

        $process->run();

        return substr_count($process->getOutput(), 'supervisor=' . $name);
    }
}
