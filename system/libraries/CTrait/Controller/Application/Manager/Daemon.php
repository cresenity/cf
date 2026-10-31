<?php
use Symfony\Component\Process\Process;

trait CTrait_Controller_Application_Manager_Daemon {
    protected $isShowStartTime = false;

    protected function getTitle() {
        return 'Daemon Manager';
    }

    protected function showStartTime() {
        $this->isShowStartTime = true;

        return $this->isShowStartTime;
    }

    public function index() {
        $app = CApp::instance();

        $app->title($this->getTitle());
        $actionContainer = $app->addDiv()->addClass('action-container mb-3');

        $reloadAction = $actionContainer->addAction()->setLabel('Reload')->addClass('btn-primary')->setIcon('fas fa-sync');

        $tableServiceDiv = $app->addDiv('tableService');

        $handlerActionClick = $reloadAction->addListener('click')->addReloadHandler();
        $handlerActionClick->setTarget('tableService');
        $handlerActionClick->setUrl($this->controllerUrl() . 'reloadTabService');

        $reloadOptions = [];
        $this->reloadTabService($tableServiceDiv, $reloadOptions);

        return $app;
    }

    public function reloadTabService($container = null, $options = []) {
        $app = $container ?: c::app();
        $daemonManager = CManager_Daemon::instance();
        $request = array_merge(CApp_Base::getRequest(), $options);

        $groupTab = carr::get($_GET, 'group');
        if ($daemonManager->haveGroup()) {
            $tabList = $app->addTabList()->setAjax(false);
            $groupKeys = $daemonManager->getGroupsKey();
            $notGrouped = $daemonManager->daemons(false);
            if (count($notGrouped) > 0) {
                $tab = $tabList->addTab()->setLabel('Not Grouped');
                $this->reloadTableService($tab, ['group' => false]);
            }
            foreach ($groupKeys as $groupName) {
                $tab = $tabList->addTab()->setLabel($groupName);
                if ($groupTab == $groupName) {
                    $tab->setActive();
                }

                $this->reloadTableService($tab, ['group' => $groupName]);
            }
        } else {
            $div = $app->addDiv();
            $this->reloadTableService($div);
        }

        return $app;
    }

    public function reloadTableService($container = null, $options = []) {
        $app = $container ?: c::app();
        $daemonManager = CManager_Daemon::instance();

        $request = array_merge(CApp_Base::getRequest(), $options);
        $group = carr::get($request, 'group');
        $groupQueryString = '?group=';
        if (strlen($group) > 0) {
            $groupQueryString = '?group=' . $group;
        }
        $listService = $daemonManager->daemons($group);
        $dataService = [];
        foreach ($listService as $kService => $vService) {
            $dService = [];
            $dService['service_class'] = $kService;
            $dService['service_name'] = $vService;
            $dataService[] = $dService;
        }

        $table = $app->addTable();
        $table->setDataFromArray($dataService);
        $table->addColumn('service_name')->setLabel('Name')->setCallback(function ($row, $value) use ($groupQueryString) {
            return CElement_Element_A::factory()->setHref($this->controllerUrl() . 'log/index/' . carr::get($row, 'service_class') . $groupQueryString)->add($value);
        });
        $table->addColumn('service_status')->setLabel('Service Status')->setCallback(function ($row, $value) {
            $isRunning = CManager::daemon()->isRunning(carr::get($row, 'service_class'));
            $badgeClass = $isRunning ? 'badge badge-success bg-success' : 'badge badge-danger bg-danger';
            $badgeLabel = $isRunning ? 'RUNNING' : 'STOPPED';
            $div = c::div();
            $div->addDiv()->add('<span class="' . $badgeClass . '">' . $badgeLabel . '</span>');
            if ($this->showStartTime()) {
                $runner = CDaemon::createRunner(carr::get($row, 'service_class'));
                $startTime = $isRunning ? $runner->getStartTime() : '';
                if ($startTime) {
                    $div->addDiv()->add('<span>' . $startTime . '</span>');
                }
            }

            return $div;
        });
        // A leaking or spinning daemon still reports a normal memory
        // footprint and a reassuring RUNNING status - this is the page
        // people actually open when something seems wrong, so it's worth
        // reading these directly rather than only in the CDaemon_Supervisor
        // dashboard. One column (like Service Status above already stacks
        // its badge + start time) rather than three, so getProcessMetrics()
        // - a shell_exec("ps ...") plus a couple of /proc reads - runs once
        // per row instead of three times.
        $table->addColumn('service_class')->setLabel('Process')->setCallback(function ($row, $value) {
            $isRunning = CManager::daemon()->isRunning($value);
            if (!$isRunning) {
                return '-';
            }
            $runner = CDaemon::createRunner($value);
            $formatted = CDaemon_ProcessMetrics::format($runner->getProcessMetrics());

            $div = c::div();
            $div->addDiv()->add('<span title="File descriptors used / limit">fd: ' . c::e($formatted['fd']) . '</span>');
            $div->addDiv()->add('<span title="Time since the process started">age: ' . c::e($formatted['age']) . '</span>');
            $div->addDiv()->add('<span title="Cumulative CPU time and its share of the process\'s age">cpu: ' . c::e($formatted['cpu']) . '</span>');

            return $div;
        });
        $table->setTitle('Service List');
        $table->setApplyDataTable(false);

        $table->setRowActionStyle('btn-dropdown');

        $actMonitor = $table->addRowAction();
        $actMonitor->setIcon('fas fa-file')->setLabel('Log');
        $actMonitor->setLink(static::controllerUrl() . 'log/index/{service_class}' . $groupQueryString);
        $actStart = $table->addRowAction();
        $actStart->setIcon('fas fa-play')->setLabel('Start');
        $actStart->setLink(static::controllerUrl() . 'start/{service_class}' . $groupQueryString)->setConfirm();
        $actStop = $table->addRowAction();
        $actStop->setIcon('fas fa-stop')->setLabel('Stop');
        $actStop->setLink(static::controllerUrl() . 'stop/{service_class}' . $groupQueryString)->setConfirm();
        $actForceStop = $table->addRowAction();
        $actForceStop->setIcon('fas fa-stop')->setLabel('Force Stop');
        $actForceStop->setLink(static::controllerUrl() . 'stop/{service_class}' . $groupQueryString . '&force=1')->setConfirm();
        $actDebug = $table->addRowAction();
        $actDebug->setIcon('fas fa-life-ring')->setLabel('Debug');
        $actDebug->setLink(static::controllerUrl() . 'debug/{service_class}' . $groupQueryString);

        if ($container == null) {
            echo $app->render();
        }
    }

