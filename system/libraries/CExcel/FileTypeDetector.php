<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 *
 * @since Oct 1, 2019, 3:14:05 PM
 *
 * @license Ittron Global Teknologi <ittron.co.id>
 */
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;

class FileTypeDetector {
    /**
     * @param             $filePath
     * @param null|string $type
     *
     * @throws NoTypeDetectedException
     *
     * @return null|string
     */
    public static function detect($filePath, $type = null) {
        if (null !== $type) {
            return $type;
        }
        if (!$filePath instanceof UploadedFile) {
            $pathInfo = pathinfo($filePath);
            $extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
        } else {
            $extension = $filePath->getClientOriginalExtension();
        }
        if (null === $type && trim($extension) === '') {
            throw new CExcel_Exception_NoTypeDetectedException();
        }
        $data = [
            'xlsx' => CExcel_Constant::XLSX,
            'xlsm' => CExcel_Constant::XLSX,
            'xltx' => CExcel_Constant::XLSX,
            'xltm' => CExcel_Constant::XLSX,
            'xls' => CExcel_Constant::XLS,
            'xlt' => CExcel_Constant::XLS,
            'ods' => CExcel_Constant::ODS,
            'ots' => CExcel_Constant::ODS,
            'slk' => CExcel_Constant::SLK,
            'xml' => CExcel_Constant::XML,
            'gnumeric' => CExcel_Constant::GNUMERIC,
            'htm' => CExcel_Constant::HTML,
            'html' => CExcel_Constant::HTML,
            'csv' => CExcel_Constant::CSV,
            'tsv' => CExcel_Constant::TSV,
            'pdf' => CExcel_Constant::DOMPDF,
        ];

        return carr::get($data, strtolower($extension));
    }

    /**
     * @param string      $filePath
     * @param null|string $type
     *
     * @throws CExcel_Exception_NoTypeDetectedException
     *
     * @return string
     */
    public static function detectStrict($filePath, $type = null) {
        $type = static::detect($filePath, $type);
        if (!$type) {
            throw new CExcel_Exception_NoTypeDetectedException();
        }

        return $type;
    }
}
