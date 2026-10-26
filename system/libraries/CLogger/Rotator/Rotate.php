<?php

/**
 * Class to manage file rotation.
 */
class CLogger_Rotator_Rotate extends CLogger_Rotator_AbstractRotate {
    /**
     * Number of copies to keep, defaults to 10.
     *
     * @var int
     */
    protected $keepNumber = 10;

    /**
     * Filesize to rotate files on (in bytes).
     *
     * @var int
     */
    protected $sizeToRotate = 1024 * 1024;

    /**
     * Set the number of old copies to keep.
     *
     * @param mixed $number
     *
     * @return CLogger_Rotator_Rotate
     */
    public function keep($number) {
        $this->keepNumber = $number;

        return $this;
    }

    /**
     * Return number of old copies to keep.
     *
     * @return int
     */
    public function getKeepNumber() {
        return $this->keepNumber;
    }

    /**
     * Set the filesize to rotate files on.
     *
     * @param string $size Define as an number with a string suffix indicating the unit measurement, e.g. 5MB
     *
     * @throws CLogger_Rotator_Exception_RotateException
     *
     * @return CLogger_Rotator_Rotate
     */
    public function size($size) {
        if (is_string($size)) {
            if (!preg_match('/^(\d+)\s?(B|KB|MB|GB)$/i', $size, $m)) {
                throw new CLogger_Rotator_Exception_RotateException('You must define size in the format 10B|KB|MB|GB');
            }

            switch (strtoupper($m[2])) {
                case 'B':
                    $size = $m[1];

                    break;
                case 'KB':
                    $size = $m[1] * 1024;

                    break;
                case 'MB':
                    $size = $m[1] * 1024 * 1024;

                    break;
                case 'GB':
                    $size = $m[1] * 1024 * 1024 * 1024;

                    break;
            }
        }
        $size = (int) $size;
        if ($size === 0) {
            throw new CLogger_Rotator_Exception_RotateException('You must define a non-zero size to rotate files on');
        }
        $this->sizeToRotate = $size;

        return $this;
    }

    /**
     * Return filesize to rotate files on (in bytes).
     *
     * @return int
     */
    public function getSizeToRotate() {
        return $this->sizeToRotate;
    }

    /**
     * Have we defined a filesize to rotate on?
     *
     * @return bool
     */
    public function hasSizeToRotate() {
        return is_int($this->getSizeToRotate()) && $this->getSizeToRotate() !== 0;
    }

    public function forceRotate() {
        $this->run(false);
    }

    /**
     * Lock file per pola nama: proses yang auto-rotate log yang sama bergantian.
     *
     * @return string
     */
    public function getLockFile() {
        $format = $this->getFilenameFormat();

        return $format->getPath() . DIRECTORY_SEPARATOR . '.' . preg_replace('/[^A-Za-z0-9._-]/', '_', $format->getFilenamePattern()) . '.rotate.lock';
    }

    /**
     * Run the file rotation.
     *
     * @param bool $checkSize
     *
     * @throws CLogger_Rotator_Exception_FilenameFormatException
     * @throws CLogger_Rotator_Exception_RotateException
     *
     * @return array Array of files which have been rotated
     */
    public function run($checkSize = true) {
        if (!$this->hasFilenameFormat()) {
            throw new CLogger_Rotator_Exception_FilenameFormatException('You must set a filename format to match files against');
        }
        if ($this->isDryRun()) {
            return $this->rotateMatchedFiles($checkSize);
        }
        $lock = @fopen($this->getLockFile(), 'c');
        if ($lock === false) {
            //mis. lock milik user lain (web vs cron): rotasi tanpa lock seperti sebelumnya
            return $this->rotateMatchedFiles($checkSize);
        }

        try {
            flock($lock, LOCK_EX);

            //ukuran dicek setelah lock, proses yang menunggu melihat berkas yang sudah dirotasi
            return $this->rotateMatchedFiles($checkSize);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param bool $checkSize
     *
     * @throws CLogger_Rotator_Exception_RotateException
     *
     * @return array
     */
    protected function rotateMatchedFiles($checkSize) {
        $path = $this->getFilenameFormat()->getPath();
        $basenames = [];
        $dir = new CLogger_Rotator_DirectoryIterator($path);
        $dir->setFilenameFormat($this->getFilenameFormat());
        foreach ($dir as $file) {
            /** @var CLogger_Rotator_DirectoryIterator $file */
            if ($file->isFile() && $file->isMatch()) {
                // Skip if rotate size specified and initial matched file doesn't exceed this
                if ($checkSize && $this->hasSizeToRotate() && $file->getSize() < $this->getSizeToRotate()) {
                    continue;
                }
                $basenames[] = $file->getBasename();
            }
        }

        //rename setelah iterasi selesai, supaya berkas hasil rotasi tidak ikut teriterasi
        $rotated = [];
        foreach ($basenames as $basename) {
            // Rotate files
            for ($x = $this->keepNumber; $x > 0; $x--) {
                $fileToRotate = $path . '/' . $basename . '.' . $x;
                if (!file_exists($fileToRotate)) {
                    continue;
                }
                if (!$this->isDryRun()) {
                    if ($x === $this->keepNumber) {
                        if (!unlink($fileToRotate)) {
                            throw new CLogger_Rotator_Exception_RotateException('Cannot delete file: ' . $basename . '.' . $x);
                        }
                    } elseif (!rename($fileToRotate, $path . '/' . $basename . '.' . ($x + 1))) {
                        throw new CLogger_Rotator_Exception_RotateException('Cannot rotate file: ' . $basename . '.' . $x);
                    }
                }
                $rotated[] = $fileToRotate;
            }
            if (!$this->isDryRun()) {
                if (!rename($path . '/' . $basename, $path . '/' . $basename . '.1')) {
                    throw new CLogger_Rotator_Exception_RotateException('Cannot rotate file: ' . $basename);
                }
            }
            $rotated[] = $path . '/' . $basename;
        }

        return $rotated;
    }
}
