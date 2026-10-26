<?php

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;

class CExporter_Writer {
    use CExporter_Trait_DelegatedMacroableTrait;
    use CExporter_Trait_HasEventBusTrait;

    /**
     * @var CExporter_Writer
     */
    protected static $instance;

    /**
     * @var Spreadsheet
     */
    protected $spreadsheet;

    /**
     * @var object
     */
    protected $exportable;

    /**
     * @var CExporter_File_TemporaryFileFactory
     */
    protected $temporaryFileFactory;

    private function __construct() {
        $this->temporaryFileFactory = CExporter_File_TemporaryFileFactory::instance();

        $this->setDefaultValueBinder();
    }

    public static function instance() {
        if (static::$instance == null) {
            static::$instance = new CExporter_Writer();
        }

        return static::$instance;
    }

    /**
     * @param object      $export
     * @param string      $writerType
     * @param null|string $fileName   target file name (a .tsv gets a tab delimiter)
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     *
     * @return TemporaryFile
     */
    public function export($export, $writerType, $fileName = null) {
        $this->open($export);

        $sheetExports = [$export];
        if ($export instanceof CExporter_Concern_WithMultipleSheets) {
            $sheetExports = $export->sheets();
        }

        foreach ($sheetExports as $sheetExport) {
            $this->addNewSheet()->export($sheetExport);
        }

        return $this->write($export, $this->temporaryFileFactory->makeLocal(), $writerType, $fileName);
    }

    /**
     * @param object $export
     *
     * @return $this
     */
    public function open($export) {
        $this->exportable = $export;

        if ($export instanceof CExporter_Concern_WithEvents) {
            $this->registerListeners($export->registerEvents());
        }

        $this->exportable = $export;
        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->disconnectWorksheets();

        if ($export instanceof CExporter_Concern_WithCustomValueBinder) {
            Cell::setValueBinder($export);
        }

        $this->handleDocumentProperties($export);

        if ($export instanceof CExporter_Concern_WithBackgroundColor) {
            $defaultStyle = $this->spreadsheet->getDefaultStyle();
            $backgroundColor = $export->backgroundColor();

            if (is_string($backgroundColor)) {
                $defaultStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($backgroundColor);
            }

            if (is_array($backgroundColor)) {
                $defaultStyle->applyFromArray(['fill' => $backgroundColor]);
            }

            if ($backgroundColor instanceof Color) {
                $defaultStyle->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor($backgroundColor);
            }
        }

        if ($export instanceof CExporter_Concern_WithDefaultStyles) {
            $defaultStyle = $this->spreadsheet->getDefaultStyle();
            $styles = $export->defaultStyles($defaultStyle);

            if (is_array($styles)) {
                $defaultStyle->applyFromArray($styles);
            }
        }
        $this->raise(new CExporter_Event_BeforeExport($this, $this->exportable));

        return $this;
    }

    /**
     * @param string $writerType
     *
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     *
     * @return CExporter_Writer
     */
    public function reopen(CExporter_File_TemporaryFile $tempFile, $writerType) {
        $reader = IOFactory::createReader($writerType);
        $this->spreadsheet = $reader->load($tempFile->sync()->getLocalPath());

        return $this;
    }

    /**
     * @param object        $export
     * @param TemporaryFile $temporaryFile
     * @param string        $writerType
     * @param null|string   $fileName      target file name (a .tsv gets a tab delimiter)
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     *
     * @return CExporter_File_TemporaryFile
     */
    public function write($export, CExporter_File_TemporaryFile $temporaryFile, $writerType, $fileName = null) {
        $this->exportable = $export;

        $this->spreadsheet->setActiveSheetIndex(0);

        $this->raise(new CExporter_Event_BeforeWriting($this, $this->exportable));

        $writer = CExporter_WriterFactory::make(
            $writerType,
            $this->spreadsheet,
            $export,
            $fileName
        );

        $writer->save(
            $path = $temporaryFile->getLocalPath()
        );

        if ($temporaryFile instanceof CExporter_File_RemoteTemporaryFile) {
            $temporaryFile->updateRemote();
            $temporaryFile->deleteLocalCopy();
        }

        $this->clearListeners();
        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);

        return $temporaryFile;
    }

    /**
     * @param null|int $sheetIndex
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     *
     * @return CExporter_Sheet
     */
    public function addNewSheet($sheetIndex = null) {
        return new CExporter_Sheet($this->spreadsheet->createSheet($sheetIndex));
    }

    /**
     * @return Spreadsheet
     */
    public function getDelegate() {
        return $this->spreadsheet;
    }

    /**
     * @return $this
     */
    public function setDefaultValueBinder() {
        Cell::setValueBinder(CExporter::defaultValueBinder());

        return $this;
    }

    /**
     * @param int $sheetIndex
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     *
     * @return CExporter_Sheet
     */
    public function getSheetByIndex($sheetIndex) {
        return new CExporter_Sheet($this->getDelegate()->getSheet($sheetIndex));
    }

    /**
     * @param string $concern
     *
     * @return bool
     */
    public function hasConcern($concern) {
        return $this->exportable instanceof $concern;
    }

    /**
     * @param object $export
     */
    protected function handleDocumentProperties($export) {
        $properties = CExporter::config()->get('exports.properties', []);

        if ($export instanceof CExporter_Concern_WithProperties) {
            $properties = array_merge($properties, $export->properties());
        }

        if ($export instanceof CExporter_Concern_WithTitle) {
            $properties = array_merge($properties, ['title' => $export->title()]);
        }

        $props = $this->spreadsheet->getProperties();

        foreach (array_filter($properties) as $property => $value) {
            switch ($property) {
                case 'title':
                    $props->setTitle($value);

                    break;
                case 'description':
                    $props->setDescription($value);

                    break;
                case 'creator':
                    $props->setCreator($value);

                    break;
                case 'lastModifiedBy':
                    $props->setLastModifiedBy($value);

                    break;
                case 'subject':
                    $props->setSubject($value);

                    break;
                case 'keywords':
                    $props->setKeywords($value);

                    break;
                case 'category':
                    $props->setCategory($value);

                    break;
                case 'manager':
                    $props->setManager($value);

                    break;
                case 'company':
                    $props->setCompany($value);

                    break;
            }
        }
    }
}
