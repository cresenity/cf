<?php
use PHPUnit\Framework\TestCase;

/**
 * CExporter_File_TemporaryFileFactory::makeLocal(): berkas sementara ekspor ada di temp/exporter/<appCode>/<Ymd>/,
 * appCode tepat setelah folder tipe, tanpa garis miring ganda.
 */
class ExporterTemporaryFilePathTest extends TestCase {
    /**
     * @param string $path
     *
     * @return void
     */
    protected function removeIfEmpty($path) {
        @rmdir($path);
    }

    public function testLocalTemporaryFileLivesUnderExporterAppCodeDate() {
        $file = CExporter_File_TemporaryFileFactory::instance()->makeLocal();
        $path = $file->getLocalPath();
        $appFolder = CTemporary::appFolder('exporter');

        $this->assertSame(
            DOCROOT . 'temp' . DS . $appFolder . DS . date('Ymd'),
            dirname($path),
            'temp/exporter/<appCode>/<Ymd>'
        );
        $this->assertStringNotContainsString(DS . DS, substr($path, strlen(DOCROOT)), 'tidak ada garis miring ganda');
        $this->assertStringStartsWith('capp-exporter-', basename($path));
        $this->assertDirectoryExists(dirname($path));
    }

    public function testExtensionAndExplicitFileNameAreKept() {
        $factory = CExporter_File_TemporaryFileFactory::instance();

        $this->assertStringEndsWith('.xlsx', $factory->makeLocal(null, 'xlsx')->getLocalPath());
        $this->assertSame('tetap.csv', basename($factory->makeLocal('tetap.csv')->getLocalPath()));
    }

    public function testDifferentAppsGetDifferentFolders() {
        $mine = dirname(CExporter_File_TemporaryFileFactory::instance()->makeLocal()->getLocalPath());
        $other = CF::asAppCode('uji-app-lain', function () {
            return dirname(CExporter_File_TemporaryFileFactory::instance()->makeLocal()->getLocalPath());
        });

        $this->assertNotSame($mine, $other);
        $this->assertSame(DOCROOT . 'temp' . DS . 'exporter' . DS . 'uji-app-lain' . DS . date('Ymd'), $other);

        $this->removeIfEmpty($other);
        $this->removeIfEmpty(dirname($other));
    }
}
