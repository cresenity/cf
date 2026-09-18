<?php

/**
 * Description of ComposerCommand.
 */
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class CConsole_Command_Phpcsfixer_ConfigCommand extends CConsole_Command {
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
    protected $signature = 'php-cs-fixer:config {args?*} {--opts=?*}';

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure() {
        $this->ignoreValidationErrors();
    }

    public function handle() {
        $this->setupPhpcsfixerConfig();
    }

    protected function setupPhpcsfixerConfig() {
        $configFile = CQC::phpcsfixer()->phpcsfixerAppConfiguration();
        if (!CFile::exists($configFile)) {
            $stubFile = DOCROOT . '.php-cs-fixer.dist.php';
            if (!$stubFile) {
                $this->error('.php-cs-fixer.dist.php not found on ' . $stubFile);
                exit(1);
            }
            $content = CFile::get($stubFile);
            CFile::put($configFile, $content);
            $this->info('php-cs-fixer configuration ' . basename($configFile) . ' created on ' . $configFile);
        }
    }
}
