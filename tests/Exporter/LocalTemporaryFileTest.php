<?php

use PHPUnit\Framework\TestCase;

/**
 * Berkas sementara exporter tidak boleh dibuat sebelum ada isinya: export yang gagal
 * di tengah jalan sebelumnya meninggalkan cangkang kosong di temp/ yang tidak pernah disapu.
 */
class Exporter_LocalTemporaryFileTest extends TestCase {
    /**
     * @var string
     */
    protected $dir;

    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-exporter-temp-' . uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testConstructingDoesNotCreateTheFile() {
        $path = $this->dir . DIRECTORY_SEPARATOR . 'capp-exporter-uji';

        $file = new CExporter_File_LocalTemporaryFile($path);

        $this->assertFileDoesNotExist($path);
        $this->assertFalse($file->exists());
        $this->assertSame($path, $file->getLocalPath());
    }

    public function testPathIsNormalizedThroughItsDirectory() {
        $file = new CExporter_File_LocalTemporaryFile($this->dir . DIRECTORY_SEPARATOR . '.' . DIRECTORY_SEPARATOR . 'capp-exporter-uji');

        $this->assertSame($this->dir . DIRECTORY_SEPARATOR . 'capp-exporter-uji', $file->getLocalPath());
    }

    public function testFileAppearsOnFirstWriteAndDeleteRemovesIt() {
        $file = new CExporter_File_LocalTemporaryFile($this->dir . DIRECTORY_SEPARATOR . 'capp-exporter-uji');

        $file->put('isi');

        $this->assertTrue($file->exists());
        $this->assertSame('isi', $file->contents());
        $this->assertTrue($file->delete());
        $this->assertFalse($file->exists());
    }

    public function testDeletingAFileThatWasNeverWrittenIsHarmless() {
        $file = new CExporter_File_LocalTemporaryFile($this->dir . DIRECTORY_SEPARATOR . 'capp-exporter-uji');

        $this->assertTrue($file->delete());
    }
}
