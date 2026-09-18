<?php

class CHouseKeeping_FileTemp_ResourceFileTemp {
    /**
     * Delete conversion scratch files older than the given hours: the per-call `temp/<random>` directories,
     * and the `<Ymd>/...` trees the older code left behind, both under `temp/resource/` (shared) and
     * `temp/resource/<appCode>/`.
     *
     * @param int         $keepHours
     * @param null|string $basePath  defaults to DOCROOT temp/resource
     *
     * @return bool true when something was deleted
     */
    public static function execute($keepHours = 24, $basePath = null) {
        $basePath = rtrim($basePath ?: DOCROOT . 'temp' . DS . 'resource', DS);
        if (!is_dir($basePath)) {
            return false;
        }
        $threshold = time() - ($keepHours * 3600);
        $executed = false;

        foreach (static::subdirectories($basePath) as $directory) {
            $name = basename($directory);
            if ($name === 'temp') {
                $executed = static::pruneScratchDirectories($directory, $threshold) || $executed;
            } elseif (static::isYmd($name)) {
                $executed = static::pruneDateDirectory($directory, $threshold) || $executed;
            } else {
                // an app folder: temp/ and <Ymd>/ one level down
                foreach (static::subdirectories($directory) as $appDirectory) {
                    $appName = basename($appDirectory);
                    if ($appName === 'temp') {
                        $executed = static::pruneScratchDirectories($appDirectory, $threshold) || $executed;
                    } elseif (static::isYmd($appName)) {
                        $executed = static::pruneDateDirectory($appDirectory, $threshold) || $executed;
                    }
                }
            }
        }

        // the configured scratch base, when it lives somewhere else
        $configured = CF::config('resource.temporary_directory_path');
        if ($configured && is_dir($configured) && strpos(rtrim($configured, DS), $basePath) !== 0) {
            $executed = static::pruneScratchDirectories($configured, $threshold) || $executed;
        }

        return $executed;
    }

    /**
     * @param string $directory
     * @param int    $threshold
     *
     * @return bool
     */
    protected static function pruneScratchDirectories($directory, $threshold) {
        $executed = false;
        foreach (static::subdirectories($directory) as $scratch) {
            if (filemtime($scratch) > $threshold) {
                continue;
            }
            static::log('deleting folder ' . $scratch);
            static::deleteDirectory($scratch);
            $executed = true;
        }

        return $executed;
    }

    /**
     * A whole Ymd tree goes once that day ended more than the threshold ago.
     *
     * @param string $directory
     * @param int    $threshold
     *
     * @return bool
     */
    protected static function pruneDateDirectory($directory, $threshold) {
        $endOfDay = CCarbon::createFromFormat('Ymd', basename($directory))->endOfDay()->getTimestamp();
        if ($endOfDay > $threshold) {
            return false;
        }
        static::log('deleting folder ' . $directory);
        static::deleteDirectory($directory);

        return true;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    protected static function isYmd($name) {
        return strlen($name) === 8 && ctype_digit($name) && checkdate((int) substr($name, 4, 2), (int) substr($name, 6, 2), (int) substr($name, 0, 4));
    }

    /**
     * @param string $directory
     *
     * @return array
     */
    protected static function subdirectories($directory) {
        $directories = glob(rtrim($directory, DS) . DS . '*', GLOB_ONLYDIR);

        return $directories === false ? [] : $directories;
    }

    /**
     * @param string $directory
     *
     * @return void
     */
    protected static function deleteDirectory($directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    protected static function log($message) {
        if (CDaemon::isDaemon()) {
            CDaemon::log($message);
        }
        if (CCron::isCron()) {
            CCron::log($message);
        }
    }
}
