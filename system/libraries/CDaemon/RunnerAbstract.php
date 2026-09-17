<?php
use Symfony\Component\Process\PhpExecutableFinder;

abstract class CDaemon_RunnerAbstract {
    /**
     * @var string
     */
    protected $domain;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setDebug($bool = true) {
        $this->debug = $bool;

        return $this;
    }

    /**
     * @return mixed
     */
    public function start() {
        return $this->run();
    }

    /**
     * @return mixed
     */
    abstract public function run();

    /**
     * @return string
     */
    abstract protected function getExecutableCommand();

    /**
     * @return string
     */
    abstract protected function debugOutput();

    /**
     * @return string
     */
    abstract public function getLogFile();

    /**
     * @param bool $background
     *
     * @return string
     */
    protected function getCommandToExecuteOnUnix($background = false) {
        $command = $this->getExecutableCommand();
        $binary = $this->getPhpBinary();
        $output = $this->debug ? $this->debugOutput() : '/dev/null';

        $commandToExecute = "NSS_STRICT_NOFORK=DISABLED {$binary} {$command}";
        if ($background) {
            $commandToExecute = static::withInheritedDescriptorsClosed("{$binary} {$command}", 'NSS_STRICT_NOFORK=DISABLED') . " 1> \"{$output}\" 2>&1 &";
        }

        return $commandToExecute;
    }

    /**
     * Wrap the command so the daemon starts without the spawning process's open descriptors.
     *
     * @param string $command
     * @param string $env     `VAR=value` assignments to prefix the exec with
     *
     * @return string
     */
    protected static function withInheritedDescriptorsClosed($command, $env = '') {
        $bash = '/bin/bash';
        if (!is_executable($bash)) {
            return trim($env . ' ' . $command);
        }
        $script = 'for f in /proc/self/fd/*; do n=${f##*/}; [ "$n" -gt 2 ] && eval "exec $n>&-"; done 2>/dev/null; ' . trim($env . ' exec ' . $command);

        return $bash . ' -c ' . escapeshellarg($script);
    }

    /**
     * @param bool $background
     *
     * @return string
     */
    protected function getCommandToExecuteOnWindows($background = true) {
        $command = $this->getExecutableCommand();
        $binary = $this->getPhpBinary();

        $commandToExecute = "\"{$binary}\" {$command}";
        if ($background) {
            $commandToExecute = 'start "blah" /B ' . $commandToExecute;
        }

        return $commandToExecute;
    }

    /**
     * @param bool $background
     *
     * @return string
     */
    public function getCommandToExecute($background = true) {
        $isUnix = CDaemon_Helper::getPlatform() === CDaemon_Helper::UNIX;
        if ($isUnix) {
            return $this->getCommandToExecuteOnUnix($background);
        }

        return $this->getCommandToExecuteOnWindows($background);
    }

    /**
     * @return false|string
     */
    protected function getPhpBinary() {
        $configured = CF::config('daemon.php_binary');
        if ($configured && file_exists($configured)) {
            return $configured;
        }

        $executableFinder = new PhpExecutableFinder();

        return $executableFinder->find();
    }

    /**
     * @return void
     */
    protected function runWindows() {
        $binary = $this->getPhpBinary();
        $command = $this->getExecutableCommand();

        pclose(popen("start \"blah\" /B \"{$binary}\" {$command}", 'r'));
    }

    /**
     * @return null|string
     */
    protected function debugContent() {
        $output = $this->debugOutput();
        if (CFile::exists($output)) {
            return file_get_contents($output);
        }

        return null;
    }

    /**
     * @return null|string
     */
    public function getLog() {
        $logFile = $this->getLogFile();
        if (CFile::exists($logFile)) {
            return CFile::get($logFile);
        }

        return null;
    }

    /**
     * @return void
     */
    public function rotateLog() {
        $logFile = $this->getLogFile();

        if (strlen($logFile) > 0 && CFile::isFile($logFile)) {
            $rotator = CLogger_Rotator::createRotate($logFile);
            $rotator->forceRotate();
        }
    }
}
