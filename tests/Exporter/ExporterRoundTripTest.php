<?php
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UjiExport_Rows implements CExporter_Concern_FromArray, CExporter_Concern_WithHeadings, CExporter_Concern_WithTitle {
    /** @var array[] */
    protected $rows;

    public function __construct(array $rows) {
        $this->rows = $rows;
    }

    public function getArray() {
        return $this->rows;
    }

    public function headings() {
        return ['Kode', 'Nama', 'Harga'];
    }

    public function title() {
        return 'Barang';
    }
}

class UjiExport_Mapped extends UjiExport_Rows implements CExporter_Concern_WithMapping, CExporter_Concern_WithColumnFormatting, CExporter_Concern_WithStrictNullComparison {
    public function map($row) {
        return [strtoupper($row['code']), $row['name'], $row['price'] * 1000, null, 0];
    }

    public function columnFormats() {
        return ['C' => '#,##0'];
    }

    public function headings() {
        return ['Kode', 'Nama', 'Harga (Rp)', 'Kosong', 'Nol'];
    }
}

class UjiExport_Collection implements CExporter_Concern_FromCollection {
    public function collection() {
        return c::collect([['a', 1], ['b', 2]]);
    }
}

class UjiExport_Iterator implements CExporter_Concern_FromIterator {
    public function iterator() {
        return new ArrayIterator([['x', 10], ['y', 20], ['z', 30]]);
    }
}

class UjiExport_Sheets implements CExporter_Concern_WithMultipleSheets {
    public function sheets() {
        return [new UjiExport_Rows([['A', 'Apel', 1]]), new UjiExport_Collection()];
    }
}

class UjiExport_Events implements CExporter_Concern_FromArray, CExporter_Concern_WithEvents {
    /** @var string[] */
    public static $seen = [];

    public function getArray() {
        return [[1, 2]];
    }

    public function registerEvents() {
        return [
            CExporter_Event_BeforeExport::class => function ($event) {
                static::$seen[] = 'beforeExport';
            },
            CExporter_Event_BeforeWriting::class => function ($event) {
                static::$seen[] = 'beforeWriting';
            },
            CExporter_Event_BeforeSheet::class => function ($event) {
                static::$seen[] = 'beforeSheet';
            },
            CExporter_Event_AfterSheet::class => function ($event) {
                static::$seen[] = 'afterSheet';
                $event->getSheet()->getDelegate()->setCellValue('D1', 'dari-event');
            },
        ];
    }
}

class UjiExport_Csv extends UjiExport_Rows implements CExporter_Concern_WithCustomCsvSettings {
    public function getCsvSettings() {
        return ['delimiter' => ';', 'enclosure' => '"', 'use_bom' => false];
    }
}

class UjiImport_Rows implements CExporter_Concern_ToArray, CExporter_Concern_WithHeadingRow {
    /** @var array */
    public $rows = [];

    public function toArray(array $array) {
        $this->rows = $array;
    }
}

class UjiImport_Plain implements CExporter_Concern_ToCollection {
    /** @var CCollection */
    public $rows;

    public function collection(CCollection $rows) {
        $this->rows = $rows;
    }
}

/**
 * CExporter (port paket excel hulu di atas PhpSpreadsheet): ekspor dari array/koleksi/iterator
 * dengan heading, mapping, format kolom, judul sheet, banyak sheet, event, pengaturan CSV;
 * hasilnya dibaca kembali (PhpSpreadsheet / str_getcsv) dan diimpor lagi lewat CExporter_Reader.
 */
class ExporterRoundTripTest extends TestCase {
    /** @var string[] */
    protected $files = [];

    /** @var mixed */
    protected $originalTransactionHandler;

    protected function setUp(): void {
        // impor dibungkus transaksi DB default; suite framework tidak punya DB
        $this->originalTransactionHandler = CConfig::repository()->get('exporter.transactions.handler');
        CConfig::repository()->set('exporter.transactions.handler', 'null');
    }

    protected function tearDown(): void {
        CConfig::repository()->set('exporter.transactions.handler', $this->originalTransactionHandler);
        foreach ($this->files as $file) {
            @unlink($file);
        }
        UjiExport_Events::$seen = [];
    }

