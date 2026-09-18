<?php

/**
 * Description of PhpStanCommand
 */
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class CQC_Console_Command_PhpStanCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qc:phpstan';

    public function handle() {
        $domain = CConsole::domain();
        $domainData = CFData::domain($domain);
        $appCode = carr::get($domainData, 'app_code');
        $appDir = DOCROOT . 'application' . DS . $appCode;
        if (!$this->isPhpStanInstalled($appDir)) {
            $this->installPhpStan();
        }

        $command = [$this->phpBinary(), '-d', 'memory_limit=1G', '-d', 'max_execution_time=0', $this->getPhpStanPhar($appDir), 'analyze', '--level', '1', $appDir];

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

    protected function isPhpStanInstalled($appDir) {
        $phpStan = $this->getPhpStanBinary($appDir);
        return file_exists($phpStan);
    }

    protected function getPhpStanBinary($appDir) {
        return $appDir . DS . 'vendor' . DS . 'bin' . DS . 'phpstan';
    }

    protected function getPhpStanPhar($appDir) {
        return $appDir . DS . 'vendor' . DS . 'phpstan' . DS . 'phpstan' . DS . 'phpstan.phar';
    }

    protected function installPhpStan() {
        $this->call('composer', [
            'args' => ['require', 'phpstan/phpstan'], '--opts' => ['dev']
        ]);
    }

    protected function phpBinary() {
        return (new PhpExecutableFinder())->find(false);
    }
}
