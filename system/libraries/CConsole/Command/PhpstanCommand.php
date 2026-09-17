<?php

/**
 * Description of PhpStanCommand.
 *
 * @author Hery
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class CConsole_Command_PhpstanCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'phpstan {path?} {--format=table : format to display} {--debug} {--no-progress}';

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
        if ($path && !$this->isAbsolutePath($path)) {
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

        if (!$this->isPhpStanInstalled()) {
            throw new RuntimeException('phpstan is not installed, please install with phpstan:install command');
        }

        chdir($isFramework ? DOCROOT : c::appRoot());
        $command = [$this->phpBinary(), '-d', 'memory_limit=1G', '-d', 'max_execution_time=0', $this->getPhpStanBinary(), 'analyze', '-c', CQC::phpstan()->phpstanConfiguration(), '--error-format', $format, '--autoload-file', CQC::phpstan()->phpstanBootstrap()];
        if ($debug) {
            $command[] = '--debug';
        }
        if ($noProgress) {
            $command[] = '--no-progress';
        }
        $command[] = $scanPath;
        $process = Process::fromShellCommandline($command, c::appRoot());
        $process->setTimeout(60 * 60);
        $process->start(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        $process->wait();
        // exit code 1 berarti ada temuan, bukan gagal - pesannya akan mencemari keluaran --format=json
        if (!$process->isSuccessful() && $process->getExitCode() !== 1) {
            $errMessage = $process->getErrorOutput();
            if (strlen($errMessage) == 0) {
                $errMessage = 'Something went wrong on running phpstan, please manually check the command';
            }
            $this->error($errMessage);
        }

        return $process->getExitCode();
    }

    private function isPhpStanInstalled() {
        return CQC::phpstan()->isInstalled();
    }

    private function getPhpStanBinary() {
        return CQC::phpstan()->phpstanBinary();
    }

    private function phpBinary() {
        return (new PhpExecutableFinder())->find(false);
    }

    // Helper function to check if a path is absolute
    private function isAbsolutePath($path) {
        return $path[0] === '/' || $path[1] === ':' || substr($path, 0, 2) === '\\\\';
    }
}
