<?php

/**
 * @package Cresenity
 */
require_once dirname(__FILE__) . '' . DIRECTORY_SEPARATOR . 'Lib' . DIRECTORY_SEPARATOR . 'pclzip' . DIRECTORY_SEPARATOR . 'pclzip.lib.php';

class CZip {
    private $pclzip = null;

    private $filename;

    public function __construct($filename) {
        $this->pclzip = new PclZip($filename);
        $this->filename = $filename;
    }

    public static function factory($file) {
        return new CZip($file);
    }

    public function zip($dest, $zip_path = null) {
        $cont = true;
        $result = 1;
        if (!file_exists($dest)) {
            $cont = false;
            //$result = 'file / directory does not exist';
            throw new Exception('file / directory does not exist');
        }

        if ($cont) {
            $pclzip = $this->pclzip;
            $res = $pclzip->create($dest, PCLZIP_OPT_REMOVE_PATH, $dest, PCLZIP_OPT_ADD_PATH, $zip_path);
            if ($res == 0) {
                //die("Error : ".$pclzip->errorInfo(true));
                //$result = $pclzip->errorInfo(true);
                throw new Exception($pclzip->errorInfo(true));
            }
        }
        return $result;
    }

    public function extract($to) {
        $archive_files = $this->pclzip->extract(PCLZIP_OPT_EXTRACT_AS_STRING);

        // Is the archive valid?
        if (!is_array($archive_files)) {
            throw new Exception('Incompatible Archive.');
        }

        if (0 == count($archive_files)) {
            throw new Exception('Empty archive.');
        }

        $needed_dirs = [];
        // Determine any children directories needed (From within the archive)
        foreach ($archive_files as $file) {
            if ('__MACOSX/' === substr($file['filename'], 0, 9)) { // Skip the OS X-created __MACOSX directory
                continue;
            }

            $needed_dirs[] = $to . c::untrailingslashit($file['folder'] ? $file['filename'] : dirname($file['filename']));
        }

        $needed_dirs = array_unique($needed_dirs);
        foreach ($needed_dirs as $dir) {
            // Check the parent folders of the folders all exist within the creation array.
            if (c::untrailingslashit($to) == $dir) { // Skip over the working directory, We know this exists (or will exist)
                continue;
            }
            if (strpos($dir, $to) === false) { // If the directory is not within the working directory, Skip it
                continue;
            }

            $parent_folder = dirname($dir);
            while (!empty($parent_folder) && c::untrailingslashit($to) != $parent_folder && !in_array($parent_folder, $needed_dirs)) {
                $needed_dirs[] = $parent_folder;
                $parent_folder = dirname($parent_folder);
            }
        }
        asort($needed_dirs);

        // Create those directories if need be:
        foreach ($needed_dirs as $_dir) {
            if (!is_dir($_dir)) {
                if (!mkdir($_dir) && !is_dir($_dir)) { // Only check to see if the dir exists upon creation failure. Less I/O this way.
                    throw new Exception('Could not create directory: ' . $_dir);
                }
            }
        }
        unset($needed_dirs);

        // Extract the files from the zip
        foreach ($archive_files as $file) {
            if ($file['folder']) {
                continue;
            }

            if ('__MACOSX/' === substr($file['filename'], 0, 9)) { // Don't extract the OS X-created __MACOSX directory files
                continue;
            }

            file_put_contents($to . $file['filename'], $file['content']);
            //if ( ! file_put_contents( $to . $file['filename'], $file['content']) )
            //throw new Exception('Could not copy file: '. $to . $file['filename']);
        }
        return true;
    }
}
