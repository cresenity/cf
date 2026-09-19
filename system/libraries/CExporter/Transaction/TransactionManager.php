<?php

class CExporter_Transaction_TransactionManager extends CBase_ManagerAbstract {
    private static $instance;

    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    private function __construct() {
        parent::__construct();
    }

    /**
     * @return string
     */
    public function getDefaultDriver() {
        return CF::config('exporter.transactions.handler', CF::config('excel.transactions.handler', 'db')) ?: 'null';
    }

    /**
     * @return CExporter_Transaction_Handler_NullHandler
     */
    public function createNullDriver() {
        return new CExporter_Transaction_Handler_NullHandler();
    }

    /**
     * @return CExporter_Transaction_Handler_DbHandler
     */
    public function createDbDriver() {
        return new CExporter_Transaction_Handler_DbHandler(
            c::db()
        );
    }

    /**
     * Get a driver instance.
     *
     * @param null|string $driver
     *
     * @throws \InvalidArgumentException
     *
     * @return CExporter_Transaction_HandlerInterface
     */
    public function driver($driver = null) {
        return parent::driver($driver);
    }
}
