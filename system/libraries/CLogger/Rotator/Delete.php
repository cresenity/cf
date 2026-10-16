<?php

/**
 * Class to manage file deletion.
 */
class CLogger_Rotator_Delete extends CLogger_Rotator_AbstractRotate {
    /**
     * Current date & time for file comparison purposes.
     *
     * @var DateTime
     */
    protected $now;

    /**
     * Paths it is safe to perform recursive delete on.
     *
     * @var array
     */
    protected $safeRecursiveDeletePaths = [];

    /**
     * Set the current date & time.
     */
    public function setNow(DateTime $dateTime) {
        $this->now = $dateTime;
    }

    /**
     * Return current time.
     *
     * Defaults to current date, midnight
     *
     * @return DateTime
     */
    public function getNow() {
        if (!$this->now instanceof DateTime) {
            $now = new DateTime();
            $this->now = new DateTime($now->format('Y-m-d') . ' 00:00:00');
        }

        return $this->now;
    }

    /**
     * Add a folder path it is safe to perform recursive delete on.
     *
     * If you don't do this you cannot recursively delete a folder, this is for safety!
     *
     * @param string $path Folder path
     *
     * @throws RotateException
     */
    public function addSafeRecursiveDeletePath($path) {
        if (!file_exists($path) || !is_dir($path)) {
            throw new CLogger_Rotator_Exception_RotateException('Cannot set path as a safe recursive delete path since does not exist or is not a folder');
        }
        $this->safeRecursiveDeletePaths[] = $path;
    }

    /**
     * Delete a file or a folder containing files.
     *
     * If you try to delete a folder containing files, you must add the path via addSafeRecursiveDeletePath()
     *
     * @throws RotateException
     *
     * @return array Array of deleted files
     */
    protected function delete($path) {
        if (is_dir($path)) {
            return $this->recursiveDeleteFolder($path);
        } else {
            if (!unlink($path)) {
                throw new CLogger_Rotator_Exception_RotateException('Cannot delete file: ' . $path);
            }

            return [$path];
        }
    }

