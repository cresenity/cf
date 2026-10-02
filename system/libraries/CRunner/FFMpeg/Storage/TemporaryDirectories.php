<?php

class CRunner_FFMpeg_Storage_TemporaryDirectories {
    /**
     * Root of the temporary directories.
     *
     * @var string
     */
    private $root;

    /**
     * Array of all directories.
     *
     * @var array
     */
    private $directories = [];

    /**
     * Sets the root and removes the trailing slash.
     */
    public function __construct() {
        $this->root = rtrim(CTemporary::getDirectory('runner', 'ffmpeg'), '/\\');
    }

    /**
     * Returns the full path a of new temporary directory.
     *
     * @return string
     */
    public function create() {
        $directory = $this->root . '/' . bin2hex(random_bytes(8));

        mkdir($directory);

        return $this->directories[] = $directory;
    }

    /**
     * Loop through all directories and delete them.
     */
    public function deleteAll() {
        $filesystem = new CFile();

        foreach ($this->directories as $directory) {
            $filesystem->deleteDirectory($directory);
        }

        $this->directories = [];
    }
}
