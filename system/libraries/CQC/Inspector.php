<?php

/**
 * Description of Inspector
 */
class CQC_Inspector {

    /**
     *
     * @var string
     */
    protected $className;

    /**
     *
     * @var CQC_QCAbstract
     */
    protected $qcObject;

    /**
     *
     * @var ReflectionClass
     */
    protected $reflectionObject;

    public function __construct($className) {
        $this->className = $className;
    }

    public function getType() {
        $parents = class_parents($this->className);
        if (in_array(CQC_UnitTestAbstract::class, $parents)) {
            return CQC::TYPE_UNIT_TEST;
        }
        if (in_array(CQC_Checker_DatabaseCheckerAbstract::class, $parents)) {
            return CQC::TYPE_DATABASE_CHECKER;
        }
        throw new Exception('Error, class ' . $this->className . ' is not inherited from CQC abstract class');
    }

    /**
     * 
     * @return ReflectionClass
     */
    public function getReflection() {
        if ($this->reflectionObject == null) {
            $this->reflectionObject = new \ReflectionClass($this->className);
        }
        return $this->reflectionObject;
    }

    public function createProcessor() {
        $type = $this->getType();
        switch ($type) {
            case CQC::TYPE_UNIT_TEST:
                return new CQC_Process_UnitTestProcess($this->className);
        }
        throw new Exception('Unhandled for type: ' . $type . ' in create processor');
    }

    /**
     * 
     * @return string
     */
    public function getFileName() {
        return $this->getReflection()->getFileName();
    }

}
