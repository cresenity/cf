<?php

/**
 * Description of UnitTest.
 */
trait CTrait_Controller_Application_QC_UnitTest {
    protected function getTitle() {
        return 'Unit Test';
    }

    public function index() {
        $app = c::app();

        $app->title($this->getTitle());
        $actionContainer = $app->addDiv()->addClass('action-container mb-3');

        $reloadAction = $actionContainer->addAction()->setLabel('Reload')->addClass('btn-primary')->setIcon('fas fa-sync');

        $tableUnitTestDiv = $app->addDiv('tableUnitTest');

        $handlerActionClick = $reloadAction->addListener('click')->addReloadHandler();
        $handlerActionClick->setTarget('tableUnitTest');
        $handlerActionClick->setUrl($this->controllerUrl() . 'reloadTabUnitTest');
        $handlerActionClick->setBlockerType('shimmer');

        $reloadOptions = [];
        static::reloadTabUnitTest($tableUnitTestDiv, $reloadOptions);

        return $app;
    }

    public static function reloadTabUnitTest($container = null, $options = []) {
        $app = $container;
        if ($container == null) {
            $app = CApp::instance();
        }
        $qcManager = CQC::manager();
        $request = $options;
        if ($request == null) {
            $request = CApp_Base::getRequest();
        }
        $listUnitTest = $qcManager->unitTests();
        $dataUnitTest = [];
        $groupTab = carr::get($_GET, 'group');
        if ($qcManager->haveUnitTestGroup()) {
            $tabList = $app->addTabList()->setAjax(false);
            $groupKeys = $qcManager->getUnitTestGroupsKey();
            $notGrouped = $qcManager->unitTests(false);
            if (count($notGrouped) > 0) {
                $tab = $tabList->addTab()->setLabel('Not Grouped');
                static::reloadTableUnitTest($tab, ['group' => false]);
            }
            foreach ($groupKeys as $groupName) {
                $tab = $tabList->addTab()->setLabel($groupName);
                if ($groupTab == $groupName) {
                    $tab->setActive();
                }

                static::reloadTableUnitTest($tab, ['group' => $groupName]);
            }
        } else {
            $div = $app->addDiv();
            static::reloadTableUnitTest($div);
        }

        if ($container == null) {
            echo $app->render();
        }
    }

    public static function reloadTableUnitTest($container = null, $options = []) {
        $app = $container;
        if ($container == null) {
            $app = CApp::instance();
        }
        $qcManager = CQC_Manager::instance();
        $request = $options;
        if ($request == null) {
            $request = CApp_Base::getRequest();
        }
        $group = carr::get($request, 'group');
        $listUnitTest = $qcManager->unitTests($group);
        $dataUnitTest = [];
        foreach ($listUnitTest as $kUnitTest => $vUnitTest) {
            $dUnitTest = [];
            $dUnitTest['unit_test_class'] = $kUnitTest;
            $dUnitTest['unit_test_name'] = $vUnitTest;
            $dataUnitTest[] = $dUnitTest;
        }
        $table = $app->addTable();
        $table->setDataFromArray($dataUnitTest);
        $table->addColumn('unit_test_name')->setLabel('Name');
        $table->setTitle('UnitTest List');
        $table->setApplyDataTable(false);
        $table->cellCallbackFunc([__CLASS__, 'cellCallback'], __FILE__);

        $table->setRowActionStyle('btn-dropdown');

        $groupQueryString = '';
        if (strlen($group) > 0) {
            $groupQueryString = '?group=' . $group;
        }

        $actMonitor = $table->addRowAction();
        $actMonitor->setIcon('fas fa-file')->setLabel('Detail');
        $actMonitor->setLink(static::controllerUrl() . 'detail/{unit_test_class}' . $groupQueryString);
        $actStart = $table->addRowAction();
        $actStart->setIcon('fas fa-play')->setLabel('Run');
        $actStart->setLink(static::controllerUrl() . 'run/{unit_test_class}' . $groupQueryString)->setConfirm();

        if ($container == null) {
            echo $app->render();
        }
    }

    public static function cellCallback($table, $col, $row, $val) {
        return $val;
    }

    public function detail($class = null) {
        $app = CApp::instance();
        if ($class == null) {
            curl::redirect($this->controllerUrl());
        }
        $name = carr::last(explode('_', $class));

        $app->title('Test Case of ' . $name);
        $app->addBreadcrumb($this->getTitle(), $this->controllerUrl());

        $actionContainer = $app->addDiv()->addClass('action-container mb-3');
        $backAction = $actionContainer->addAction()->setLabel('Back')->addClass('btn-primary')->setIcon('fas fa-arrow-left')->setLink(static::controllerUrl() . static::groupQueryString());
        $rotateAction = $actionContainer->addAction()->setLabel('Run All')->addClass('btn-primary')->setIcon('fas fa-play')
            ->setConfirm()
            ->onClickListener()->addCustomHandler()
            ->setJs(<<<JS
                $('.btn-run-method').trigger('click');
            JS);

        $runner = CQC::createUnitTestRunner($class);
        $methods = $runner->getTestMethods();

        foreach ($methods as $method) {
            $template = $app->addTemplate()->setTemplate('CApp/QC/UnitTest/Method');
            $template->setVar('method', $method);
            $template->setVar('name', $name);
            $template->setVar('className', $class);
            $template->setVar('controllerUrl', static::controllerUrl());
        }

        echo $app->render();
    }

    public function check($className, $method = null) {
        $cfCommand = 'qc:phpunit --class=' . $className;
        if (strlen($method) > 0) {
            $cfCommand .= ' --method=' . $method;
        }

        $errCode = 0;
        $errMessage = '';
        $data = [];
        $output = '';

        try {
            $CFCli = new CApp_CFCli();

            $process = $CFCli->run($cfCommand);
            $errorOutput = $process->getErrorOutput();
            $output = $process->getOutput();
            if (strlen($errorOutput) > 0) {
                $errCode++;
                $errMessage = $errorOutput;
            }
        } catch (Exception $ex) {
            $errCode++;
            $errMessage = $ex->getMessage();
        }
        $data['output'] = $output;

        echo CApp_Base::jsonResponse($errCode, $errMessage, $data);
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
}
