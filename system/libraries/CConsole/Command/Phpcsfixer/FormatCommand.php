<?php

/**
 * Description of PhpStanCommand.
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class CConsole_Command_Phpcsfixer_FormatCommand extends CConsole_Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'php-cs-fixer:format {path} {--format=table : format to display} {--config= : config file to use, defaults to the resolved .php-cs-fixer.dist.php} {--debug} {--ignore-env}';

    public function handle() {
        $isFramework = CF::appCode() == null;
        $format = $this->option('format');
        $debug = $this->option('debug');
        $ignoreEnv = $this->option('ignore-env');
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

        if (!CFile::isFile($scanPath)) {
            throw new RuntimeException($scanPath . ' is not file');
        }

        if (!$this->isPhpCsFixerInstalled()) {
            throw new RuntimeException('php-cs-fixer is not installed, please install with phpcs:install command');
        }

        chdir($isFramework ? DOCROOT : c::appRoot());
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'phpcf_phpcsfixer_');

        CFile::put($tempFile, CFile::get($scanPath));
        //$command = [$this->phpBinary(), $this->getPhpCsPhar(),$appDir];
        //Config boleh ditentukan pemanggil. Yang dipilih CQC bergantung pada
        //CF::appCode(), yang berasal dari direktori kerja - dan berkas yang
        //diformat di sini justru berada di direktori sementara.
        $configuration = $this->option('config');
        if (!strlen((string) $configuration)) {
            $configuration = CQC::phpcsfixer()->phpcsfixerConfiguration();
        }
        $command = [$this->phpBinary(), $this->getPhpCsFixerPhar(), '--config=' . $configuration];
        $command[] = 'fix';
        $command[] = $tempFile;
        $envVariables = [];
        if ($ignoreEnv) {
            $envVariables['PHP_CS_FIXER_IGNORE_ENV'] = 1;
        }

        $process = Process::fromShellCommandline($command, c::appRoot(), $envVariables);
        $process->setTimeout(60 * 60);
        $process->start(function ($type, $buffer) {
            //$this->output->write($buffer);
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
        $this->output->write(CFile::get($tempFile));
        CFile::delete($tempFile);

        return $process->getExitCode();
    }

    private function isPhpCsFixerInstalled() {
        return CQC::phpcsfixer()->isInstalled();
    }

    private function getPhpCsFixerPhar() {
        return CQC::phpcsfixer()->phpcsfixerPhar();
    }

    private function phpBinary() {
        return (new PhpExecutableFinder())->find(false);
    }

    // Helper function to check if a path is absolute
    private function isAbsolutePath($path) {
        return $path[0] === '/' || $path[1] === ':' || substr($path, 0, 2) === '\\\\';
    }
}
