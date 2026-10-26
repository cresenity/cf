<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @see CDaemon_Runner
 * @see CDaemon_Supervisor_Runner
 */
class CDaemon {
    const COMMAND_START = 'start';

    const COMMAND_STOP = 'stop';

    const COMMAND_DEBUG = 'debug';

    const COMMAND_RESTART = 'restart';

    const COMMAND_KILL = 'kill';

    const COMMAND_STATUS = 'status';

    const COMMAND_RELOAD = 'reload';

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var CDaemon_Helper
     */
    protected $helper;

    /**
     * @var CDaemon_ServiceAbstract
     */
    protected static $runningService = null;

    public static function cliRunner($parameter = null) {
        $argv = carr::get($_SERVER, 'argv');
        if ($parameter == null) {
            $parameter = $argv[3];
        }
        parse_str($parameter, $config);
        $cls = carr::get($config, 'serviceClass');
        self::$runningService = CDaemon_Manager::createService($cls);
        CDaemon_ErrorHandler::init();
        self::$runningService->start();
    }

    public static function cliSupervisorRunner($parameter = null) {
        $argv = carr::get($_SERVER, 'argv');
        if ($parameter == null) {
            $parameter = $argv[3];
        }
        parse_str($parameter, $config);
        $name = carr::get($config, 'name');
        $masterDaemonClass = carr::get($config, 'masterDaemonClass');
        $supervisor = CDaemon_Manager::createSupervisor($name, $config);

        try {
            $supervisor->ensureNoDuplicateSupervisors();
        } catch (Exception $e) {
            throw new Exception(sprintf('A supervisor with this name is already running. (%s)', $name));

            return 13;
        }

        if ($supervisor->options->nice) {
            proc_nice($supervisor->options->nice);
        }

        if ($masterDaemonClass) {
            $supervisor->setMasterDaemonClass($masterDaemonClass);
        }
        $output = new Symfony\Component\Console\Output\ConsoleOutput();
        $supervisor->handleOutputUsing(function ($type, $line) use ($output) {
            try {
                $output->writeln($type . ':' . $line);
            } catch (Symfony\Component\Console\Exception\RuntimeException $e) {
                //pipe ke master sudah putus (master mati/restart) - output dibuang,
                //ensureParentIsRunning() yang menghentikan supervisor ini
            }
        });

        $supervisor->working = !carr::get($config, 'paused');

        $supervisor->scale(max(
            0,
            carr::get($config, 'max-processes') - $supervisor->totalSystemProcessCount()
        ));

        $supervisor->monitor();
    }

    public static function cliWorkerRunner($parameter = null) {
        $argv = carr::get($_SERVER, 'argv');
        if ($parameter == null) {
            $parameter = $argv[3];
        }
        parse_str($parameter, $config);
        $connection = carr::get($config, 'connection');
        CDaemon_Manager::runWorker($connection, $config);
    }

    /**
     * @return CDaemon_ServiceAbstract
     */
    public static function getRunningService() {
        return self::$runningService;
    }

    /**
     * @return CDaemon_Factory
     */
    public static function factory() {
        return CDaemon_Factory::instance();
    }

    /**
     * Shortcut function to log the current running service.
     *
     * @param string $msg
     * @param string $label
     */
    public static function log($msg, $label = '') {
        $runningService = self::getRunningService();
        if ($runningService != null) {
            $runningService->log($msg, $label);
        }
    }

    /**
     * Handle an exception that occurs within the daemon.
     *
     * @param Exception $e
     *
     * @return void
     */
    public static function handleException($e) {
        CDaemon_ErrorHandler::daemonException($e);
    }

    /**
     * @param string      $serviceClass
     * @param null|string $domain
     *
     * @return CDaemon_Runner
     */
    public static function createRunner($serviceClass, $domain = null) {
        return new CDaemon_Runner($serviceClass, $domain);
    }

    /**
     * @param CDaemon_Supervisor_MasterSupervisor  $master
     * @param CDaemon_Supervisor_SupervisorOptions $options
     * @param null|string                          $domain
     *
     * @return CDaemon_Supervisor_Runner
     */
    public static function createSupervisorRunner(CDaemon_Supervisor_MasterSupervisor $master, CDaemon_Supervisor_SupervisorOptions $options, $domain = null) {
        return new CDaemon_Supervisor_Runner($master, $options, $domain);
    }

    /**
     * @param CDaemon_Supervisor_SupervisorOptions $options
     * @param null|string                          $domain
     * @param string                               $masterDaemonClass
     *
     * @return CDaemon_Supervisor_WorkerRunner
     */
    public static function createWorkerRunner(CDaemon_Supervisor_SupervisorOptions $options, $masterDaemonClass, $domain = null) {
        return new CDaemon_Supervisor_WorkerRunner($options, $masterDaemonClass, $domain);
    }

    public static function isDaemon() {
        return self::getRunningService() != null;
    }

    /**
     * @return CDaemon_Supervisor
     */
    public static function supervisor() {
        return new CBase_ForwarderStaticClass(CDaemon_Supervisor::class);
    }

    public static function bootSupervisor() {
        CDaemon_Supervisor_Bootstrap::boot();
    }
}
