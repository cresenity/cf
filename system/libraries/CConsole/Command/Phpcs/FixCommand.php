<?php

/**
 * Description of PhpStanCommand.
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class CConsole_Command_Phpcs_FixCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'phpcs:fix {path?} {--format=table : format to display} {--debug} {--no-progress}';

    protected $description = 'Run PHPCS fix on the given path';

    public function handle() {
        $isFramework = CF::appCode() == null;
        $format = $this->option('format');
        $debug = $this->option('debug');
        $noProgress = $this->option('no-progress');
        $appDir = $isFramework ? DOCROOT . 'system/libraries/CElement' : c::appRoot();
        $path = $this->argument('path');
        // Get the current working directory
        $currentWorkingDirectory = getcwd();
        $fullPath = $path;
        // Check if the path is relative or absolute
        if (!$this->isAbsolutePath($path)) {
            // If the path is relative, convert it to an absolute path
            $fullPath = realpath($currentWorkingDirectory . DIRECTORY_SEPARATOR . $path);
        } else {
            // If the path is absolute, use it directly
            $fullPath = realpath($path);
        }

        $scanPath = $appDir;
        if ($path) {
            $scanPath = $fullPath;
        }

        if (!$this->isPhpCbfInstalled()) {
            throw new RuntimeException('phpcs is not installed, please install with phpcs:install command');
        }

        chdir($isFramework ? DOCROOT : c::appRoot());
        //$command = [$this->phpBinary(), $this->getPhpCsPhar(),$appDir];
        $command = [$this->phpBinary(), $this->getPhpCbfPhar(), '--standard=' . CQC::phpcs()->phpcsConfiguration()];
        $command[] = $scanPath;
        $process = Process::fromShellCommandline($command, c::appRoot());
        $process->setTimeout(60 * 60);
        $process->start(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        $process->wait();
        // executes after the command finishes
        if (!$process->isSuccessful()) {
            $errMessage = $process->getErrorOutput();
            if (strlen($errMessage) == 0) {
                $errMessage = 'Something went wrong on running phpcs, please manually check the command';
            }
            $this->error($errMessage);
        }

        return $process->getExitCode();
    }

    private function isPhpCbfInstalled() {
        return CQC::phpcs()->isInstalled();
    }

    private function getPhpCbfPhar() {
        return CQC::phpcs()->phpcbfPhar();
    }

    private function phpBinary() {
        return (new PhpExecutableFinder())->find(false);
    }

    // Helper function to check if a path is absolute
    private function isAbsolutePath($path) {
        return $path[0] === '/' || $path[1] === ':' || substr($path, 0, 2) === '\\\\';
    }
}
