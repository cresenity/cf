<?php

/**
 * File.
 *
 * A convenience class for temporary files.
 */
class CTemporary_LocalFile {
    /**
     * @var bool whether to delete the tmp file when it's no longer referenced
     *           or when the request ends.  Default is `true`.
     */
    protected $delete = true;

    /**
     * @var string the name of this file
     */
    protected $fileName;

    /**
     * Constructor.
     *
     * @param mixed      $content
     * @param null|mixed $folder
     * @param null|mixed $suffix
     * @param mixed      $delete
     */
    public function __construct($content, $folder = null, $suffix = null, $delete = true) {
        $this->delete = $delete;

        $filename = date('Ymd') . cutils::randmd5();
        if ($suffix !== null) {
            $filename .= $suffix;
        }

        if ($folder == null) {
            $folder = 'common';
        }

        $this->fileName = CTemporary::local()->put($content, $folder, $filename);
    }

    /**
     * Delete tmp file on shutdown if `$delete` is `true`.
     */
    public function __destruct() {
        if ($this->delete) {
            CTemporary::local()->delete($this->fileName);
        }
    }

    /**
     * Send tmp file to client, either inline or as download.
     *
     * @param null|string $filename    the filename to send. If empty, the file is
     *                                 streamed inline.
     * @param string      $contentType the Content-Type header
     * @param bool        $inline      whether to force inline display of the file, even if
     *                                 filename is present
     */
    public function send($filename = null, $contentType = '', $inline = false) {
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: ' . $contentType);
        header('Content-Transfer-Encoding: binary');

        //#11 Undefined index: HTTP_USER_AGENT
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        // #84: Content-Length leads to "network connection was lost" on iOS
        $isIOS = preg_match('/i(phone|pad|pod)/i', $userAgent);
        if (!$isIOS) {
            header('Content-Length: ' . filesize($this->fileName));
        }

        if ($filename !== null || $inline) {
            $disposition = $inline ? 'inline' : 'attachment';
            $encodedFilename = rawurlencode($filename);
            header(
                "Content-Disposition: $disposition; "
                    . "filename=\"$filename\"; "
                    . "filename*=UTF-8''$encodedFilename"
            );
        }

        readfile($this->getFileName());
    }

    /**
     * @return string the full file name
     */
    public function getFileName() {
        return CTemporary::local()->disk()->path($this->fileName);
    }

    /**
     * @return string the full file name
     */
    #[\ReturnTypeWillChange]
    public function __toString() {
        return $this->getFileName();
    }

    public function delete() {
        $filename = $this->getFileName();
        if (file_exists($filename)) {
            return @unlink($filename);
        }

        return false;
    }
}
