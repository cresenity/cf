<?php

/**
 * Description of CFCli.
 *
 * @author Hery
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class CApp_CFCli {
    use CTrait_HasOptions;

    public function __construct($options = []) {
        $this->options = $options;
    }

    /**
     * @return false|string
     */
    protected function getPhpBinary() {
        $executableFinder = new PhpExecutableFinder();

        return $executableFinder->find();
    }

    /**
     * @param mixed $cfCommand
     *
     * @return string
     */
    protected function getExecutableCommand($cfCommand) {
        $script = DOCROOT . 'cf';

        $cmd = sprintf('"%s" %s', $script, $cfCommand);

        return $cmd;
    }

    /**
     * @param string $cfCommand
     *
     * @return string
     */
    protected function getCommand($cfCommand) {
        return $this->getPhpBinary() . ' ' . $this->getExecutableCommand($cfCommand);
    }

    /**
     * @param string $cfCommand
     *
     * @return Process
     */
    public function run($cfCommand) {
        $process = new Process($this->getCommand($cfCommand));
        $process->run();

        return $process;
    }
}
