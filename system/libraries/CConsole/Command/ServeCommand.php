<?php

/**
 * Description of ServeCommand.
 */
use Carbon\Carbon;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\PhpExecutableFinder;

class CConsole_Command_ServeCommand extends CConsole_Command {
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'serve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Serve the application on the PHP development server';

    /**
     * The current port offset.
     *
     * @var int
     */
    protected $portOffset = 0;

    /**
     * Execute the console command.
     *
     * @throws \Exception
     *
     * @return int
     */
    public function handle() {
        $this->line("<info>Starting CF development server:</info> http://{$this->host()}:{$this->port()}");

        $environmentFile = $this->option('env') ? $this->option('env') : DOCROOT . '.env';

        $hasEnvironment = file_exists($environmentFile);

        $environmentLastModified = $hasEnvironment ? filemtime($environmentFile) : Carbon::now()->addDays(30)->getTimestamp();

        $process = $this->startProcess();

        while ($process->isRunning()) {
            if ($hasEnvironment) {
                clearstatcache(false, $environmentFile);
            }

            if (!$this->option('no-reload')
                && $hasEnvironment
                && filemtime($environmentFile) > $environmentLastModified
            ) {
                $environmentLastModified = filemtime($environmentFile);

                $this->comment('Environment modified. Restarting server...');

                $process->stop(5);

                $process = $this->startProcess();
            }

            usleep(500 * 1000);
        }

        $status = $process->getExitCode();

        if ($status && $this->canTryAnotherPort()) {
            $this->portOffset += 1;

            return $this->handle();
        }

        return $status;
    }

    /**
     * Start a new server process.
     *
     * @return \Symfony\Component\Process\Process
     */
    protected function startProcess() {
        $env = c::collect($_ENV)->mapWithKeys(function ($value, $key) {
            if ($this->option('no-reload')) {
                return [$key => $value];
            }

            return in_array($key, ['APP_ENV', 'LARAVEL_SAIL']) ? [$key => $value] : [$key => false];
        })->all();

        $process = new Process($this->serverCommand(), null, $env = null);
        $process->start(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $process;
    }

    /**
     * Get the full server command.
     *
     * @return array
     */
    protected function serverCommand() {
        $docroot = DOCROOT;

        return [
            (new PhpExecutableFinder())->find(false),
            '-S',
            $this->host() . ':' . $this->port(),
            '-t',
            $docroot,
        ];
    }

    /**
     * Get the host for the command.
     *
     * @return string
     */
    protected function host() {
        return $this->input->getOption('host');
    }

    /**
     * Get the port for the command.
     *
     * @return string
     */
    protected function port() {
        $port = $this->input->getOption('port') ?: 8000;

        return $port + $this->portOffset;
    }

    /**
     * Check if command has reached its max amount of port tries.
     *
     * @return bool
     */
    protected function canTryAnotherPort() {
        return is_null($this->input->getOption('port'))
                && ($this->input->getOption('tries') > $this->portOffset);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions() {
        $domain = CConsole::domain();
        //check data domain
        if (cstr::endsWith($domain, '.test')) {
            $file = CFData::getFile($domain, 'domain') . '.php';

            if (!CFile::exists($domain)) {
                $this->createDomainFile($domain);
            }
        }
        if ($domain == null) {
            $domain = 'localhost';
        }

        return [
            ['host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on', $domain],
            ['port', null, InputOption::VALUE_OPTIONAL, 'The port to serve the application on', 8080],
            ['tries', null, InputOption::VALUE_OPTIONAL, 'The max number of ports to attempt to serve from', 10],
            ['no-reload', null, InputOption::VALUE_NONE, 'Do not reload the development server on .env file changes'],
        ];
    }

    public function createDomainFile($domain) {
        $domainData = [
            'app_code' => CF::appCode()
        ];
        CFData::set($domain, $domainData, 'domain');
    }
}
