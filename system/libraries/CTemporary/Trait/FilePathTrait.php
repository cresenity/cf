<?php

trait CTemporary_Trait_FilePathTrait {
    /**
     * Whether the last segment of the path looks like a file name: a dot followed by an extension that
     * contains at least one letter ("laporan.pdf" yes, "v1.2" and "2026.10.03" no).
     *
     * @param string $path
     *
     * @return bool
     */
    protected function isFilePath($path) {
        $name = basename(rtrim((string) $path, '/\\'));
        $position = strrpos($name, '.');
        if ($position === false) {
            return false;
        }

        return preg_match('/[A-Za-z]/', substr($name, $position + 1)) === 1;
    }

    /**
     * The path without its file name, or the path itself when it is a directory path.
     *
     * @param string $path
     *
     * @return string
     */
    protected function removeFilenameFromPath($path) {
        if (!$this->isFilePath($path)) {
            return $path;
        }

        return substr($path, 0, strrpos($path, DIRECTORY_SEPARATOR));
    }
}
