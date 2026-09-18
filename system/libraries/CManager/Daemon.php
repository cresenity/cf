<?php

final class CManager_Daemon {
    /**
     * @var CManager_Daemon
     */
    protected static $instance;

    protected $daemons = [];

    protected $daemonsGroup = [];

    protected $statusCache = [];

    protected $cacheTime = [];

    /**
     * @return CManager_Daemon
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get the status of a daemon service.
     *
     * @param string $serviceClass
     *
     * @return array
     */
    public function getDaemonStatus($serviceClass) {
        $ttl = 5; // detik

        if (isset($this->statusCache[$serviceClass])
            && (time() - $this->cacheTime[$serviceClass]) < $ttl
        ) {
            return $this->statusCache[$serviceClass];
        }

        $runner = CDaemon::createRunner($serviceClass);
        $isRunning = $runner->isRunning();
        $startTime = $isRunning ? $runner->getStartTime() : null;

        $status = [
            'running' => $isRunning,
            'start_time' => $startTime,
        ];

        $this->statusCache[$serviceClass] = $status;
        $this->cacheTime[$serviceClass] = time();

        return $status;
    }

    /**
     * Register a daemon service.
     *
     * @param string $class
     * @param null|string $name
     * @param null|string $group
     */
    public function registerDaemon($class, $name = null, $group = null) {
        if ($name == null) {
            $name = carr::last(explode('_', $class));
        }
        $this->daemons[$class] = $name;
        if ($group !== null) {
            if (!isset($this->daemonsGroup[$group])) {
                $this->daemonsGroup[$group] = [];
            }
            $this->daemonsGroup[$group][$class] = $name;
        }
    }

    public function daemons($group = null) {
        if ($group === null) {
            return $this->daemons;
        }
        if ($group === false) {
            $allDaemons = $this->daemons;
            foreach ($this->daemonsGroup as $groupArray) {
                $allDaemons = array_diff_key($allDaemons, $groupArray);
            }

            return $allDaemons;
        }
        if ($group !== null) {
            if (!in_array($group, $this->getGroupsKey())) {
                throw new Exception('group daemon ' . $group . ' not available');
            }
        }

        return $this->daemonsGroup[$group];
    }

    public function getGroupsKey() {
        return array_keys($this->daemonsGroup);
    }

    public function haveGroup() {
        return count($this->getGroupsKey()) > 0;
    }

    /**
     * Get the status of a daemon service.
     *
     * @param string $className
     *
     * @return mixed
     */
    public function status($className) {
        return CDaemon::createRunner($className)->status();
    }

    /**
     * Start a daemon service.
     *
     * @param string $className
     *
     * @return mixed
     */
    public function start($className) {
        return CDaemon::createRunner($className)->run();
    }

    /**
     * Stop a daemon service.
     *
     * @param string $className
     * @param bool $force
     *
     * @return mixed
     */
    public function stop($className, $force = false) {
        return CDaemon::createRunner($className)->stop($force);
    }

    /**
     * Check if a daemon service is running.
     *
     * @param string $className
     *
     * @return bool
     */
    public function isRunning($className) {
        return CDaemon::createRunner($className)->isRunning();
    }

    /**
     * Rotate the log file for a daemon service.
     *
     * @param string $className
     *
     * @return mixed
     */
    public function rotateLog($className) {
        return CDaemon::createRunner($className)->rotateLog();
    }

    /**
     * Dump the log for a daemon service.
     *
     * @param string $className
     *
     * @return mixed
     */
    public function logDump($className) {
        return CDaemon::createRunner($className)->logDump();
    }

    /**
     * Get the name of a daemon service.
     *
     * @param string $className
     *
     * @return string
     */
    public function getServiceName($className) {
        $serviceName = $className;
        $serviceNameExploded = explode('_', $className);
        if (count($serviceNameExploded) > 0) {
            $serviceName = carr::get($serviceNameExploded, count($serviceNameExploded) - 1);
        }

        return $serviceName;
    }

    /**
     * Get the log file for a daemon service.
     *
     * @param string $className
     * @param null|string $filename
     *
     * @return string
     */
    public function getLogFile($className, $filename = null) {
        return CDaemon_Helper::getLogFile($className, $filename);
    }

    /**
     * Get the pid file for a daemon service.
     *
     * @param string $className
     *
     * @return string
     */
    public function getPidFile($className) {
        return CDaemon_Helper::getPidFile($className);
    }

    /**
     * Get the list of log files for a daemon service.
     *
     * @param string $className
     *
     * @return array
     */
    public function getLogFileList($className) {
        return CDaemon_Helper::getLogFileList($className);
    }
}
