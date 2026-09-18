<?php


class CBackup_Monitor {

    /** @var CBackup_BackupDestination */
    protected $backupDestination;

    /** @var array */
    protected $healthChecks;

    /** @var CBackup_Monitor_HealthCheckFailure|null */
    protected $healthCheckFailure;

    public function __construct(CBackup_BackupDestination $backupDestination, array $healthChecks = []) {
        $this->backupDestination = $backupDestination;
        $this->healthChecks = $healthChecks;
    }

    /**
     * 
     * @return CBackup_BackupDestination
     */
    public function backupDestination() {
        return $this->backupDestination;
    }

    /**
     * 
     * @param CBackup_Monitor_AbstractHealthCheck $check
     * @return \CBackup_Monitor_HealthCheckFailure|boolean
     */
    public function check(CBackup_Monitor_AbstractHealthCheck $check) {
        try {
            $check->checkHealth($this->backupDestination());
        } catch (Exception $exception) {
            return new CBackup_Monitor_HealthCheckFailure($check, $exception);
        }
        return true;
    }

    public function getHealthChecks() {
        return c::collect($this->healthChecks)->prepend(new CBackup_Monitor_HealthCheck_IsReachable());
    }

    public function getHealthCheckFailure() {
        return $this->healthCheckFailure;
    }

    public function isHealthy() {
        $healthChecks = $this->getHealthChecks();
        foreach ($healthChecks as $healthCheck) {
            $checkResult = $this->check($healthCheck);
            if ($checkResult instanceof CBackup_Monitor_HealthCheckFailure) {
                $this->healthCheckFailure = $checkResult;
                return false;
            }
        }
        return true;
    }

}
