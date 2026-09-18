<?php

/**
 * Description of UnitTestRunner.
 */

use PHPUnit\TextUI\TestRunner;

class CQC_Runner_UnitTestRunner extends CQC_RunnerAbstract {
    /**
     * @var CQC_UnitTestAbstract
     */
    protected $unitTest;

    /**
     * @return CQC_UnitTestAbstract
     */
    protected function getUnitTest() {
        if ($this->unitTest == null) {
            $className = $this->className;
            $this->unitTest = new $className();
        }

        return $this->unitTest;
    }

    /**
     * @return array
     */
    public function getTestMethods() {
        $phpMethods = get_class_methods($this->getUnitTest());

        $methods = c::collect($phpMethods)->filter(function ($method) {
            return cstr::startsWith($method, 'test');
        })->all();

        return $methods;
    }

    public function run() {
        $methods = $this->getTestMethods();
        $result = [];
        foreach ($methods as $method) {
            $result[$method] = $this->runMethod($method);
        }

        return $result;
    }

    public function runMethod($method) {
        $runner = new TestRunner();
        $suite = $runner->getTest(TBQC_UnitTest_MemberApi_BasicTestMemberApi::class);
        $errMessage = '';
        $output = 'AA';

        try {
            $result = $runner->run($suite, [], [], true);
        } catch (Throwable $t) {
            $errMessage = $t->getMessage() . PHP_EOL;
        }

        $return = TestRunner::FAILURE_EXIT;

        if (isset($result) && $result->wasSuccessful()) {
            $return = TestRunner::SUCCESS_EXIT;
        } elseif (!isset($result) || $result->errorCount() > 0) {
            $return = TestRunner::EXCEPTION_EXIT;
        }

        return new CQC_ProcessRunnerResult($output, $errMessage);
        /**
         * $isError = false;
         * $result = null;
         * try {
         * $processOptions = [];
         * $processOptions['method'] = $method;.
         *
         * $processRunner = new CQC_ProcessRunner($this->className, $processOptions);
         * $processRunnerResult = $processRunner->run();
         * } catch (Exception $ex) {
         * $isError = true;
         * }
         * if ($processRunnerResult->haveError()) {
         * throw new Exception($processRunnerResult->getErrorOutput());
         * }
         *
         * return $processRunnerResult->getOutput();
         */
    }
}
