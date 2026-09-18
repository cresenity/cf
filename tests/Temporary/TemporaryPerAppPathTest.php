<?php

use PHPUnit\Framework\TestCase;

/**
 * Semua berkas temp dipisah per app (`temp/<folder>/<appCode>/...`), meniru pemisahan temp/ajax:
 * berkas baru ditulis ke folder app, berkas yang hanya ada di lokasi lama bersama masih ditemukan.
 */
class TemporaryPerAppPathTest extends TestCase {
    /**
     * @var string[]
     */
    protected $created = [];

    protected function tearDown(): void {
        $disk = CTemporary::disk();
        foreach ($this->created as $file) {
            if ($disk->exists($file)) {
                $disk->delete($file);
            }
        }
        foreach (['uji-temp', 'uji-dir'] as $folder) {
            $path = DOCROOT . 'temp/' . $folder;
            if (is_dir($path)) {
                exec('rm -rf ' . escapeshellarg($path));
            }
        }
    }

    /**
     * @return string
     */
    protected function filename() {
        return date('Ymd') . cutils::randmd5() . '.txt';
    }

    public function testAppFolderAppendsTheAppCodeOnce() {
        $appCode = CF::appCode();

        $this->assertSame('imgupload/' . $appCode, CTemporary::appFolder('imgupload'));
        $this->assertSame('imgupload/' . $appCode, CTemporary::appFolder('imgupload/' . $appCode), 'folder yang sudah per app tidak digandakan');
        $this->assertSame('common/' . $appCode, CTemporary::appFolder(null));
    }

    public function testNewFilesLandUnderTheAppFolder() {
        $filename = $this->filename();
        $path = CTemporary::put('uji-temp', 'isi', $filename);
        $this->created[] = $path;

        $this->assertStringStartsWith('uji-temp/' . CF::appCode() . '/' . date('Ymd') . '/', $path);
        $this->assertSame($path, CTemporary::getPath('uji-temp', $filename));
        $this->assertSame('isi', CTemporary::get('uji-temp', $filename));
        $this->assertSame(3, CTemporary::getSize('uji-temp', $filename));
        $this->assertTrue(CTemporary::isExists('uji-temp', $filename));
        $this->assertSame(rtrim(DOCROOT, '/') . '/temp/' . $path, CTemporary::getLocalPath('uji-temp', $filename));
        $this->assertStringEndsWith('/temp/' . $path, CTemporary::getUrl('uji-temp', $filename));
    }

    public function testAFileOnlyInTheSharedLocationIsStillResolvedThere() {
        $filename = $this->filename();
        $legacy = 'uji-temp/' . date('Ymd') . '/' . implode('/', str_split(substr($filename, 8, 5))) . '/' . $filename;
        CTemporary::disk()->put($legacy, 'lama');
        $this->created[] = $legacy;

        $this->assertSame($legacy, CTemporary::getPath('uji-temp', $filename));
        $this->assertSame('lama', CTemporary::get('uji-temp', $filename));
        $this->assertSame(rtrim(DOCROOT, '/') . '/temp/' . $legacy, CTemporary::getLocalPath('uji-temp', $filename));
        $this->assertSame(rtrim(DOCROOT, '/') . '/temp/' . $legacy, CTemporary::makePath('uji-temp', $filename));
        $this->assertTrue(CTemporary::delete('uji-temp', $filename));
        $this->assertFalse(CTemporary::disk()->exists($legacy));

        // sesudah berkas lama hilang, nama yang sama menunjuk ke folder app
        $this->assertStringStartsWith('uji-temp/' . CF::appCode() . '/', CTemporary::getPath('uji-temp', $filename));
    }

    public function testMakePathCreatesTheAppDirectoryForANewFile() {
        $filename = $this->filename();
        $path = CTemporary::makePath('uji-temp', $filename);

        $this->assertStringStartsWith(rtrim(DOCROOT, '/') . '/temp/uji-temp/' . CF::appCode() . '/', $path);
        $this->assertDirectoryExists(dirname($path));
        $this->assertFileDoesNotExist($path);
    }

    public function testDirectoryHelpersArePerAppToo() {
        $this->assertSame(DOCROOT . 'temp/uji-dir/' . CF::appCode() . '/', CTemporary::getDirectory('uji-dir'));

        $directory = CTemporary::createDirectory('uji-dir/sesi123');
        $this->assertSame(rtrim(DOCROOT, '/') . '/temp/uji-dir/' . CF::appCode() . '/sesi123/', $directory->getPath());
        $this->assertDirectoryExists($directory->getPath());
        $directory->delete();
    }
}
