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
     * Run the file rotation.
     *
     * @param bool $checkSize
     *
     * @throws CLogger_Rotator_Exception_FilenameFormatException
     * @throws RotateException
     *
     * @return array Array of files which have been rotated
     */
    public function run($checkSize = true) {
        if (!$this->hasFilenameFormat()) {
            throw new CLogger_Rotator_Exception_FilenameFormatException('You must set a filename format to match files against');
        }
        $rotated = [];
        $dir = new CLogger_Rotator_DirectoryIterator($this->getFilenameFormat()->getPath());
        $dir->setFilenameFormat($this->getFilenameFormat());
        foreach ($dir as $file) {
            /** @var CLogger_Rotator_DirectoryIterator $file */
            if ($file->isFile() && $file->isMatch()) {
                if ($checkSize) {
                    // Skip if rotate size specified and initial matched file doesn't exceed this
                    if ($this->hasSizeToRotate()) {
                        if ($file->getSize() < $this->getSizeToRotate()) {
                            continue;
                        }
                    }
                }
                // Rotate files
                for ($x = $this->keepNumber; $x > 0; $x--) {
                    $fileToRotate = $file->getPath() . '/' . $file->getRotatedFilename($x);
                    if (!file_exists($fileToRotate)) {
                        continue;
                    }
                    if ($x === $this->keepNumber) {
                        if (!$this->isDryRun()) {
                            if (!unlink($fileToRotate)) {
                                throw new CLogger_Rotator_Exception_RotateException('Cannot delete file: ' . $file->getRotatedFilename($x));
                            }
                        }
                        $rotated[] = $fileToRotate;
                    } else {
                        if (!$this->isDryRun()) {
                            if (!rename($fileToRotate, $file->getPath() . '/' . $file->getRotatedFilename($x + 1))) {
                                throw new CLogger_Rotator_Exception_RotateException('Cannot rotate file: ' . $file->getRotatedFilename($x));
                            }
                        }
                        $rotated[] = $fileToRotate;
                    }
                }
                if (!$this->isDryRun()) {
                    if (!rename($file->getPath() . '/' . $file->getBasename(), $file->getPath() . '/' . $file->getRotatedFilename(1))) {
                        throw new CLogger_Rotator_Exception_RotateException('Cannot rotate file: ' . $file->getBasename());
                    }
                }
                $rotated[] = $file->getPath() . '/' . $file->getBasename();
            }
        }

        return $rotated;
    }
}
