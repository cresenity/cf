<?php


class CBackup_MonitorFactory {

    /**
     * 
     * @return CCollection
     */
    public static function createForMonitorConfig() {
        $monitorConfiguration = CBackup::getConfig('monitor_backups');
        return c::collect($monitorConfiguration)->flatMap(function (array $monitorProperties) {
                    return self::createForSingleMonitor($monitorProperties);
                })->sortBy(function (CBackup_Monitor $backupDestinationStatus) {
                    return $backupDestinationStatus->backupDestination()->backupName() . '-' .
                            $backupDestinationStatus->backupDestination()->diskName();
                });
    }

    public static function createForSingleMonitor(array $monitorConfig) {
        return c::collect($monitorConfig['disks'])->map(function ($diskName) use ($monitorConfig) {
                    $backupDestination = CBackup_BackupDestination::create($diskName, $monitorConfig['name']);
                    return new CBackup_Monitor($backupDestination, static::buildHealthChecks($monitorConfig));
                });
    }

    protected static function buildHealthChecks($monitorConfig) {
        return c::collect(carr::get($monitorConfig, 'health_checks'))->map(function ($options, $class) {
                    if (is_int($class)) {
                        $class = $options;
                        $options = [];
                    }
                    return static::buildHealthCheck($class, $options);
                })->toArray();
    }

    protected static function buildHealthCheck($class, $options) {
        // A single value was passed - we'll instantiate it manually assuming it's the first argument
        if (!is_array($options)) {
            return new $class($options);
        }
        // A config array was given. Use reflection to match arguments
        return CContainer::getInstance()->makeWith($class, $options);
    }

}
