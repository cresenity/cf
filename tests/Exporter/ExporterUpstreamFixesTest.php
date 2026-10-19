<?php

use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class UjiFix_ManyRows implements CExporter_Concern_FromCollection, CExporter_Concern_WithHeadings {
    public function collection() {
        return c::collect(range(1, 2500))->map(function ($i) {
            return ['r' . $i, $i];
        });
    }

    public function headings() {
        return ['Kode', 'Nomor'];
    }
}

class UjiFix_CollectionAndView implements CExporter_Concern_FromCollection, CExporter_Concern_FromView {
    public function collection() {
        return c::collect([['a']]);
    }

    public function view() {
        return null;
    }
}

class UjiFix_Query implements CExporter_Concern_FromQuery {
    public $query;

    public function __construct($query) {
        $this->query = $query;
    }

    public function query() {
        return $this->query;
    }
}

class UjiFix_Rows implements CExporter_Concern_FromArray, CExporter_Concern_WithHeadings {
    public function getArray() {
        return [['a1', 'Apel']];
    }

    public function headings() {
        return ['Kode', 'Nama'];
    }
}

class UjiFix_Drawing implements CExporter_Concern_FromArray, CExporter_Concern_WithDrawings {
    public static $image;

    public function getArray() {
        return [['a', 'b'], ['c', 'd']];
    }

    public function drawings() {
        $drawing = new Drawing();
        $drawing->setPath(static::$image);
        $drawing->setCoordinates('C1');

        return $drawing;
    }
}

/**
 * Perbaikan ekspor dari Laravel-Excel 3.1.46-3.1.70 (#-14747).
 */
class ExporterUpstreamFixesTest extends TestCase {
    protected $files = [];

    protected function tearDown(): void {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    private function load($contents) {
        $path = tempnam(sys_get_temp_dir(), 'uji-fix') . '.xlsx';
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return IOFactory::load($path)->getActiveSheet();
    }

    public function testRowsAreAppendedInChunksWithoutLosingAny() {
        $sheet = $this->load(CExporter::raw(new UjiFix_ManyRows(), CExporter::XLSX));

        $this->assertSame(2501, $sheet->getHighestRow());
        $this->assertSame('Kode', $sheet->getCell('A1')->getValue());
        $this->assertSame('r1', $sheet->getCell('A2')->getValue());
        $this->assertSame('r1000', $sheet->getCell('A1001')->getValue());
        $this->assertSame('r1001', $sheet->getCell('A1002')->getValue());
        $this->assertSame(2500, $sheet->getCell('B2501')->getValue());
    }

    public function testACollectionExportThatIsAlsoAViewIsRejected() {
        $this->expectException(CExporter_Exception_ConcernConflictException::class);
        CExporter::raw(new UjiFix_CollectionAndView(), CExporter::XLSX);
    }

    public function testFailureIsJsonSerializable() {
        $failure = new CExporter_Validator_Failure(3, 'email', ['The email field is required.'], ['email' => null]);

        $this->assertSame(
            '{"row":3,"attribute":"email","errors":["The email field is required."],"values":{"email":null}}',
            json_encode($failure)
        );
    }

    public function testTheExportQueryIsNotModifiedByChunking() {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite tidak tersedia');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db = new CDatabase_Connection_Pdo_SqliteConnection($pdo, '', '', ['driver' => 'sqlite']);
        $db->setEventDispatcher(new CEvent_Dispatcher());
        $db->getSchemaBuilder()->create('barang', function (CDatabase_Schema_Blueprint $table) {
            $table->increments('barang_id');
            $table->string('nama');
        });
        $db->table('barang')->insert([['nama' => 'Apel'], ['nama' => 'Beras']]);
        $query = $db->table('barang')->select('nama')->orderBy('barang_id');
        $export = new UjiFix_Query($query);

        $sheet = $this->load(CExporter::raw($export, CExporter::XLSX));

        $this->assertSame([['Apel'], ['Beras']], $sheet->toArray(null, true, false));
        $this->assertNull($query->limit);
        $this->assertNull($query->offset);
        $this->assertCount(1, $query->orders);
    }

    public function testATsvFileIsWrittenWithTabs() {
        $path = 'uji-exporter/' . uniqid() . '.tsv';
        $this->assertTrue(CExporter::store(new UjiFix_Rows(), $path));
        $disk = CStorage::instance()->disk();

        try {
            $lines = $this->lines($disk->get($path));
            $this->assertSame(['Kode', 'Nama'], str_getcsv($lines[0], "\t"));
            $this->assertSame(['a1', 'Apel'], str_getcsv($lines[1], "\t"));
        } finally {
            $disk->delete($path);
        }

        //CSV sesudahnya tetap koma (delimiter tab tidak tertinggal di setting static)
        $this->assertSame(['Kode', 'Nama'], str_getcsv($this->lines(CExporter::raw(new UjiFix_Rows(), CExporter::CSV))[0]));
    }

    private function lines($contents) {
        return array_values(array_filter(explode("\n", str_replace("\r", '', ltrim($contents, "\xEF\xBB\xBF")))));
    }

    public function testDrawingsAreAddedAfterTheData() {
        if (!function_exists('imagepng')) {
            $this->markTestSkipped('gd tidak tersedia');
        }
        UjiFix_Drawing::$image = tempnam(sys_get_temp_dir(), 'uji-img') . '.png';
        $this->files[] = UjiFix_Drawing::$image;
        imagepng(imagecreatetruecolor(4, 4), UjiFix_Drawing::$image);

        $sheet = $this->load(CExporter::raw(new UjiFix_Drawing(), CExporter::XLSX));

        $this->assertCount(1, $sheet->getDrawingCollection());
        $this->assertSame('C1', $sheet->getDrawingCollection()[0]->getCoordinates());
        $this->assertSame('d', $sheet->getCell('B2')->getValue());
    }
}
