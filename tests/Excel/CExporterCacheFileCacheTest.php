<?php
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * CExporter_Cache_FileCache - cache sel PhpSpreadsheet berbasis berkas disk, dipakai lewat
 * CExporter::runWithDiskCellCache() untuk export baris sangat banyak yang OOM dengan cache
 * memori bawaan (default PhpSpreadsheet, SimpleCache1/SimpleCache3 - cuma array di memori).
 */
class CExporterCacheFileCacheTest extends TestCase {
    /** @var string[] */
    protected $files = [];

    protected function tearDown(): void {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testPsr16ContractBasics() {
        $cache = new CExporter_Cache_FileCache();

        $this->assertFalse($cache->has('a'));
        $this->assertSame('default', $cache->get('a', 'default'));

        $cache->set('a', 'nilai string');
        $this->assertTrue($cache->has('a'));
        $this->assertSame('nilai string', $cache->get('a'));

        // overwrite kunci yang sama - get() harus ambil nilai TERBARU
        $cache->set('a', 'nilai kedua');
        $this->assertSame('nilai kedua', $cache->get('a'));

        $cache->delete('a');
        $this->assertFalse($cache->has('a'));

        $cache->close();
    }

    public function testRoundTripsComplexValues() {
        $cache = new CExporter_Cache_FileCache();

        $cache->set('array', ['x' => 1, 'y' => [2, 3], 'z' => null]);
        $this->assertSame(['x' => 1, 'y' => [2, 3], 'z' => null], $cache->get('array'));

        $obj = new stdClass();
        $obj->foo = 'bar';
        $obj->num = 42.5;
        $cache->set('object', $obj);
        $gotObj = $cache->get('object');
        $this->assertInstanceOf(stdClass::class, $gotObj);
        $this->assertSame('bar', $gotObj->foo);
        $this->assertSame(42.5, $gotObj->num);

        $cache->close();
    }

    public function testMultipleOperations() {
        $cache = new CExporter_Cache_FileCache();

        $cache->setMultiple(['p' => 1, 'q' => 2]);
        $this->assertSame(1, $cache->get('p'));
        $this->assertSame(2, $cache->get('q'));

        $results = $cache->getMultiple(['p', 'q', 'tidak-ada'], 'default-multi');
        $this->assertSame(['p' => 1, 'q' => 2, 'tidak-ada' => 'default-multi'], $results);

        $cache->deleteMultiple(['p', 'q']);
        $this->assertFalse($cache->has('p'));
        $this->assertFalse($cache->has('q'));

        $cache->close();
    }

    public function testClearEmptiesAllKeys() {
        $cache = new CExporter_Cache_FileCache();
        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->clear();
        $this->assertFalse($cache->has('a'));
        $this->assertFalse($cache->has('b'));
        $cache->close();
    }

    public function testCloseDeletesTemporaryFile() {
        $cache = new CExporter_Cache_FileCache();
        $prop = (new ReflectionClass($cache))->getProperty('filePath');
        $prop->setAccessible(true);
        $path = $prop->getValue($cache);

        $this->assertFileExists($path);
        $cache->close();
        $this->assertFileDoesNotExist($path);
    }

    /**
     * Uji integrasi nyata: bangun spreadsheet kecil dengan cache disk aktif lewat
     * CExporter::runWithDiskCellCache(), simpan ke xlsx, baca ulang - nilainya harus benar
     * dan Settings::getCache() harus kembali ke default (array memori) sesudahnya, bukan
     * ketinggalan cache disk yang berkasnya sudah dihapus.
     */
    public function testRunWithDiskCellCacheProducesCorrectFileAndRestoresDefaultCache() {
        $defaultCacheBefore = Settings::getCache();

        $path = sys_get_temp_dir() . '/cexporter_filecache_integration_test.xlsx';
        $this->files[] = $path;

        CExporter::runWithDiskCellCache(function () use ($path) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([
                ['CP-1', 100, 'bank'],
                ['CP-2', 200, 'ewallet'],
                ['CP-3', 300, null],
            ], null, 'A1');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($path);
        });

        // cache harus kembali ke keadaan semula (bukan FileCache yang berkasnya sudah dihapus)
        $defaultCacheAfter = Settings::getCache();
        $this->assertNotInstanceOf(CExporter_Cache_FileCache::class, $defaultCacheAfter);
        $this->assertSame(get_class($defaultCacheBefore), get_class($defaultCacheAfter));

        $this->assertFileExists($path);
        $reloaded = IOFactory::load($path);
        $reloadedSheet = $reloaded->getActiveSheet();
        $this->assertSame('CP-1', $reloadedSheet->getCell('A1')->getValue());
        $this->assertEquals(100, $reloadedSheet->getCell('B1')->getValue());
        $this->assertSame('bank', $reloadedSheet->getCell('C1')->getValue());
        $this->assertSame('CP-3', $reloadedSheet->getCell('A3')->getValue());
    }

    /**
     * Cache disk tetap dikembalikan ke semula walau $callback melempar exception -
     * kalau tidak, request LAIN di worker PHP-FPM yang sama akan ikut memakai cache
     * yang berkasnya sudah dihapus.
     */
    public function testCacheRestoredEvenWhenCallbackThrows() {
        $defaultCacheBefore = Settings::getCache();

        try {
            CExporter::runWithDiskCellCache(function () {
                throw new RuntimeException('simulasi gagal di tengah export');
            });
            $this->fail('exception seharusnya diteruskan ke pemanggil');
        } catch (RuntimeException $e) {
            $this->assertSame('simulasi gagal di tengah export', $e->getMessage());
        }

        $defaultCacheAfter = Settings::getCache();
        $this->assertNotInstanceOf(CExporter_Cache_FileCache::class, $defaultCacheAfter);
        $this->assertSame(get_class($defaultCacheBefore), get_class($defaultCacheAfter));
    }
}
