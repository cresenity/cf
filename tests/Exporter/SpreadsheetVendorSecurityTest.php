<?php
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Vendor spreadsheet setelah dinaikkan: XXE/ENTITY di XLSX ditolak, path phar:// ditolak,
 * dependensi barunya (Composer\Pcre, Complex, Matrix, HTMLPurifier >= 4.15) ada dan berfungsi.
 */
class SpreadsheetVendorSecurityTest extends TestCase {
    /** @var string[] */
    protected $files = [];

    protected function tearDown(): void {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    protected function xlsx(array $rows) {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'cfx') . '.xlsx';
        (new XlsxWriter($spreadsheet))->save($path);
        $this->files[] = $path;

        return $path;
    }

    public function testXlsxWithEntityDeclarationIsRejected() {
        $path = $this->xlsx([['a', 1]]);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($sheet);
        $poisoned = preg_replace('/^<\?xml[^>]*\?>/', '$0<!DOCTYPE x [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>', $sheet, 1);
        $zip->addFromString('xl/worksheets/sheet1.xml', $poisoned);
        $zip->close();

        $this->expectException(PhpOffice\PhpSpreadsheet\Reader\Exception::class);
        $this->expectExceptionMessage('Detected use of ENTITY');
        IOFactory::load($path);
    }

    public function testPharPathIsRejected() {
        $this->expectException(PhpOffice\PhpSpreadsheet\Exception::class);
        $this->expectExceptionMessage('Disallowed stream wrapper');
        IOFactory::load('phar://' . $this->xlsx([['a', 1]]) . '/x.xlsx');
    }

    public function testCleanXlsxStillLoadsWithRawTypes() {
        $sheet = IOFactory::load($this->xlsx([['Kode', 'Harga'], ['a1', 10.5]]))->getActiveSheet();
        $this->assertSame([['Kode', 'Harga'], ['a1', 10.5]], $sheet->toArray(null, true, false));
    }

    public function testNewDependenciesAreVendored() {
        $this->assertTrue(class_exists(Composer\Pcre\Preg::class), 'composer/pcre dipakai Shared\\File (guard phar://)');
        $this->assertSame(1, Composer\Pcre\Preg::match('/b/', 'abc'));
        $this->assertTrue(class_exists(Matrix\Matrix::class) && class_exists(Complex\Complex::class), 'markbaker/matrix + complex untuk fungsi MDETERM/IMSUM dkk');
        $this->assertTrue(version_compare(HTMLPurifier::VERSION, '4.15.0', '>='), 'HTMLPurifier ' . HTMLPurifier::VERSION);
        $this->assertTrue(class_exists(ZipStream\Option\Archive::class), 'ZipStream 2.x → PhpSpreadsheet memakai adaptor ZipStream2');
    }

    public function testMatrixFormulasWorkNow() {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([[1, 2], [3, 4]]);
        $sheet->setCellValue('D1', '=MDETERM(A1:B2)');
        $sheet->setCellValue('D2', '=IMSUM("1+2i","3-i")');
        $this->assertEquals(-2, $sheet->getCell('D1')->getCalculatedValue());
        $this->assertSame('4+i', $sheet->getCell('D2')->getCalculatedValue());
    }

    public function testHtmlPurifierStillCleansInput() {
        require_once DOCROOT . 'system/vendor/HTMLPurifier.auto.php'; // seperti CHTTP_Middleware_CleanInput
        $purifier = new HTMLPurifier(HTMLPurifier_Config::createDefault());
        $this->assertSame('<b>ok</b>', $purifier->purify('<b onclick="x()">ok</b><script>alert(1)</script>'));
    }
}
