<?php

/**
 * Description of UnitTestProcess
 */
class CQC_Process_UnitTestProcess extends CQC_ProcessAbstract {
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

    public function run($options = []) {
        $methods = carr::wrap(carr::get($options, 'method', $this->getTestMethods()));

        $result = [];
        foreach ($methods as $method) {
            $result[$method] = $this->runMethod($method);
        }
        return $result;
    }

    public function runMethod($method) {
        $isError = false;
        $exception = null;
        $unitTest = $this->getUnitTest();
        try {
            $unitTest->build();
            $unitTest->setUp();
            $unitTest->$method();
            $unitTest->tearDown();
            $unitTest->destroy();
        } catch (Exception $ex) {
            $isError = true;
            $exception = $ex;
        }
        if ($exception) {
            throw $exception;
        }
        $result = $unitTest->result();
        return $result;
    }
}
