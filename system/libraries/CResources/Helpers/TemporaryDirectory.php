<?php

class CResources_Helpers_TemporaryDirectory {
    const DEFAULT_FOLDER = 'resource';

    /**
     * Temp folder handed to CTemporary (which makes it per app), unless the config names one.
     *
     * @return string
     */
    protected static function folder() {
        $folder = CF::config('resource.temporary_directory_path');
        if (strlen($folder) == 0) {
            $folder = static::DEFAULT_FOLDER;
        }

        return $folder;
    }

    /**
     * Base of the per-call temporary directories, `temp/resource/<appCode>/temp` unless the config names one.
     *
     * @return string
     */
    public static function basePath() {
        return CF::config('resource.temporary_directory_path') ?: DOCROOT . 'temp' . DS . 'resource' . DS . CF::appCode() . DS . 'temp';
    }

    public static function generateLocalFilePath($extension = null) {
        $filename = CTemporary::generateRandomFilename();
        if ($extension != null) {
            $filename .= '.' . $extension;
        }
        $localPath = CTemporary::getLocalPath(static::folder(), $filename);
        $dirPath = dirname($localPath);
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, 0777, true);
        }

        return $localPath;
    }

    public static function delete($filename) {
        $filename = basename($filename);

        return CTemporary::deleteLocal(static::folder(), $filename);
    }

    /**
     * @return CTemporary_CustomDirectory
     */
    public static function create() {
        return new CTemporary_CustomDirectory(static::getTemporaryDirectoryPath());
    }

    protected static function getTemporaryDirectoryPath() {
        return static::basePath() . DIRECTORY_SEPARATOR . cstr::random(32);
    }
}
