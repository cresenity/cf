<?php


class CBackup_Event_HouseKeepingWasSuccessful {

    /** @var CBackup_BackupDestination */
    public $backupDestination;

    public function __construct(CBackup_BackupDestination $backupDestination) {
        $this->backupDestination = $backupDestination;
    }

}
