<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Oct 7, 2020
 */
trait CTrait_Controller_Application_QC_DatabaseChecker {
    protected function getTitle() {
        return 'Database Checker';
    }

    public function index() {
        $app = CApp::instance();
        $db = c::db();

        $app->title($this->getTitle());
        $actionContainer = $app->addDiv()->addClass('action-container mb-3');

        $reloadAction = $actionContainer->addAction()->setLabel('Reload')->addClass('btn-primary')->setIcon('fas fa-sync');

        $tableCheckerDiv = $app->addDiv('tableChecker');

        $handlerActionClick = $reloadAction->onClickListener()->addReloadHandler('reload');
        $handlerActionClick->setTarget('tableChecker');
        $handlerActionClick->setUrl($this->controllerUrl() . 'reloadTabChecker');

        $reloadOptions = [];
        static::reloadTabChecker($tableCheckerDiv, $reloadOptions);

        echo $app->render();
    }

    public static function reloadTabChecker($container = null, $options = []) {
        $app = $container;
        if ($container == null) {
            $app = CApp::instance();
        }
        $qcManager = CQC_Manager::instance();
        $request = $options;
        if ($request == null) {
            $request = CApp_Base::getRequest();
        }
        $db = c::db();
        $listChecker = $qcManager->databaseCheckers();
        $dataChecker = [];
        $groupTab = carr::get($_GET, 'group');
        if ($qcManager->haveDatabaseCheckerGroup()) {
            $tabList = $app->addTabList()->setAjax(false);
            $groupKeys = $qcManager->getDatabaseCheckerGroupsKey();
            $notGrouped = $qcManager->databaseCheckers(false);
            if (count($notGrouped) > 0) {
                $tab = $tabList->addTab()->setLabel('Not Grouped');
                static::reloadTableChecker($tab, ['group' => false]);
            }
            foreach ($groupKeys as $groupName) {
                $tab = $tabList->addTab()->setLabel($groupName);
                if ($groupTab == $groupName) {
                    $tab->setActive();
                }

                static::reloadTableChecker($tab, ['group' => $groupName]);
            }
        } else {
            $div = $app->addDiv();
            static::reloadTableChecker($div);
        }

        if ($container == null) {
            echo $app->render();
        }
    }

    public static function reloadTableChecker($container = null, $options = []) {
        $app = $container;
        if ($container == null) {
            $app = CApp::instance();
        }
        $qcManager = CQC_Manager::instance();
        $request = $options;
        if ($request == null) {
            $request = CApp_Base::getRequest();
        }
        $db = c::db();
        $group = carr::get($request, 'group');
        $listChecker = $qcManager->databaseCheckers($group);
        $dataChecker = [];
        foreach ($listChecker as $kChecker => $vChecker) {
            $template = $app->addTemplate()->setTemplate('CApp/QC/DatabaseChecker/Record');
            $template->setVar('name', $vChecker);
            $template->setVar('className', $kChecker);
            $template->setVar('controllerUrl', static::controllerUrl());
        }

        if ($container == null) {
            echo $app->render();
        }
    }

    public function check($className) {
        $runner = CQC::createDatabaseCheckerRunner($className);

        $errCode = 0;
        $errMessage = '';
        $data = [];

        try {
            $data = $runner->run();
        } catch (Exception $ex) {
            $errCode++;
            $errMessage = $ex->getMessage();
            cdbg::dd($ex);
        }

        echo CApp_Base::jsonResponse($errCode, $errMessage, $data);
    }
}
