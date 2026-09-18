<?php

/**
 * Description of ComposerCommand.
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class CConsole_Command_Phpcsfixer_InstallCommand extends CConsole_Command {
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
    protected $signature = 'php-cs-fixer:install {args?*} {--opts=?*}';

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure() {
        $this->ignoreValidationErrors();
    }

    public function handle() {
        $this->downloadPhpcsfixerPharOnBinPath();
    }

    protected function downloadPhpcsfixerPharOnBinPath() {
        $version = CQC_Phpcsfixer::VERSION;
        $pharPath = CQC::phpcsfixer()->phpcsfixerPhar();
        $installed = CQC_Phpcsfixer::installedVersion();

        if ($installed === $version) {
            $this->info($pharPath . ' is already installed (' . $version . ')');

            return true;
        }

        //Yang diperiksa versinya, bukan keberadaan berkasnya. Phar lama tetap
        //ada di tempatnya dan lolos file_exists() walau ia menolak jalan pada
        //versi PHP di sini - sehingga tidak pernah tergantikan.
        if (file_exists($pharPath)) {
            $this->info('Replacing php-cs-fixer ' . ($installed == null ? '(unreadable)' : $installed) . ' with ' . $version);
        }

        $url = 'https://devcloud.cresenity.com/application/devcloud/default/data/bin/php-cs-fixer/php-cs-fixer.' . $version . '.phar';

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
}
