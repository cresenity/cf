<?php

defined('SYSPATH') or die('No direct access allowed.');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CExcel {
    use CTrait_Compat_Excel;
    private $phpexcel;

    /**
     * Purpose: Creates the spreadsheet with given or default settings
     * Input: array $headers with optional parameters: title, subject, description, author
     * Returns: void.
     *
     * @param mixed $headers
     */
    public function __construct($headers = []) {
        $headers = array_merge([
            'title' => 'New Spreadsheet',
            'subject' => 'New Spreadsheet',
            'description' => 'New Spreadsheet',
            'author' => 'Cresenity',
        ], $headers);

        $this->phpexcel = new Spreadsheet();
        // Set properties
        $this->phpexcel->getProperties()
            ->setCreator($headers['author'])
            ->setTitle($headers['title'])
            ->setSubject($headers['subject'])
            ->setDescription($headers['description']);
        //->setActiveSheetIndex(0);
        //$this->phpexcel->getActiveSheet()->setTitle('Minimalistic demo');
    }

    public static function factory($headers = []) {
        $s = new CExcel($headers);

        return $s;
    }

    public static function num2alpha($n) {
        for ($r = ''; $n >= 0; $n = intval($n / 26) - 1) {
            $r = chr($n % 26 + 0x41) . $r;
        }

        return $r;
    }

    public function garbageCollect() {
        $this->phpexcel->garbageCollect();

        return $this;
    }

    public function setCreator($creator) {
        $this->phpexcel->getProperties()->setCreator($creator);

        return $this;
    }

    public function setTitle($title) {
        $this->phpexcel->getProperties()->setTitle($title);

        return $this;
    }

    public function setSubject($subject) {
        $this->phpexcel->getProperties()->setSubject($subject);

        return $this;
    }

    public function setDescription($description) {
        $this->phpexcel->getProperties()->setDescription($description);

        return $this;
    }

    public function phpexcel() {
        return $this->phpexcel;
    }

    public function getActiveSheet() {
        return $this->phpexcel->getActiveSheet();
    }

    public function setActiveSheetName($name) {
        $this->phpexcel->getActiveSheet()->setTitle($name);

        return $this;
    }

    public function getHighestRow() {
        return $this->phpexcel->getActiveSheet()->getHighestRow();
    }

    public function getActiveSheetName() {
        return $this->phpexcel->getActiveSheet()->getTitle();
    }

    public function setAutoWidth($colStart = '', $colEnd = '') {
        $sheet = $this->phpexcel->getActiveSheet();
        if (empty($colEnd)) {//not defined the last column, set it the max one
            $colEnd = $sheet->getColumnDimension($sheet->getHighestColumn())->getColumnIndex();
        }
        if ($colStart == '') {
            $colStart = 'A';
        }
        if (is_numeric($colStart)) {
            $colStart = $this->num2alpha($colStart);
        }
        $colEnd++;
        for ($i = $colStart; $i !== $colEnd; $i++) {
            $sheet->getColumnDimension($i)->setAutoSize(true);
        }
        $sheet->calculateColumnWidths();
    }

    public function setAlignByIndex($col, $row, $align) {
        $sheet = $this->phpexcel->getActiveSheet();
        if (is_numeric($col)) {
            $col = $this->num2alpha($col);
        }

        if ($align == 'left') {
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        }
        if ($align == 'right') {
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        }
        if ($align == 'center') {
            $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }
    }

    public function mergeCell($col1, $row1, $col2, $row2) {
        $sheet = $this->phpexcel->getActiveSheet();
        if (is_numeric($col1)) {
            $col1 = $this->num2alpha($col1);
        }
        if (is_numeric($col2)) {
            $col2 = $this->num2alpha($col2);
        }
        $sheet->mergeCells($col1 . $row1 . ':' . $col2 . $row2);
    }

    public function setHeaderStyle($row = null, $style = [], $colStart = '', $colEnd = '') {
        if ($row == null) {
            $row = '1';
        }
        $sheet = $this->phpexcel->getActiveSheet();
        if (empty($style)) {
            $style = [
                'fill' => [
                    'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['argb' => 'FF333333'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
            ];
        }
        if (empty($colEnd)) {//not defined the last column, set it the max one
            $colEnd = $sheet->getColumnDimension($sheet->getHighestColumn())->getColumnIndex();
        }
        if ($colStart == '') {
            $colStart = 'A';
        }
        if (is_numeric($colStart)) {
            $colStart = $this->num2alpha($colStart);
        }
        if (is_numeric($colEnd)) {
            $colEnd = $this->num2alpha($colEnd);
        }

        $sheet->getStyle($colStart . $row . ':' . $colEnd . $row)->applyFromArray($style);
    }

    public function setRowStyle($row, $style = [], $colStart = '', $colEnd = '') {
        $sheet = $this->phpexcel->getActiveSheet();
        if (empty($style)) {
            $style = [
                'fill' => [
                    'fillType' => PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['argb' => 'FF333333'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
            ];
        }
        if (empty($colEnd)) {//not defined the last column, set it the max one
            $colEnd = $sheet->getColumnDimension($sheet->getHighestColumn())->getColumnIndex();
        }
        if ($colStart == '') {
            $colStart = 'A';
        }
        if (is_numeric($colStart)) {
            $colStart = $this->num2alpha($colStart);
        }
        if (is_numeric($colEnd)) {
            $colEnd = $this->num2alpha($colEnd);
        }

        $sheet->getStyle($colStart . $row . ':' . $colEnd . $row)->applyFromArray($style);
    }

    public function addSheet() {
        return $this->phpexcel->createSheet();
    }

    /**
     * Purpose Writes cells to the spreadsheet
     * Input: array of array( [row] => array([col]=>[value]) ) ie $arr[row][col] => value
     * Returns: void.
     *
     * @param mixed $cell
     * @param mixed $value
     */
    public function writeCell($cell, $value) {
        $this->phpexcel->getActiveSheet()->setCellValue($cell, $value);

        return $this;
    }

    public function writeByIndex($column, $row, $value) {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        $this->phpexcel->getActiveSheet()->setCellValueByColumnAndRow($column, $row, $value);

        return $this;
    }

    public function readCell($cell) {
        return $this->phpexcel->getActiveSheet()->getCell($cell)->getCalculatedValue();
    }

    public function readByIndex($column, $row) {
        return $this->phpexcel->getActiveSheet()->getCellByColumnAndRow($column, $row)->getCalculatedValue();
    }

    public function setListValidationByIndex($col, $row, $source) {
        $cell = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
        $this->setListValidation($cell, $source);
    }

    public function setColumnWidth($index, $width) {
        $sheet = $this->phpexcel->getActiveSheet();
        $col = $sheet->getColumnDimension(PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index));
        $col->setWidth($width);
    }

    public function setListValidation($cell, $source) {
        $objValidation = $this->phpexcel->getActiveSheet()->getCell($cell)->getDataValidation();
        $objValidation->setType(PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $objValidation->setErrorStyle(PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
        $objValidation->setAllowBlank(false);
        $objValidation->setShowInputMessage(true);
        $objValidation->setShowErrorMessage(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setErrorTitle('Input error');
        $objValidation->setError('Value is not in list.');
        $objValidation->setPromptTitle('Pick from list');
        $objValidation->setPrompt('Please pick a value from the drop-down list.');
        $objValidation->setFormula1($source);
        $objValidation->setFormula2($source);
    }

    public function setData(array $data, $multiSheet = false) {

        //Single sheet ones can just dump everything to the current sheet
        if (!$multiSheet) {
            $sheet = $this->phpexcel->getActiveSheet();
            $this->setSheetData($data, $sheet);
        } else {
            //Have to do a little more work with multi-sheet
            foreach ($data as $sheetName => $sheetData) {
                $Sheet = $this->phpexcel->createSheet();
                $Sheet->setTitle($sheetName);
                $this->setSheetData($sheetData, $Sheet);
            }
            //Now remove the auto-created blank sheet at start of XLS
            $this->phpexcel->removeSheetByIndex(0);
        }

        /*
          array(
          1 => array('A1', 'B1', 'C1', 'D1', 'E1')
          2 => array('A2', 'B2', 'C2', 'D2', 'E2')
          3 => array('A3', 'B3', 'C3', 'D3', 'E3')
          );
         */
    }

    public function setSheetData(array $data, PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $Sheet) {
        foreach ($data as $row => $columns) {
            foreach ($columns as $column => $value) {
                $Sheet->setCellValueByColumnAndRow($column, $row, $value);
            }
        }
    }

    public function save($name) {
        $Writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->phpexcel, 'Xls');
        // If you want to output e.g. a PDF file, simply do:
        //$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'PDF');
        $Writer->save($name);

        return $name;
    }

    public function savepdf($name) {
        $writer = PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->phpexcel, 'PDF');
        // If you want to output e.g. a PDF file, simply do:
        //$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'PDF');
        $writer->save($name);

        return $name;
    }

    public function load($name) {
        $this->phpexcel = PhpOffice\PhpSpreadsheet\IOFactory::load($name);

        return $this;
    }

    public static function columnIndex($column_char) {
        $colIndex = PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column_char);
    }
}
