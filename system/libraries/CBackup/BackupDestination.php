<?php

use Carbon\Carbon;

class CBackup_BackupDestination {
    /**
     * @var Exception
     */
    public $connectionError;

    /**
     * @var CStorage_Adapter
     */
    protected $disk;

    /**
     * @var string
     */
    protected $diskName;

    /**
     * @var string
     */
    protected $backupName;

    /**
     * @var null|\CBackup_RecordCollection
     */
    protected $backupCollectionCache = null;

    public function __construct(?CStorage_Adapter $disk = null, $backupName, $diskName) {
        $this->disk = $disk;
        $this->diskName = $diskName;
        $this->backupName = preg_replace('/[^a-zA-Z0-9.]/', '-', $backupName);
    }

    public function disk() {
        return $this->disk;
    }

    public function diskName() {
        return $this->diskName;
    }

    public function filesystemType() {
        if (is_null($this->disk)) {
            return 'unknown';
        }
        $adapterClass = get_class($this->disk->getAdapter());
        $filesystemType = carr::last(explode('\\', $adapterClass));

        return strtolower($filesystemType);
    }

    public static function create($diskName, $backupName) {
        try {
            $disk = CStorage::instance()->disk($diskName);

            return new static($disk, $backupName, $diskName);
        } catch (Exception $exception) {
            $backupDestination = new static(null, $backupName, $diskName);
            $backupDestination->connectionError = $exception;

            return $backupDestination;
        }
    }

    public function write($file) {
        if (is_null($this->disk)) {
            throw CBackup_Exception_InvalidBackupDestinationException::diskNotSet($this->backupName);
        }
        $destination = $this->backupName . '/' . pathinfo($file, PATHINFO_BASENAME);
        $handle = fopen($file, 'r+');
        $this->disk->getDriver()->writeStream(
            $destination,
            $handle,
            $this->getDiskOptions()
        );
        if (is_resource($handle)) {
            fclose($handle);
        }

        return $destination;
    }

    public function backupName() {
        return $this->backupName;
    }

    public function backups() {
        if ($this->backupCollectionCache) {
            return $this->backupCollectionCache;
        }
        $files = is_null($this->disk) ? [] : $this->disk->allFiles($this->backupName);

        return $this->backupCollectionCache = CBackup_RecordCollection::createFromFiles(
            $this->disk,
            $files
        );
    }

    public function connectionError() {
        return $this->connectionError;
    }

    public function getDiskOptions() {
        return CF::config("storage.disks.{$this->diskName()}.backup_options", []);
    }

    public function isReachable() {
        if (is_null($this->disk)) {
            return false;
        }

        try {
            $this->disk->allFiles($this->backupName);

            return true;
        } catch (Exception $exception) {
            $this->connectionError = $exception;

            return false;
        }
    }

    public function usedStorage() {
        return $this->backups()->size();
    }

    public function newestBackup() {
        return $this->backups()->newest();
    }

    public function oldestBackup() {
        return $this->backups()->oldest();
    }

    public function newestBackupIsOlderThan(Carbon $date) {
        $newestBackup = $this->newestBackup();
        if (is_null($newestBackup)) {
            return true;
        }

        return $newestBackup->date()->lt($date);
    }

    public function fresh() {
        $this->backupCollectionCache = null;

        return $this;
    }
}
