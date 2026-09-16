<?php

/**
 * A minimal concrete daemon service for the CDaemon tests.
 *
 * CDaemon_ServiceAbstract leaves only `setup()` and `execute()` to the
 * subclass, so this is the smallest thing that can be instantiated.
 */
class DaemonTestService extends CDaemon_ServiceAbstract {
    /**
     * @var int
     */
    public $setupCount = 0;

    /**
     * @var int
     */
    public $executeCount = 0;

    /**
     * @var string[]
     */
    public $logged = [];

    /**
     * @return void
     */
    public function setup() {
        $this->setupCount++;
    }

    /**
     * @return void
     */
    public function execute() {
        $this->executeCount++;
    }

    /**
     * @return array
     */
    public function exposeHandledSignals() {
        return $this->handledSignals();
    }

    /**
     * Kept out of the filesystem; the tests only care that it was called.
     *
     * @param string $message
     * @param string $label
     * @param int    $indent
     *
     * @return void
     */
    public function log($message, $label = '', $indent = 0) {
        $this->logged[] = $message;
    }

    /**
     * @param array $statList each entry a list with `duration` and `idle`
     *
     * @return void
     */
    public function setStats(array $statList) {
        $property = new ReflectionProperty(CDaemon_ServiceAbstract::class, 'stats');
        $property->setAccessible(true);
        $property->setValue($this, $statList);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    public function setProperty($name, $value) {
        $property = new ReflectionProperty(CDaemon_ServiceAbstract::class, $name);
        $property->setAccessible(true);
        $property->setValue($this, $value);
    }

    /**
     * @param string $name
     * @param array  $argumentList
     *
     * @return mixed
     */
    public function callProtected($name, array $argumentList = []) {
        $method = new ReflectionMethod(CDaemon_ServiceAbstract::class, $name);
        $method->setAccessible(true);

        return $method->invokeArgs($this, $argumentList);
    }
}
