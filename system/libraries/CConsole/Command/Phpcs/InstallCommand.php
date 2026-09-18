<?php

/**
 * Description of ComposerCommand.
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class CConsole_Command_Phpcs_InstallCommand extends CConsole_Command {
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
    protected $signature = 'phpcs:install {args?*} {--opts=?*}';

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure() {
        $this->ignoreValidationErrors();
    }

    public function handle() {
        $this->downloadPhpcsPharOnBinPath();
        $this->downloadPhpcbfPharOnBinPath();
        $this->setupPhpcsConfig();
    }

    protected function downloadPhpcsPharOnBinPath() {
        return $this->installPhar('phpcs', CQC::phpcs()->phpcsPhar());
    }

    protected function downloadPhpcbfPharOnBinPath() {
        //phpcbf harus seversi dengan phpcs - keduanya berbagi sniff yang sama,
        //dan yang satu memperbaiki apa yang dilaporkan yang lain.
        return $this->installPhar('phpcbf', CQC::phpcs()->phpcbfPhar());
    }

    /**
     * @param string $name     phpcs atau phpcbf
     * @param string $pharPath
     *
     * @return bool
     */
    protected function installPhar($name, $pharPath) {
        $version = CQC_Phpcs::VERSION;
        $installed = CQC_Phpcs::installedVersion($pharPath);

        if ($installed === $version) {
            $this->info($pharPath . ' is already installed (' . $version . ')');

            return true;
        }

        //Yang diperiksa versinya, bukan keberadaan berkasnya. Phar lama tetap
        //ada di tempatnya dan lolos file_exists() walau versinya bukan yang
        //didukung - sehingga tidak pernah tergantikan.
        if (file_exists($pharPath)) {
            $this->info('Replacing ' . $name . ' ' . ($installed == null ? '(unreadable)' : $installed) . ' with ' . $version);
        }

        $url = 'https://devcloud.cresenity.com/application/devcloud/default/data/bin/phpcs/' . $name . '.' . $version . '.phar';

        $this->info('Downloading ' . basename($url));

        try {
            CQC::downloadPhar($url, $pharPath, $version);
        } catch (Exception $ex) {
            $this->error($ex->getMessage());

            return false;
        }

        $this->info($pharPath . ' downloaded (' . $version . ')');

        return true;
    }

    protected function setupPhpcsConfig() {
        $configFile = CQC::phpcs()->phpcsConfiguration();
        if (!CFile::exists($configFile)) {
            $stubFile = DOCROOT . 'phpcs.xml';
            if (!$stubFile) {
                $this->error('phpcs.xml not found on ' . $stubFile);
                exit(1);
            }
            $content = CFile::get($stubFile);
            CFile::put($configFile, $content);
            $this->info('phpcs configuration ' . basename($configFile) . ' created on ' . $configFile);
        }
    }
}
