<?php


class CBackup_File {

    /** @var array */
    protected static $allowedMimeTypes = [
        'application/zip',
        'application/x-zip',
        'application/x-gzip',
    ];

    public function isZipFile($disk, $path) {
        if ($this->hasZipExtension($path)) {
            return true;
        }
        return $this->hasAllowedMimeType($disk, $path);
    }

    protected function hasZipExtension($path) {
        return pathinfo($path, PATHINFO_EXTENSION) === 'zip';
    }

    protected function hasAllowedMimeType($disk, $path) {
        return in_array($this->mimeType($disk, $path), self::$allowedMimeTypes);
    }

    protected function mimeType($disk, $path) {
        try {
            if ($disk && method_exists($disk, 'mimeType')) {
                return $disk->mimeType($path) ?: false;
            }
        } catch (Exception $exception) {
            // Some drivers throw exceptions when checking mime types, we'll
            // just fallback to `false`.
        }
        return false;
    }

}
