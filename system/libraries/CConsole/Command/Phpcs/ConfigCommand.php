<?php

/**
 * Description of ComposerCommand.
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class CConsole_Command_Phpcs_ConfigCommand extends CConsole_Command {
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
    protected $signature = 'phpcs:config {args?*} {--opts=?*}';

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure() {
        $this->ignoreValidationErrors();
    }

    public function handle() {
        $this->setupPhpcsConfig();
    }

    protected function setupPhpcsConfig() {
        $configFile = CQC::phpcs()->phpcsAppConfiguration();
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
