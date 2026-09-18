<?php


class CBackup_Event_HouseKeepingHasFailed {

    /** @var \Exception */
    public $exception;

    /** @var CBackup_BackupDestination|null */
    public $backupDestination;

    public function __construct(Exception $exception, ?CBackup_BackupDestination $backupDestination = null) {
        $this->exception = $exception;

        $this->backupDestination = $backupDestination;
    }

}