    /**
     * @param string $contents
     * @param string $extension
     *
     * @return string path
     */
    protected function save($contents, $extension) {
        $path = tempnam(sys_get_temp_dir(), 'uji-export') . '.' . $extension;
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    /**
     * @param string $contents
     *
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    protected function spreadsheet($contents) {
        return IOFactory::load($this->save($contents, 'xlsx'));
    }

    /**
     * @return array[]
     */
    protected function rows() {
        return [
            ['code' => 'a1', 'name' => 'Apel', 'price' => 10],
            ['code' => 'b2', 'name' => 'Beras', 'price' => 20.5],
        ];
    }

    public function testRawXlsxFromArrayWithHeadingsAndTitle() {
        $contents = CExporter::raw(new UjiExport_Rows($this->rows()), CExporter::XLSX);
        $this->assertStringStartsWith("PK\x03\x04", $contents, 'xlsx = arsip zip');
        $sheet = $this->spreadsheet($contents)->getActiveSheet();
        $this->assertSame('Barang', $sheet->getTitle());
        $this->assertSame([['Kode', 'Nama', 'Harga'], ['a1', 'Apel', 10], ['b2', 'Beras', 20.5]], $sheet->toArray());
    }

    public function testMappingColumnFormatsAndStrictNullComparison() {
        $sheet = $this->spreadsheet(CExporter::raw(new UjiExport_Mapped($this->rows()), CExporter::XLSX))->getActiveSheet();
        $this->assertSame(['A1', 'Apel', 10000, null, 0], $sheet->toArray(null, true, false)[1], 'map() menentukan kolom; null tetap kosong, 0 tetap ditulis (strict null)');
        $this->assertSame('#,##0', $sheet->getStyle('C2')->getNumberFormat()->getFormatCode());
        $this->assertSame('10,000', $sheet->getCell('C2')->getFormattedValue());
        $this->assertSame(['Kode', 'Nama', 'Harga (Rp)', 'Kosong', 'Nol'], $sheet->toArray()[0]);
    }

    public function testFromCollectionAndFromIterator() {
        $this->assertSame([['a', 1], ['b', 2]], $this->spreadsheet(CExporter::raw(new UjiExport_Collection(), CExporter::XLSX))->getActiveSheet()->toArray());
        $this->assertSame([['x', 10], ['y', 20], ['z', 30]], $this->spreadsheet(CExporter::raw(new UjiExport_Iterator(), CExporter::XLSX))->getActiveSheet()->toArray());
    }

    public function testPlainArrayAndCollectionAreWrappedByTheDetector() {
        // raw() menuntut exportable (hulu juga); store()/download() yang memanggil detektor
        $this->assertSame([[1, 2], [3, 4]], $this->spreadsheet(CExporter::raw(CExporter_ExportableDetector::toExportable([[1, 2], [3, 4]]), CExporter::XLSX))->getActiveSheet()->toArray(), 'array polos');
        $this->assertSame([['k', 'v']], $this->spreadsheet(CExporter::raw(CExporter_ExportableDetector::toExportable(c::collect([['k', 'v']])), CExporter::XLSX))->getActiveSheet()->toArray(), 'koleksi polos');
        $this->assertInstanceOf(CExporter_Exportable_Array::class, CExporter_ExportableDetector::toExportable([[1]]));
        $this->assertInstanceOf(CExporter_Exportable_Collection::class, CExporter_ExportableDetector::toExportable(c::collect([])));
        $this->assertInstanceOf(CExporter_Exportable_Iterator::class, CExporter_ExportableDetector::toExportable(new ArrayIterator([])));
        $exportable = new UjiExport_Rows([]);
        $this->assertSame($exportable, CExporter_ExportableDetector::toExportable($exportable), 'yang sudah exportable dibiarkan');
    }

    public function testMultipleSheets() {
        $spreadsheet = $this->spreadsheet(CExporter::raw(new UjiExport_Sheets(), CExporter::XLSX));
        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertSame('Barang', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame([['Kode', 'Nama', 'Harga'], ['A', 'Apel', 1]], $spreadsheet->getSheet(0)->toArray());
        $this->assertSame([['a', 1], ['b', 2]], $spreadsheet->getSheet(1)->toArray());
    }

    public function testEventsFireInOrderAndCanTouchTheSheet() {
        $sheet = $this->spreadsheet(CExporter::raw(new UjiExport_Events(), CExporter::XLSX))->getActiveSheet();
        $this->assertSame(['beforeExport', 'beforeSheet', 'afterSheet', 'beforeWriting'], UjiExport_Events::$seen);
        $this->assertSame('dari-event', $sheet->getCell('D1')->getValue());
    }

    public function testCsvExportUsesDefaultAndCustomSettings() {
        $csv = CExporter::raw(new UjiExport_Rows($this->rows()), CExporter::CSV);
        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $csv))));
        $this->assertSame(['Kode', 'Nama', 'Harga'], str_getcsv(ltrim($lines[0], "\xEF\xBB\xBF")), 'BOM bawaan dibuang untuk perbandingan');
        $this->assertSame(['a1', 'Apel', '10'], str_getcsv($lines[1]));
        $this->assertSame(['b2', 'Beras', '20.5'], str_getcsv($lines[2]));

        $custom = CExporter::raw(new UjiExport_Csv($this->rows()), CExporter::CSV);
        $this->assertStringNotContainsString("\xEF\xBB\xBF", $custom, 'use_bom=false');
        $this->assertStringContainsString('"a1";"Apel";"10"', str_replace("\r", '', $custom), 'enclosure dipakai untuk semua sel, angka pun');
    }

    public function testStoreWritesToTheDefaultDiskAndCanBeReadBack() {
        $path = 'uji-exporter/' . uniqid() . '.xlsx';
        $this->assertTrue(CExporter::store(new UjiExport_Rows($this->rows()), $path));
        $disk = CStorage::instance()->disk();
        $this->assertTrue($disk->exists($path));
        try {
            $sheet = $this->spreadsheet($disk->get($path))->getActiveSheet();
            $this->assertSame('a1', $sheet->getCell('A2')->getValue());
        } finally {
            $disk->delete($path);
        }
    }

    public function testFileTypeDetectorFollowsTheExtension() {
        $this->assertSame(CExporter::XLSX, CExporter_FileTypeDetector::detectStrict('laporan.xlsx'));
        $this->assertSame(CExporter::CSV, CExporter_FileTypeDetector::detectStrict('laporan.csv'));
        $this->assertSame(CExporter::XLS, CExporter_FileTypeDetector::detectStrict('laporan.XLS', null), 'ekstensi tidak peka huruf');
        $this->assertSame(CExporter::CSV, CExporter_FileTypeDetector::detectStrict('tanpa-ekstensi', CExporter::CSV), 'tipe eksplisit menang');
        $this->expectException(CExporter_Exception_NoTypeDetectedException::class);
        CExporter_FileTypeDetector::detectStrict('tanpa-ekstensi');
    }

    public function testGenerateExtensionAndRandomFilename() {
        $this->assertSame('xlsx', CExporter::generateExtension(CExporter::XLSX));
        $this->assertSame('csv', CExporter::generateExtension(CExporter::CSV));
        $this->assertMatchesRegularExpression('/\.xlsx$/', CExporter::randomFilename(CExporter::XLSX));
        $this->assertNotSame(CExporter::randomFilename(), CExporter::randomFilename());
    }

    public function testImportWithHeadingRowKeysRowsByHeading() {
        $path = $this->save(CExporter::raw(new UjiExport_Rows($this->rows()), CExporter::XLSX), 'xlsx');
        $import = new UjiImport_Rows();
        CExporter_Reader::instance()->read($import, $path);
        $this->assertSame([
            ['kode' => 'a1', 'nama' => 'Apel', 'harga' => 10],
            ['kode' => 'b2', 'nama' => 'Beras', 'harga' => 20.5],
        ], $import->rows, 'heading di-slug jadi kunci (formatter bawaan)');
    }

    public function testImportToCollectionWithoutHeadingRow() {
        $path = $this->save(CExporter::raw(CExporter_ExportableDetector::toExportable([[1, 'a'], [2, 'b']]), CExporter::CSV), 'csv');
        $import = new UjiImport_Plain();
        CExporter_Reader::instance()->read($import, $path);
        $this->assertInstanceOf(CCollection::class, $import->rows);
        $this->assertEquals([[1, 'a'], [2, 'b']], $import->rows->map->toArray()->all());
    }

    public function testReaderToArrayAndToCollectionShortcuts() {
        $path = $this->save(CExporter::raw(new UjiExport_Sheets(), CExporter::XLSX), 'xlsx');
        $arrays = CExporter_Reader::instance()->toArray(new UjiImport_Plain(), $path, CExporter::XLSX);
        $this->assertCount(2, $arrays, 'tanpa WithMultipleSheets setiap sheet dibaca dengan import yang sama (hulu juga)');
        $this->assertSame([['Kode', 'Nama', 'Harga'], ['A', 'Apel', 1]], $arrays[0]);
        $this->assertSame([['a', 1], ['b', 2]], $arrays[1]);
        $collections = CExporter_Reader::instance()->toCollection(new UjiImport_Plain(), $path, CExporter::XLSX);
        $this->assertInstanceOf(CCollection::class, $collections);
        $this->assertSame('Apel', $collections[0][1][1]);
    }
}
