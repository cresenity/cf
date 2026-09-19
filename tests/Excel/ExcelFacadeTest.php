<?php
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * CExcel (pembungkus PhpSpreadsheet lama), CExcel_FileTypeDetector, CExcel_DefaultValueBinder.
 */
class ExcelFacadeTest extends TestCase {
    /** @var string[] */
    protected $files = [];

    protected function tearDown(): void {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testNum2AlphaAndColumnIndexRoundTrip() {
        $this->assertSame('A', CExcel::num2alpha(0));
        $this->assertSame('Z', CExcel::num2alpha(25));
        $this->assertSame('AA', CExcel::num2alpha(26));
        $this->assertSame('AZ', CExcel::num2alpha(51));
        $this->assertSame(1, CExcel::columnIndex('A'));
        $this->assertSame(27, CExcel::columnIndex('AA'));
        foreach ([0, 25, 26, 700] as $n) {
            $this->assertSame($n + 1, CExcel::columnIndex(CExcel::num2alpha($n)), 'num2alpha 0-based, columnIndex 1-based');
        }
    }

    public function testFactorySetsDocumentProperties() {
        $excel = CExcel::factory(['title' => 'Laporan', 'author' => 'Uji']);
        $props = $excel->phpexcel()->getProperties();
        $this->assertSame('Laporan', $props->getTitle());
        $this->assertSame('Uji', $props->getCreator());
        $this->assertSame('New Spreadsheet', $props->getSubject(), 'default tetap untuk yang tidak diberi');
        $excel->setSubject('Subjek')->setDescription('Deskripsi');
        $this->assertSame('Subjek', $props->getSubject());
        $this->assertSame('Deskripsi', $props->getDescription());
    }

    public function testWriteReadAndSetDataSingleSheet() {
        $excel = new CExcel();
        $excel->writeCell('A1', 'judul');
        $excel->writeByIndex(2, 1, 42);
        $this->assertSame('judul', $excel->readCell('A1'));
        $this->assertSame(42, $excel->readByIndex(2, 1));
        $excel->setData([
            2 => [1 => 'a', 2 => 'b'],
            3 => [1 => 'c', 2 => 'd'],
        ]);
        $this->assertSame('d', $excel->readCell('B3'));
        $this->assertSame(3, $excel->getHighestRow());
        $excel->setActiveSheetName('Data');
        $this->assertSame('Data', $excel->getActiveSheetName());
    }

    public function testSetDataMultiSheetDropsTheBlankFirstSheet() {
        $excel = new CExcel();
        $excel->setData([
            'Satu' => [1 => [1 => 'x']],
            'Dua' => [1 => [1 => 'y']],
        ], true);
        $this->assertSame(['Satu', 'Dua'], $excel->phpexcel()->getSheetNames());
        $this->assertSame('y', $excel->phpexcel()->getSheetByName('Dua')->getCell('A1')->getValue());
    }

    public function testSaveWritesXlsAndLoadReadsItBack() {
        $path = tempnam(sys_get_temp_dir(), 'uji-excel') . '.xls';
        $this->files[] = $path;
        $excel = new CExcel();
        $excel->writeCell('A1', 'tersimpan');
        $excel->mergeCell(1, 2, 3, 2);
        $excel->mergeCell('A', 4, 'B', 4);
        $this->assertSame($path, $excel->save($path));
        $this->assertSame('Xls', IOFactory::identify($path), 'save() memakai format XLS lama (biner), bukan xlsx');

        $loaded = (new CExcel())->load($path);
        $this->assertSame('tersimpan', $loaded->readCell('A1'));
        $merged = array_values($loaded->getActiveSheet()->getMergeCells());
        $this->assertContains('B2:D2', $merged, 'kolom numerik mergeCell 0-based lewat num2alpha (1 = B)');
        $this->assertContains('A4:B4', $merged);
    }

    public function testFileTypeDetectorMapsExtensions() {
        $this->assertSame(CExcel_Constant::XLSX, CExcel_FileTypeDetector::detect('/tmp/a.XLSX'));
        $this->assertSame(CExcel_Constant::XLS, CExcel_FileTypeDetector::detect('/tmp/a.xls'));
        $this->assertSame(CExcel_Constant::CSV, CExcel_FileTypeDetector::detect('/tmp/a.csv'));
        $this->assertSame(CExcel_Constant::ODS, CExcel_FileTypeDetector::detect('/tmp/a.ods'));
        $this->assertSame('Custom', CExcel_FileTypeDetector::detect('/tmp/a.bin', 'Custom'), 'tipe eksplisit menang');
        $this->assertNull(CExcel_FileTypeDetector::detect('/tmp/a.bin'), 'ekstensi tak dikenal → null (detect longgar)');
    }

    public function testFileTypeDetectorStrictThrows() {
        $this->assertSame(CExcel_Constant::XLSX, CExcel_FileTypeDetector::detectStrict('/tmp/a.xlsx'));
        try {
            CExcel_FileTypeDetector::detect('/tmp/tanpa-ekstensi');
            $this->fail('tanpa ekstensi harus melempar');
        } catch (CExcel_Exception_NoTypeDetectedException $e) {
            $this->assertInstanceOf(CExcel_ExceptionInterface::class, $e);
        }
        $this->expectException(CExcel_Exception_NoTypeDetectedException::class);
        CExcel_FileTypeDetector::detectStrict('/tmp/a.bin');
    }

    public function testDefaultValueBinderJsonEncodesArrays() {
        $sheet = (new \PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet();
        $binder = new CExcel_DefaultValueBinder();
        $this->assertTrue($binder->bindValue($sheet->getCell('A1'), ['a' => 1]));
        $this->assertSame('{"a":1}', $sheet->getCell('A1')->getValue());
        $binder->bindValue($sheet->getCell('A2'), '12');
        $this->assertSame(12, $sheet->getCell('A2')->getValue(), 'string numerik tetap dikonversi seperti binder induk');
    }
}
