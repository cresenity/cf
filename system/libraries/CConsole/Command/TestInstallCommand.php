<?php

/**
 * Description of ComposerCommand.
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class CConsole_Command_TestInstallCommand extends CConsole_Command {
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
    protected $signature = 'test:install {args?*} {--opts=?*}';

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure() {
        $this->ignoreValidationErrors();
    }

    public function handle() {
        CConsole::domainRequired($this);
        if (!CTesting::isInstalled()) {
            $this->downloadPHPUnitOnBinPath();
        }
        if (!CTesting::isInstalled()) {
            $this->setupPhpUnitConfig();
        }
        if (!CTesting::isInstalled()) {
            $this->setupDirectoryTest();
        }
    }

    protected function downloadPHPUnitOnBinPath() {
        $binPath = CTesting::phpUnitBinary();
        if (!file_exists($binPath)) {
            $this->info('Downloading phpunit ');
            $errCode = 0;
            $errMessage = '';

            try {
                if (!CFile::isDirectory(dirname($binPath))) {
                    CFile::makeDirectory(dirname($binPath), 0755, true);
                }
                copy('https://devcloud.cresenity.com/application/devcloud/default/data/bin/phpunit/phpunit', $binPath);
                $this->info($binPath . ' downloaded');
            } catch (Exception $ex) {
                $errCode++;
                $errMessage = $ex->getMessage();
            }

            if ($errCode > 0) {
                $this->error($errMessage);

                return false;
            }
        }

        return true;
    }

    protected function phpBinary() {
        return (new PhpExecutableFinder())->find(false);
    }

    protected function setupPhpUnitConfig() {
        $configFile = CTesting::phpUnitConfig();
        if (!CFile::exists($configFile)) {
            $stubFile = CF::findFile('stubs', 'phpunit.xml', true, 'stub');
            if (!$stubFile) {
                $this->error('phpunit xml stub not found');
                exit(1);
            }
            $content = CFile::get($stubFile);
            $content = str_replace('{APP_CODE}', CF::appCode(), $content);
            CFile::put($configFile, $content);
            $this->info('PHPUnit configuration ' . basename($configFile) . ' created on ' . $configFile);
        }
    }

    protected function setupDirectoryTest() {
        $testDirectory = CTesting::testDirectory();
        if (!CFile::isDirectory($testDirectory)) {
            CFile::makeDirectory($testDirectory, 0755, true);
            $this->info('Test directory created on ' . $testDirectory);
        }
        $testUnitDirectory = CTesting::testUnitDirectory();
        if (!CFile::isDirectory($testUnitDirectory)) {
            CFile::makeDirectory($testUnitDirectory, 0755, true);
            $this->info('Test Unit directory created on ' . $testUnitDirectory);
        }
        $testFeatureDirectory = CTesting::testFeatureDirectory();
        if (!CFile::isDirectory($testFeatureDirectory)) {
            CFile::makeDirectory($testFeatureDirectory, 0755, true);
            $this->info('Test Feature directory created on ' . $testFeatureDirectory);
        }

        //create gitignore
        $path = c::fixPath($testDirectory) . '.gitignore';
        CFile::put($path, './Browser' . PHP_EOL . './Browser/*' . PHP_EOL);
    }
}
