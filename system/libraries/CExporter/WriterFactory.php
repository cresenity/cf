<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

class CExporter_WriterFactory {
    use CExporter_Trait_MapsCsvSettingsTrait;

    /**
     * @param string      $writerType
     * @param object      $export
     * @param null|string $filePath   target file name, a .tsv gets a tab delimiter
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     *
     * @return IWriter
     */
    public static function make($writerType, Spreadsheet $spreadsheet, $export, $filePath = null) {
        $writer = IOFactory::createWriter($spreadsheet, $writerType);

        if (static::includesCharts($export)) {
            $writer->setIncludeCharts(true);
        }

        if ($writer instanceof Html && $export instanceof CExporter_Concern_WithMultipleSheets) {
            $writer->writeAllSheets();
        }

        if ($writer instanceof Csv) {
            static::applyCsvSettings(CF::config('exporter.exports.csv', []));

            if ($export instanceof CExporter_Concern_WithCustomCsvSettings) {
                static::applyCsvSettings($export->getCsvSettings());
            }

            // CExporter::TSV is the Csv writer; the delimiter comes from the file name (upstream 3.1.68).
            // Set on the writer only, the static settings outlive this export (queue worker)
            $isTsv = $filePath && static::isTsvFile($filePath) && !($export instanceof CExporter_Concern_WithCustomCsvSettings);
            $writer->setDelimiter($isTsv ? "\t" : static::$delimiter);
            $writer->setEnclosure(static::$enclosure);
            $writer->setLineEnding(static::$lineEnding);
            $writer->setUseBOM(static::$useBom);
            $writer->setIncludeSeparatorLine(static::$includeSeparatorLine);
            $writer->setExcelCompatibility(static::$excelCompatibility);
        }

        // Calculation settings
        $writer->setPreCalculateFormulas(
            $export instanceof CExporter_Concern_WithPreCalculateFormulas ? true : CExporter::config()->get('exports.pre_calculate_formulas', false)
        );

        return $writer;
    }

    /**
     * @return bool
     */
    private static function includesCharts($export) {
        if ($export instanceof CExporter_Concern_WithCharts) {
            return true;
        }

        if ($export instanceof CExporter_Concern_WithMultipleSheets) {
            foreach ($export->sheets() as $sheet) {
                if ($sheet instanceof CExporter_Concern_WithCharts) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $filePath
     *
     * @return bool
     */
    private static function isTsvFile($filePath) {
        return strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'tsv';
    }
}
