<?php

use PHPUnit\Framework\TestCase;

class TemporaryFilePathHarnessForTest {
    use CTemporary_Trait_FilePathTrait;

    public function isFile($path) {
        return $this->isFilePath($path);
    }

    public function directoryOf($path) {
        return $this->removeFilenameFromPath($path);
    }
}

/**
 * Heuristik berkas-atau-direktori bersama Directory dan CustomDirectory: hanya segmen terakhir yang dinilai,
 * dan ekstensi harus memuat huruf.
 */
class TemporaryFilePathTest extends TestCase {
    /**
     * @return array
     */
    public function filePathProvider() {
        return [
            'berkas biasa' => ['laporan.pdf', true],
            'berkas dalam folder' => ['a/b/laporan.pdf', true],
            'ekstensi ganda' => ['arsip.tar.gz', true],
            'ekstensi berhuruf dan angka' => ['musik.mp3', true],
            'ekstensi diawali angka' => ['arsip.7z', true],
            'berkas tersembunyi' => ['a/.htaccess', true],
            'folder polos' => ['folder', false],
            'folder bertitik versi' => ['v1.2', false],
            'folder tanggal' => ['2026.10.03', false],
            'folder bertitik dalam path' => ['a/v1.2', false],
            'berkas di dalam folder bertitik' => ['a/v1.2/laporan.pdf', true],
            'titik di folder induk saja' => ['/home/pengguna.nama/temp/folder', false],
            'titik di root dan berkas' => ['/home/pengguna.nama/temp/laporan.pdf', true],
            'titik penutup tanpa ekstensi' => ['nama.', false],
            'garis miring penutup' => ['a/laporan.pdf/', true],
            'kosong' => ['', false],
        ];
    }

    /**
     * @dataProvider filePathProvider
     */
    public function testIsFilePath($path, $expected) {
        $this->assertSame($expected, (new TemporaryFilePathHarnessForTest())->isFile($path), $path);
    }

    public function testRemoveFilenameFromPathOnlyCutsRealFileNames() {
        $harness = new TemporaryFilePathHarnessForTest();
        $ds = DIRECTORY_SEPARATOR;

        $this->assertSame('a' . $ds . 'b', $harness->directoryOf('a' . $ds . 'b' . $ds . 'c.txt'));
        $this->assertSame('a' . $ds . 'b' . $ds . 'v1.2', $harness->directoryOf('a' . $ds . 'b' . $ds . 'v1.2'));
        $this->assertSame('a' . $ds . 'v1.2', $harness->directoryOf('a' . $ds . 'v1.2' . $ds . 'c.txt'));
        $this->assertSame('/home/pengguna.nama/temp/folder', $harness->directoryOf('/home/pengguna.nama/temp/folder'));
    }
}
