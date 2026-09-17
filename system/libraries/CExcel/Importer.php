<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Oct 1, 2019, 3:11:09 PM
 */
class CExcel_Importer {
    /**
     * @inheritdoc
     */
    public function import($import, $filePath, $disk = null, $readerType = null) {
        $readerType = FileTypeDetector::detect($filePath, $readerType);
        $response = $this->reader->read($import, $filePath, $readerType, $disk);
        if ($response instanceof CQueue_PendingDispatch) {
            return $response;
        }

        return $this;
    }
}
