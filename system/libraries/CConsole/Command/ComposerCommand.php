<?php

/**
 * Description of ComposerCommand
 */
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class CConsole_Command_ComposerCommand extends CConsole_Command {
    /**
     * Command line options that should not be gathered dynamically.
     *
     * @var array
     */
    protected $ignoreOptions = [
        '--continue',
        '--pretend',
        '--help',
        '--quiet',
        '--version',
        '--asci',
        '--no-asci',
        '--no-interactions',
        '--verbose',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'composer {args?*} {--opts=?*}';

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure() {
        $this->ignoreValidationErrors();
    }

    public function handle() {
        $command = $this->getComposserBinaryCommand();
        $arguments = $this->argument('args');

        $domain = CConsole::domain();
        $domainData = CFData::domain($domain);
        $appCode = carr::get($domainData, 'app_code');
        $appDir = DOCROOT . 'application' . DS . $appCode;
        if ($command !== false) {
            foreach ($arguments as $arg) {
                $command[] = $arg;
            }

            $options = $this->option('opts');
            if (is_array($options)) {
                foreach ($options as $opt) {
                    $command[] = '--' . $opt;
                }
            }

            $process = Process::fromShellCommandline($command, $appDir);

            $process->start(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            $process->wait();
            // executes after the command finishes
            if (!$process->isSuccessful()) {
                $errMessage = $process->getErrorOutput();
                if (strlen($errMessage) == 0) {
                    $errMessage = 'Something went wrong on running composer, please manually check the command';
                }
                $this->error($errMessage);
            }
        }
    }

    protected function getComposserBinaryCommand() {
        $command = ['composer'];
        if (!$this->isComposerInstalled()) {
            $command = [$this->phpBinary(), $this->getComposerOnBinPath()];
            if (!$this->isComposerInstalledOnBinPath()) {
                if (!$this->downloadComposerOnBinPath()) {
                    return false;
                }
            }
        }

        return $command;
    }

    protected function isComposerInstalled() {
        $process = new Process(['composer', '--version']);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            return false;
        }
        $output = $process->getOutput();
        $version = 'unknown version';
        $releaseDate = '';

        if (preg_match('/^Composer version\s(.+?)\s(.+?\s.+?)$/', $output, $matches)) {
            $version = carr::get($matches, 1);
            $releaseDate = carr::get($matches, 2);
        }

        return true;
    }

    protected function isComposerInstalledOnBinPath() {
        $binPath = $this->getComposerOnBinPath();
        return file_exists($binPath);
    }

    protected function getComposerOnBinPath() {
        return DOCROOT . '.bin' . DS . 'composer.phar';
    }

    protected function downloadComposerOnBinPath() {
        $composerSetup = DOCROOT . '.bin' . DS . 'composer-setup.php';
        $this->info('Downloading Composer Installer');
        $errCode = 0;
        $errMessage = '';
        try {
            copy('https://getcomposer.org/installer', $composerSetup);
            $this->info($composerSetup . ' downloaded');
        } catch (Exception $ex) {
            $errCode++;
            $errMessage = $ex->getMessage();
        }
        if ($errCode == 0) {
            if (hash_file('sha384', $composerSetup) !== '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') {
                $errCode++;
                $errMessage = 'Installer corrupt';
            }
        }
        if ($errCode == 0) {
            $this->info('Installer Verified, installing...');

            $process = new Process([$this->phpBinary(), $composerSetup, '--install-dir=' . DOCROOT . '.bin']);
            $process->start(function ($type, $buffer) {
                //$this->output->write($buffer);
            });

            $process->wait();
            // executes after the command finishes
            if (!$process->isSuccessful()) {
                $errCode++;
                $errMessage = $process->getErrorOutput();
                if (strlen($errMessage) == 0) {
                    $errMessage = 'Something went wrong on install composer-setup.php, please manually check the installation';
                }
            }
        }

        if (file_exists($composerSetup)) {
            $this->info($composerSetup . ' deleted');
            unlink($composerSetup);
        }

        if ($errCode > 0) {
            $this->error($errMessage);
            return false;
        }
        return true;
    }

    protected function phpBinary() {
        return (new PhpExecutableFinder())->find(false);
    }
}
