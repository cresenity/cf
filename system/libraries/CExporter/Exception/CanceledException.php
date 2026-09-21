<?php

/**
 * Thrown from inside a job's progress callback once CExporter_DownloadProgress::isCanceled() turns true, so the job can stop and return quietly.
 */
class CExporter_Exception_CanceledException extends RuntimeException implements CExporter_ExceptionInterface {
    /**
     * @param string $downloadId
     *
     * @return static
     */
    public static function forDownload($downloadId) {
        return new static('Export ' . $downloadId . ' was canceled');
    }
}
