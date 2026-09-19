<?php

class CBackup_Monitor_HealthCheckFailure {
    /**
     * @var \CBackup_Monitor_AbstractHealthCheck
     */
    protected $healthCheck;

    /**
     * @var \Exception
     */
    protected $exception;

    public function __construct(CBackup_Monitor_AbstractHealthCheck $healthCheck, Exception $exception) {
        $this->healthCheck = $healthCheck;
        $this->exception = $exception;
    }

    /**
     * @return CBackup_Monitor_AbstractHealthCheck
     */
    public function healthCheck() {
        return $this->healthCheck;
    }

    public function exception() {
        return $this->exception;
    }

    public function wasUnexpected() {
        return !$this->exception instanceof CBackup_Monitor_Exception_InvalidHealthCheckException;
    }
}