    public function start($serviceClass) {
        $errCode = 0;
        $errMessage = '';

        try {
            $started = CManager::daemon()->start($serviceClass);
        } catch (Exception $ex) {
            $errCode++;
            $errMessage = $ex->getMessage();
        }
        if ($errCode == 0) {
            cmsg::add('success', 'Daemon Successfully Started');
        } else {
            cmsg::add('error', $errMessage);
        }

        return c::redirect($this->controllerUrl() . static::groupQueryString());
    }

    public function stop($serviceClass) {
        $errCode = 0;
        $errMessage = '';
        $group = carr::get($_GET, 'group');
        $force = (bool) carr::get($_GET, 'force');

        try {
            $started = CManager::daemon()->stop($serviceClass, $force);
        } catch (Exception $ex) {
            $errCode++;
            $errMessage = $ex->getMessage();
        }
        if ($errCode == 0) {
            cmsg::add('success', 'Daemon Successfully Stopped');
        } else {
            cmsg::add('error', $errMessage);
        }

        return c::redirect($this->controllerUrl() . static::groupQueryString());
    }

    public function log() {
        $args = func_get_args();
        $method = carr::get($args, 0);
        $logArgs = array_slice($args, 1);

        switch ($method) {
            case 'index':
                return $this->logIndex(...$logArgs);

                break;
            case 'file':
                return $this->logFile(...$logArgs);

                break;
            case 'restart':
                return $this->logRestart(...$logArgs);

                break;
            case 'dump':
                return $this->logDump(...$logArgs);

                break;
            case 'back':
                return c::redirect($this->controllerUrl() . static::groupQueryString());

                break;
        }
    }

    public function logIndex($serviceClass = null) {
        if (strlen($serviceClass) == 0 || !$this->isRegisteredDaemonService($serviceClass)) {
            return c::redirect($this->controllerUrl());
        }
        $app = CApp::instance();
        $group = carr::get($_GET, 'group');
        $app->addBreadcrumb($this->getTitle(), static::controllerUrl());
        $app->title(CManager::daemon()->getServiceName($serviceClass));
        $actionContainer = $app->addDiv()->addClass('action-container mb-3');
        $restartAction = $actionContainer->addAction()->setLabel('Restart')->addClass('btn-primary')->setIcon('fas fa-sync')->setLink(static::controllerUrl() . 'log/restart/' . $serviceClass . static::groupQueryString())->setConfirm();
        $backAction = $actionContainer->addAction()->setLabel('Back')->addClass('btn-primary')->setIcon('fas fa-arrow-left')->setLink(static::controllerUrl() . static::groupQueryString());
        $rotateAction = $actionContainer->addAction()->setLabel('Dump Status')->addClass('btn-primary')->setIcon('fas fa-sync')->setLink(static::controllerUrl() . 'log/dump/' . $serviceClass . static::groupQueryString())->setConfirm();
        $debugAction = $actionContainer->addAction()->setLabel('Debug')->addClass('btn-primary')->setIcon('fas fa-life-ring')->setLink(static::controllerUrl() . 'debug/' . $serviceClass . static::groupQueryString());

        $logFileList = CManager::daemon()->getLogFileList($serviceClass);
        $tabList = $app->addTabList()->setAjax(true);
        $logFile = CManager::daemon()->getLogFile($serviceClass);
        $basename = basename($logFile);
        if (file_exists($logFile)) {
            $tabList->addTab()->setLabel('Current')->setAjaxUrl(static::controllerUrl() . 'log/file/' . $serviceClass . '?f=' . $basename);
        }
        for ($i = 1; $i <= 10; $i++) {
            $logFileRotate = $logFile . '.' . $i;
            if (file_exists($logFileRotate)) {
                $basename = basename($logFileRotate);
                $tabList->addTab()->setLabel('Rotate:' . $i)->setAjaxUrl(static::controllerUrl() . 'log/file/' . $serviceClass . '?f=' . $basename);
            }
        }

        return $app;
    }

