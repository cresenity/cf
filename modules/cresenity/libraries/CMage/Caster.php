<?php

class CMage_Caster {
    protected $mage;

    protected $controller;

    public function __construct(CMage_AbstractMage $mage, $controllerClass) {
        $this->mage = $mage;
        $this->controllerClass = $controllerClass;
    }

    public function index() {
        $method = $this->createMethod('Index');
        $method->execute();
    }

    public function add() {
        $method = $this->createMethod('Add');
        $method->execute();
    }

    public function edit($id) {
        $method = $this->createMethod('Edit');
        $method->setId($id);
        $method->execute();
    }

    protected function createMethod($methodName) {
        $methodClassName = 'CMage_Method_' . $methodName . 'Method';
        $methodClass = new $methodClassName($this->mage, $this->controllerClass);
        return $methodClass;
    }
}
