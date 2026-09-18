<?php

/**
 * Description of PhpStanCommand.
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Process\Exception\ProcessSignaledException;

class CConsole_Command_NpmCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'npm {npmArgs?*} {--without-tty : Disable output to TTY} {--debug}';

    public function handle() {
        $isFramework = CF::appCode() == null;
        $debug = $this->option('debug');
        $appDir = $isFramework ? DOCROOT : c::appRoot();
        $podConfig = null;
        if (CFile::exists($appDir . 'webpack.pod.js')) {
            $podConfig = $appDir . 'webpack.pod.js';
        }

        $npmArgs = carr::get($this->input->getArguments(), 'npmArgs');
        $commands = array_merge(
            ['npm', 'run', 'development'],
            $podConfig ? ['--config=' . $podConfig] : [],
            //$npmArgs
        );
        //$this->clearEnv();
        $process = (new Process($commands, DOCROOT))->setTimeout(null);

        try {
            $process->setTty(!$this->option('without-tty'));
        } catch (RuntimeException $e) {
            $this->output->writeln('Warning: ' . $e->getMessage());
        }

        try {
            return $process->run(function ($type, $line) {
                $this->output->write($line);
            });
        } catch (ProcessSignaledException $e) {
            if (extension_loaded('pcntl') && $e->getSignal() !== SIGINT) {
                throw $e;
            }
        }
    }

    private function isPhpCsInstalled() {
        return CQC::phpcs()->isInstalled();
    }

    private function getPhpCsPhar() {
        return CQC::phpcs()->phpcsPhar();
    }

    private function phpBinary() {
        return (new PhpExecutableFinder())->find(false);
    }
}