    /**
     * Recursively delete a folder and its contents.
     *
     * Warning: this can be dangerous, you must add paths that are safe to perform recursive deletion on via addSafeRecursiveDeletePath()
     * otherwise this function will fail
     *
     * @throws RotateException
     *
     * @return array Array of deleted files
     */
    protected function recursiveDeleteFolder($folderPath) {
        $deleted = [];
        $folderPath = realpath($folderPath);
        if (!is_dir($folderPath)) {
            throw new CLogger_Rotator_Exception_RotateException("Path $folderPath is not a folder");
        }
        $safeToDelete = false;
        foreach ($this->safeRecursiveDeletePaths as $safePath) {
            // harus di DALAM safe path: pembanding pakai realpath + pemisah, supaya log/ tidak meliputi logs-other/
            $safePath = realpath($safePath);
            if ($safePath !== false && strpos($folderPath, rtrim($safePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0) {
                $safeToDelete = true;
            }
        }
        if (!$safeToDelete) {
            throw new CLogger_Rotator_Exception_RotateException("It is not safe to perform a recursive delete on path: $folderPath");
        }
        $dir = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folderPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($dir as $file) {
            if (!$this->isDryRun()) {
                if ($file->isDir()) {
                    if (!rmdir($file->getPathname())) {
                        throw new CLogger_Rotator_Exception_RotateException('Cannot delete folder: ' . $file->getPathname());
                    }
                } else {
                    if (!unlink($file->getPathname())) {
                        throw new CLogger_Rotator_Exception_RotateException('Cannot delete file: ' . $file->getPathname());
                    }
                }
            }
            $deleted[] = $file->getPathname();
        }
        if (!$this->isDryRun()) {
            rmdir($folderPath);
            $deleted[] = $folderPath;
        }
        unset($dir, $file);

        return $deleted;
    }

    /**
     * Delete files by the filename time stored within the filename itself.
     *
     * For example delete all files with a filename date pattern of payment.2016-01-01.log over 3 months old
     *
     * @param mixed $timePeriod DateInterval object, or time interval supported by DateInterval::createFromDateString, e.g. 3 months
     *
     * @throws FilenameFormatException
     * @throws RotateException
     *
     * @return array Array of deleted files
     */
    public function deleteByFilenameTime($timePeriod) {
        if (!$this->hasFilenameFormat()) {
            throw new CLogger_Rotator_Exception_FilenameFormatException('You must set a filename format to match files against');
        }
        $deleted = [];
        if ($timePeriod instanceof DateInterval) {
            $interval = $timePeriod;
        } else {
            $interval = DateInterval::createFromDateString($timePeriod);
        }
        $oldestDate = clone $this->getNow();
        $oldestDate = $oldestDate->sub($interval);
        $dir = new CLogger_Rotator_DirectoryIterator($this->getFilenameFormat()->getPath());
        $dir->setFilenameFormat($this->getFilenameFormat());
        foreach ($dir as $file) {
            /** @var CLogger_Rotator_DirectoryIterator $file */
            if (!$file->isDot() && $file->isMatch()) {
                // tanggal gagal di-parse (false) bukan berarti lebih tua - berkas itu dilewati
                $filenameDate = $file->getFilenameDate();
                if ($filenameDate instanceof DateTime && $filenameDate < $oldestDate) {
                    if (!$this->isDryRun()) {
                        $results = $this->delete($file->getPathname());
                        $deleted = array_merge($deleted, $results);
                    } else {
                        $deleted[] = $file->getPathname();
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * Delete files by the last modified time of the filename.
     *
     * For example delete all files over 3 months old.
     *
     * @param mixed $timePeriod DateInterval object, or time interval supported by DateInterval::createFromDateString, e.g. 3 months
     *
     * @throws FilenameFormatException
     * @throws RotateException
     *
     * @return array Array of deleted files
     */
    public function deleteByFileModifiedDate($timePeriod) {
        if (!$this->hasFilenameFormat()) {
            throw new CLogger_Rotator_Exception_FilenameFormatException('You must set a filename format to match files against');
        }
        $deleted = [];
        if ($timePeriod instanceof DateInterval) {
            $interval = $timePeriod;
        } else {
            $interval = DateInterval::createFromDateString($timePeriod);
        }
        $oldestDate = clone $this->getNow();
        $oldestDate = $oldestDate->sub($interval);
        $dir = new CLogger_Rotator_DirectoryIterator($this->getFilenameFormat()->getPath());
        $dir->setFilenameFormat($this->getFilenameFormat());
        foreach ($dir as $file) {
            /** @var CLogger_Rotator_DirectoryIterator $file */
            if (!$file->isDot() && $file->isMatch()) {
                $fileDate = new DateTime();
                $fileDate->setTimestamp($file->getMTime());
                if ($fileDate < $oldestDate) {
                    if (!$this->isDryRun()) {
                        $results = $this->delete($file->getPathname());
                        $deleted = array_merge($deleted, $results);
                    } else {
                        $deleted[] = $file->getPathname();
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * Delete files by a custom callback function.
     *
     * For example, do a database lookup on the order ID stored within the filename to ensure the order has been completed, if so delete the file.
     *
     * Callback function must accept one parameter (DirectoryIterator $file) and must return true (delete file) or false (do not delete file)
     *
     * Example to delete all files over 5Mb in size:
     * $callback = function(DirectoryIterator $file) {
     *     if ($file->getSize() > 5 * 1024 * 1024) {
     *         return true;
     *     } else {
     *         return false;
     *     }
     * }
     *
     * @throws FilenameFormatException
     * @throws RotateException
     *
     * @return array Array of deleted files
     */
    public function deleteByCallback(callable $callback) {
        if (!$this->hasFilenameFormat()) {
            throw new CLogger_Rotator_Exception_FilenameFormatException('You must set a filename format to match files against');
        }
        $deleted = [];
        $dir = new CLogger_Rotator_DirectoryIterator($this->getFilenameFormat()->getPath());
        $dir->setFilenameFormat($this->getFilenameFormat());
        foreach ($dir as $file) {
            /** @var CLogger_Rotator_DirectoryIterator $file */
            if (!$file->isDot() && $file->isMatch()) {
                if ($callback($file)) {
                    if (!$this->isDryRun()) {
                        $results = $this->delete($file->getPathname());
                        $deleted = array_merge($deleted, $results);
                    } else {
                        $deleted[] = $file->getPathname();
                    }
                }
            }
        }

        return $deleted;
    }
}
