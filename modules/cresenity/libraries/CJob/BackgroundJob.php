<?php

class CJob_BackgroundJob {
    use CJob_SerializerTrait;

    /**
     * @var CJob_Helper
     */
    protected $helper;

    /**
     * @var string
     */
    protected $job;

    /**
     * @var string
     */
    protected $tmpDir;

    /**
     * @var array
     */
    protected $config;

    /**
     * @param string $job
     * @param array  $config
     * @param Helper $helper
     */
    public function __construct($job, array $config, CJob_Helper $helper = null) {
        $this->job = $job;
        $this->config = $config + [
            'recipients' => null,
            'mailer' => null,
            'maxRuntime' => null,
            'smtpHost' => null,
            'smtpPort' => null,
            'smtpUsername' => null,
            'smtpPassword' => null,
            'smtpSender' => null,
            'smtpSenderName' => null,
            'smtpSecurity' => null,
            'runAs' => null,
            'environment' => null,
            'runOnHost' => null,
            'output' => null,
            'dateFormat' => null,
            'enabled' => null,
            'haltDir' => null,
            'debug' => null,
            'lock' => true,
        ];
        $this->helper = $helper ?: new CJob_Helper();
        $this->tmpDir = $this->helper->getTempDir();
        CJob_EventManager::initialize();
    }

    public function run() {
        $lockFile = $this->getLockFile();
        try {
            $this->checkMaxRuntime($lockFile);
        } catch (Exception $e) {
            $this->log('ERROR: ' . $e->getMessage());
            $this->mail($e->getMessage());
            return;
        }
        if (!$this->shouldRun()) {
            return;
        }
        $lockAcquired = false;
        try {
            $this->helper->acquireLock($lockFile);
            $lockAcquired = true;
            $retval = null;
            if (isset($this->config['closure'])) {
                $retval = $this->runFunction();
            } else {
                $retval = $this->runFile();
            }

            $eventManager = CJob_EventManager::getEventManager();

            if ($eventManager->hasListeners(CJob_Events::onBackgroundJobPostRun)) {
                $eventArgs = new CJob_EventManager_Args();
                $eventArgs->addArg('job', $this->job);
                $eventArgs->addArg('config', $this->config);
                $eventArgs->addArg('result', $retval);
                $eventManager->dispatchEvent(CJob_Events::onBackgroundJobPostRun, $eventArgs);
            }
        } catch (CJob_Exception_InfoException $e) {
            $this->log('INFO: ' . $e->getMessage());
        } catch (CJob_Exception $e) {
            $this->log('ERROR: ' . $e->getMessage());
            $this->mail($e->getMessage());
        }
        if ($lockAcquired) {
            $this->helper->releaseLock($lockFile);
            // remove log file if empty
            $logfile = $this->getLogfile();
            if (is_file($logfile) && filesize($logfile) <= 0) {
                unlink($logfile);
            }
        }
    }

    /**
     * @return array
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * @param string $lockFile
     *
     * @throws Exception
     */
    protected function checkMaxRuntime($lockFile) {
        $maxRuntime = $this->config['maxRuntime'];
        if ($maxRuntime === null) {
            return;
        }
        if ($this->helper->getPlatform() === CJob_Helper::WINDOWS) {
            throw new CJob_Exception('"maxRuntime" is not supported on Windows');
        }
        $runtime = $this->helper->getLockLifetime($lockFile);
        if ($runtime < $maxRuntime) {
            return;
        }
        throw new CJob_Exception("MaxRuntime of $maxRuntime secs exceeded! Current runtime: $runtime secs");
    }

    /**
     * @param string $message
     */
    protected function mail($message) {
        if (empty($this->config['recipients'])) {
            return;
        }
        $this->helper->sendMail(
            $this->job,
            $this->config,
            $message
        );
    }

    /**
     * @return string
     */
    protected function getLogfile() {
        if ($this->config['output'] === null) {
            return false;
        }
        $logfile = $this->config['output'];
        $logs = dirname($logfile);
        if (!is_dir($logs)) {
            mkdir($logs, 0755, true);
        }
        return $logfile;
    }

    /**
     * @return string
     */
    protected function getLockFile() {
        $tmp = $this->tmpDir;
        $job = $this->helper->escape($this->job);
        if (!empty($this->config['environment'])) {
            $env = $this->helper->escape($this->config['environment']);
            return "$tmp/$env-$job.lck";
        } else {
            return "$tmp/$job.lck";
        }
    }

    /**
     * @return bool
     */
    protected function shouldRun() {
        if (!$this->config['enabled']) {
            return false;
        }
        if (($haltDir = $this->config['haltDir']) !== null) {
            if (file_exists($haltDir . DIRECTORY_SEPARATOR . $this->job)) {
                return false;
            }
        }
        $host = $this->helper->getHost();
        if (strcasecmp($this->config['runOnHost'], $host) != 0) {
            return false;
        }
        return true;
    }

    /**
     * @param string $message
     */
    protected function log($message) {
        //$now = date($this->config['dateFormat'] ? $this->config['dateFormat'] : 'Y-m-d H:i:s', $_SERVER['REQUEST_TIME']);
        $now = date('Y-m-d H:i:s');
        if ($logfile = $this->getLogfile()) {
            file_put_contents($logfile, '[' . $now . '] ' . $message . "\n", FILE_APPEND);
        }
    }

    protected function runFunction() {
        $command = $this->getSerializer()->unserialize($this->config['closure']);
        ob_start();

        $retval = false;
        try {
            $retval = $command();
        } catch (\Throwable $e) {
            echo 'Error! ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
        }
        $content = ob_get_contents();
        if ($logfile = $this->getLogfile()) {
            file_put_contents($logfile, $content, FILE_APPEND);
        }
        ob_end_clean();

        if ($retval === false) {
            throw new Exception("Closure did not return false Returned:\n" . print_r($retval, true) . ' content:' . $content);
        }
        return $retval;
    }

    protected function runFile() {
        // If job should run as another user, we must be on *nix and
        // must have sudo privileges.
        $isUnix = ($this->helper->getPlatform() === CJob_Helper::UNIX);
        $useSudo = '';
        if ($isUnix) {
            $runAs = $this->config['runAs'];
            $isRoot = (posix_getuid() === 0);
            if (!empty($runAs) && $isRoot) {
                $useSudo = "sudo -u $runAs";
            }
        }
        // Start execution. Run in foreground (will block).
        $command = $this->config['command'];
        $logfile = $this->getLogfile() ? $this->getLogfile() : $this->helper->getSystemNullDevice();
        exec("$useSudo $command 1>> \"$logfile\" 2>&1", $dummy, $retval);
        if ($retval !== 0) {
            throw new Exception("Job exited with status '$retval'.");
        }
        return $retval;
    }
}