    public function logFile($serviceClass = null, $filename = null) {
        $app = CApp::instance();
        if (!$this->isRegisteredDaemonService($serviceClass)) {
            return $app;
        }
        $filename = $filename ?: c::request()->f;
        $logFile = CManager::daemon()->getLogFile($serviceClass, strlen($filename) > 0 ? basename($filename) : null);
        $divLog = $app->addDiv()->addClass('console');
        $log = '';
        if (file_exists($logFile)) {
            $log = file_get_contents($logFile);
        }
        $divLog->add($log);

        return $app;
    }

    public function logRestart($serviceClass = null) {
        if (strlen($serviceClass) == 0) {
            // curl::redirect($this->controllerUrl() . 'log/index' . static::groupQueryString());
            return c::redirect($this->controllerUrl() . 'log/index' . static::groupQueryString());
        }
        $group = carr::get($_GET, 'group');

        $errCode = 0;
        $errMessage = '';

        try {
            $started = CManager::daemon()->stop($serviceClass);
        } catch (Exception $ex) {
            $errCode++;
            $errMessage = $ex->getMessage();
        }
        sleep(2);
        CManager::daemon()->rotateLog($serviceClass);

        try {
            $started = CManager::daemon()->start($serviceClass);
        } catch (Exception $ex) {
            $errCode++;
            $errMessage = $ex->getMessage();
        }
        if ($errCode == 0) {
            cmsg::add('success', 'Daemon Successfully Restarted');
        } else {
            cmsg::add('error', $errMessage);
        }
        sleep(1);

        // curl::redirect($this->controllerUrl() . 'log/index/' . $serviceClass . static::groupQueryString());
        return c::redirect($this->controllerUrl() . 'log/index/' . $serviceClass . static::groupQueryString());
    }

    public function logDump($serviceClass = null) {
        $group = carr::get($_GET, 'group');
        if (strlen($serviceClass) == 0) {
            curl::redirect($this->controllerUrl() . 'log/index' . static::groupQueryString());
        }

        $app = CApp::instance();

        $errCode = 0;
        $errMessage = '';

        CManager::daemon()->logDump($serviceClass);

        if ($errCode == 0) {
            cmsg::add('success', 'Daemon Successfully Dumped on Log');
        } else {
            cmsg::add('error', $errMessage);
        }

        return c::redirect($this->controllerUrl() . 'log/index/' . $serviceClass . static::groupQueryString());
    }

    /**
     * @param null|string $serviceClass
     *
     * @return bool
     */
    protected function isRegisteredDaemonService($serviceClass) {
        return array_key_exists((string) $serviceClass, CManager::daemon()->daemons());
    }

    /**
     * @return string
     */
    private static function groupQueryString() {
        $group = carr::get($_GET, 'group');
        $groupQueryString = '';
        if (strlen($group) > 0) {
            $groupQueryString = '?group=' . $group;
        }

        return $groupQueryString;
    }

    public function debug($serviceClass) {
        if (strlen($serviceClass) == 0) {
            return c::redirect($this->controllerUrl());
        }
        $app = CApp::instance();
        $group = carr::get($_GET, 'group');
        $app->addBreadcrumb($this->getTitle(), static::controllerUrl());
        $app->title(CManager::daemon()->getServiceName($serviceClass));
        $actionContainer = $app->addDiv()->addClass('action-container mb-3');
        $logAction = $actionContainer->addAction()->setLabel('Log')->addClass('btn-primary')->setIcon('fas fa-file')->setLink(static::controllerUrl() . 'log/index/' . $serviceClass . static::groupQueryString());
        $runner = new CDaemon_Runner($serviceClass);
        $form = $app->addForm();
        $div = $form->addPre()->addClass('p-4 border-1');
        $foregroundCommand = $runner->getCommandToExecute(false);
        $div->add($foregroundCommand);

        if (c::request()->isMethod('POST')) {
            $process = new Process($foregroundCommand);
            $process->setWorkingDirectory(DOCROOT);
            $process->start();
            sleep(3);
            $process->stop();
            $preOutput = $form->addPre()->addClass('p-4 border-1');

            $output = $process->getOutput();
            $preOutput->add(c::e($output));
        }

        $form->addActionList()->addAction()->setLabel('Try to Execute')->setSubmit();

        return $app;
    }
}
