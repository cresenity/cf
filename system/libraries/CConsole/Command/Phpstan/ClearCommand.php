<?php

/**
 * Description of PhpStanCommand.
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class CConsole_Command_Phpstan_ClearCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'phpstan:clear';

    public function handle() {
        $appDir = c::appRoot();

        if (!$this->isPhpStanInstalled()) {
            throw new RuntimeException('phpstan is not installed, please install with phpstan:install command');
        }
        chdir(c::appRoot());
        $command = [$this->phpBinary(), '-c', CQC::phpstan()->phpstanConfiguration(), '-d', 'memory_limit=1G', '-d', 'max_execution_time=0', $this->getPhpStanBinary(), 'clear-result-cache', '--autoload-file', CQC::phpstan()->phpstanBootstrap()];

        $process = Process::fromShellCommandline($command, $appDir);
        $process->setTimeout(60 * 60);
        $process->start(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        $process->wait();
        // executes after the command finishes
        if (!$process->isSuccessful()) {
            $errMessage = $process->getErrorOutput();
            if (strlen($errMessage) == 0) {
                $errMessage = 'Something went wrong on running phpstan, please manually check the command';
            }
            $this->error($errMessage);
        }
    }

    protected function isPhpStanInstalled() {
        return CQC::phpstan()->isInstalled();
    }

    protected function getPhpStanBinary() {
        return CQC::phpstan()->phpstanBinary();
    }

    protected function getPhpStanPhar($appDir) {
        return CQC::phpstan()->phpstanPhar();
    }

    protected function phpBinary() {
        return (new PhpExecutableFinder())->find(false);
    }
}
