<?php

/**
 * Description of UnitTest.
 */
trait CTrait_Controller_Application_QC_Testing {
    protected function getTitle() {
        return 'Unit Test';
    }

    public function index() {
        $app = c::app();

        $app->title($this->getTitle());

        $testing = CQC::manager()->testing();

        $repository = $testing->repository();

        $divDaemon = $app->addDiv();
        $this->daemonContainer($divDaemon);
        if (!$testing->daemonIsRunning()) {
            return $app;
        }

        $tests = $repository->getTests()->toArray();

        $runTestUrl = $this->controllerUrl() . 'run';
        $pollUrl = $this->controllerUrl() . 'poll';
        $resetUrl = $this->controllerUrl() . 'reset';
        $runAllUrl = $this->controllerUrl() . 'run/all';
        $codeUrl = $this->controllerUrl() . 'code';
        $app->addView('cresenity.qc.tests', [
            'tests' => $tests,
            'runTestUrl' => $runTestUrl,
            'pollUrl' => $pollUrl,
            'resetUrl' => $resetUrl,
            'runAllUrl' => $runAllUrl,
            'codeUrl' => $codeUrl,
        ]);

        return $app;
    }

    protected function daemonContainer($container = null) {
        $app = $container ?: c::app();
        /** @var CApp $app */
        $divDaemon = $app->addDiv()->addClass('mb-3');

        $testing = CQC::manager()->testing();
        $isRunning = $testing->daemonIsRunning();
        $divDaemon->setAttr('style', 'display:flex;justify-content:space-between');
        $divDaemon->addDiv()->add('Daemon Status : ' . (
            $isRunning
            ? '<span class="badge badge-success">RUNNING</span>'
            : '<span class="badge badge-danger">STOPPED</span>'
        ));
        $actionList = $divDaemon->addDiv()->addActionList();
        $action = $actionList->addAction();
        $action->setLabel($isRunning ? 'Stop' : 'Start');
        $action->addClass($isRunning ? 'btn-danger' : 'btn-success');
        $action->setConfirm();
        $action->setLink($this->controllerUrl() . 'daemon/' . ($isRunning ? 'stop' : 'start'));
        $action = $actionList->addAction();
        $action->setLabel('Log');
        $action->addClass('btn-info');
        $action->onClickListener()->addDialogHandler()
            ->setUrl($this->controllerUrl() . 'log/daemon')
            ->setSidebar()
            ->setFull()
            ->setTitle('Daemon Log');
    }

    public function daemon($method) {
        if ($method == 'start') {
            CQC::manager()->testing()->startDaemon();
        }
        if ($method == 'stop') {
            CQC::manager()->testing()->stopDaemon();
        }

        return c::redirect()->back();
    }

    public function reset() {
        CQC::manager()->testing()->repository()->resetAllTest();

        return $this->success();
    }

    public function run($all = null) {
        $testId = c::request()->testId;
        $result = [];

        if ($all == 'all') {
            $result = CQC::manager()->testing()->repository()->runAllTest();
        } else {
            if ($testId) {
                $result = CQC::manager()->testing()->repository()->runTest($testId);
            }
        }

        return $this->success($result);
    }

    public function test() {
        $cfCli = CConsole::kernel()->cfCli();
        $exitCode = $cfCli->call('test /home/appittro/public_html/application/semut/default/tests/Unit/UtilsTest.php');

        $output = $cfCli->output();
        cdbg::dd($output, $exitCode);
        $test = CQC_Testing_Model_Test::find(1);
        $ok = false;

        $lines = '';
        $repository = CQC::manager()->testing()->repository();

        cdbg::dd($repository->getAllRun());
        $run = $repository->markTestAsRunning($test);
        $file = $test->path . DS . $test->name;

        //$this->log('RUNNING: ' . $command . ' - at ' . $test->suite->path . ' - cwd:' . getcwd(), 'comment');

        $logOutput = '';
        $executor = CQC::createExecutor();
        $startedAt = c::now();
        for ($times = 0; $times <= $test->suite->retries; $times++) {
            if ($times > 0) {
                $this->log('retrying...');
            }
            $cfCli = CConsole::kernel()->cfCli();
            $exitCode = $cfCli->call('test ' . $file);
            $lines = $cfCli->output();
            if ($ok = $exitCode === 0) {
                break;
            }
        }

        $endedAt = c::now();
        $this->log($ok ? 'OK' : 'FAILED');
        $repository->storeTestResult($run, $test, $lines, $ok, $startedAt, $endedAt);
        cdbg::dd($lines);
    }

    public function poll() {
        $tests = CQC::manager()->testing()->repository()->getTests()->toArray();

        return $this->success($tests);
    }

    public function log($method) {
        $app = c::app();
        if ($method == 'daemon') {
            $divLog = $app->addDiv()->addClass('console');
            $divLog->add(CQC::manager()->testing()->daemonLog());

            return $app;
        }

        return c::abort(404);
    }

    protected function success($data = []) {
        return CApp_Base::toJsonResponse(0, '', $data);
    }

    public function code() {
        $app = c::app();
        $test = CQC::manager()->testing()->repository()->findTest(c::request()->testId);
        $file = $test->getFile();
        $app->addDiv()->addPrismCode()->add(c::e(CFile::get($file)));

        return $app;
    }
}
