<?php

/**
 *
 * @final
 */

use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Exception\ProcessSignaledException;

class CConsole_Command_TestCommand extends CConsole_Command {
    /**
     * @var string
     */
    protected $signature = 'test {phpunitArgs?*} {--without-tty : Disable output to TTY}';

    /**
     * @var string
     */
    protected $description = 'Run the application tests';

    /**
     * @var array
     */
    protected $arguments = [
        '--printer',
        'CTesting_PhpUnit_Printer',
    ];

    public function __construct() {
        parent::__construct();

        $this->ignoreValidationErrors();
    }

    /**
     * @return mixed
     */
    public function handle() {
        if ((int) \PHPUnit\Runner\Version::id()[0] < 9) {
            throw new RuntimeException('Running cf test command requires PHPUnit ^9.0.');
        }

        $options = [];
        $phpunitArgs = $this->rawPhpunitArgs();

        $commands = array_merge(
            $this->binary(),
            array_merge(
                $this->arguments,
                $this->phpunitArguments($options),
                $this->reformatOptionsPath($phpunitArgs),
            )
        );

        $process = (new Process($commands))->setTimeout(null);

        try {
            $process->setTty(!$this->option('without-tty'));
        } catch (RuntimeException $e) {
            $this->output->writeln('Warning: ' . $e->getMessage());
        }

        try {
            return $process->run(function ($type, $line) {
                $this->output->write($line);
            });
        } catch (ProcessSignaledException $e) {
            if (extension_loaded('pcntl') && $e->getSignal() !== SIGINT) {
                throw $e;
            }
        }
    }

    /**
     * Symfony's ArgvInput::bind() aborts entirely (leaving every declared
     * argument unbound) the moment it hits an option this command's
     * signature doesn't declare - e.g. `--filter=X`, which phpunit
     * understands but this command doesn't. ignoreValidationErrors() only
     * suppresses the resulting exception, it doesn't make binding lenient,
     * so $this->input->getArguments()['phpunitArgs'] silently comes back
     * empty whenever such an option is present, and every phpunit arg
     * meant to scope the run (--filter, a specific test file, etc.) is
     * lost - the run then executes the *entire* configured test suite
     * instead of the intended subset.
     *
     * Read the raw argv directly instead, everything after this command's
     * own name, so each token (recognized by Symfony or not) reaches
     * phpunit verbatim.
     *
     * @return array
     */
    protected function rawPhpunitArgs() {
        $argv = carr::get($_SERVER, 'argv', []);
        $index = array_search($this->getName(), $argv, true);
        if ($index === false) {
            return carr::get($this->input->getArguments(), 'phpunitArgs', []);
        }

        $rawArgs = array_slice($argv, $index + 1);

        return array_values(array_filter($rawArgs, function ($arg) {
            return $arg !== '--without-tty';
        }));
    }

    /**
     * @return bool
     */
    protected function isFrameworkContext() {
        return !defined('CFCLI_APPCODE') || CF::cliAppCode() === null;
    }

    /**
     * @return string
     */
    protected function getBasePath() {
        if ($this->isFrameworkContext()) {
            return c::fixPath(DOCROOT);
        }

        return c::fixPath(CF::appDir());
    }

    /**
     * @return array
     */
    protected function binary() {
        $command = DOCROOT . '.bin' . DS . 'phpunit' . DS . 'phpunit';

        if ('phpdbg' === PHP_SAPI) {
            return [$this->getPhpBinary(), '-qrr', $command];
        }

        return [$this->getPhpBinary(), $command];
    }

    /**
     * @return false|string
     */
    protected function getPhpBinary() {
        $executableFinder = new PhpExecutableFinder();

        return $executableFinder->find();
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function phpunitArguments($options) {
        $options = array_values(array_filter($options, function ($option) {
            return !cstr::startsWith($option, '--env=');
        }));

        $basePath = $this->getBasePath();
        if (!file_exists($file = $basePath . 'phpunit.xml')) {
            $file = $basePath . 'phpunit.xml.dist';
        }
        if (!file_exists($file)) {
            throw new Exception('File not found:' . $file);
        }

        $options = $this->reformatOptionsPath($options);

        return array_merge(['-c', $file], $options);
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function reformatOptionsPath(array $options) {
        if ($this->isFrameworkContext()) {
            $testsPath = DOCROOT . 'tests' . DS;
        } else {
            $testsPath = c::appRoot() . 'default/tests/';
        }

        foreach ($options as $key => $option) {
            $path = $testsPath . $option;
            if (CFile::isDirectory($path) || CFile::isFile($path)) {
                $options[$key] = $path;
            }
        }

        return $options;
    }
}
