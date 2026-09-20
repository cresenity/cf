<?php

defined('SYSPATH') or die('No direct access allowed.');

// @codingStandardsIgnoreStart
/**
 * @deprecated since 1.6 use c::response()->download()
 */
class cdownload {
    //@codingStandardsIgnoreEnd

    /**
     * Force a download of a file to the user's browser. This function is
     * binary-safe and will work with any MIME type that Kohana is aware of.
     *
     * @param string $filename a file path or file name
     * @param mixed  $data     data to be sent if the filename does not exist
     * @param string $nicename suggested filename to display in the download
     *
     * @deprecated use Response to download
     *
     * @return void
     */
    public static function force($filename = null, $data = null, $nicename = null) {
        CF::deprecated(__METHOD__, 'c::response()->download()', '1.6', null, true);
        if (empty($filename)) {
            return false;
        }

        if (is_file($filename)) {
            // Get the real path
            $filepath = str_replace('\\', '/', realpath($filename));

            // Set filesize
            $filesize = filesize($filepath);

            // Get filename
            $filename = substr(strrchr('/' . $filepath, '/'), 1);

            // Get extension
            $extension = strtolower(substr(strrchr($filepath, '.'), 1));
        } else {
            // Get filesize
            $filesize = strlen($data);

            // Make sure the filename does not have directory info
            $filename = substr(strrchr('/' . $filename, '/'), 1);

            // Get extension
            $extension = strtolower(substr(strrchr($filename, '.'), 1));
        }

        // Get the mime type of the file
        $mime = CF::config('mimes.' . $extension);

        if (empty($mime)) {
            // Set a default mime if none was found
            $mime = ['application/octet-stream'];
        }
        // Generate the server headers
        //header('Content-Type: '.$mime[0]);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . (empty($nicename) ? $filename : $nicename) . '"');
        //header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . sprintf('%d', $filesize));

        // More caching prevention
        header('Expires: 0');

        if (CF::userAgent('browser') === 'Internet Explorer') {
            // Send IE headers
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
        } else {
            // Send normal headers
            header('Pragma: no-cache');
        }
        // Clear the output buffer
        // CF::close_buffers(FALSE);

        if (isset($filepath)) {
            //echo $filepath;
            $data = file_get_contents($filepath);
            echo $data;
        } else {
            // Send the file data
            echo $data;
        }

        exit;
    }
}

// End download
