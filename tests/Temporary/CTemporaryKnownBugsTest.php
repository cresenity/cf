<?php

use PHPUnit\Framework\TestCase;

/**
 * Regression test yang MEMBUKTIKAN (bukan memperbaiki) temuan bug pada
 * papers/ANALISA-REFACTOR-CTEMPORARY.md §2 yang statusnya masih "belum" dikerjakan
 * (#7, #10, #11, #14). Setiap test mengasersikan perilaku SAAT INI yang salah, sesuai
 * kebijakan "dokumentasikan, jangan diam-diam diperbaiki" untuk bug yang bukan bagian
 * dari pekerjaan sesi ini. Kalau bug-nya diperbaiki di kemudian hari, test ini yang
 * pertama gagal dan harus diupdate mengikuti kontrak baru.
 */
class CTemporaryKnownBugsTest extends TestCase {
    /**
     * @var string[]
     */
    protected $createdFiles = [];

    /**
     * @var string[]
     */
    protected $createdFolders = [];

    protected function tearDown(): void {
        $disk = CTemporary::disk();
        foreach ($this->createdFiles as $file) {
            if ($disk->exists($file)) {
                $disk->delete($file);
            }
        }
        foreach ($this->createdFolders as $folder) {
            $path = DOCROOT . 'temp/' . $folder;
            if (is_dir($path)) {
                exec('rm -rf ' . escapeshellarg($path));
            }
        }
    }

    /**
     * Temuan #10: CTemporary_Directory::isFilePath() menganggap SEMBARANG titik di path
     * sebagai tanda "ini nama berkas", jadi getPath() gagal membuat subdirektori yang
     * namanya sendiri mengandung titik (mis. versi "v1.2") — beda perlakuan dari nama
     * subdirektori sejenis yang tidak mengandung titik.
     */
    public function testDirectoryGetPathCreatesASubdirectoryNamedWithADot() {
        $this->createdFolders[] = 'uji-bug10';
        $dir = CTemporary::createDirectory('uji-bug10');

        $withoutDot = $dir->getPath('subfolder');
        $withDot = $dir->getPath('v1.2');

        $this->assertDirectoryExists($withoutDot, 'subdirektori tanpa titik dibuat dengan benar');
        $this->assertDirectoryExists($withDot, 'subdirektori "v1.2" bukan nama berkas (ekstensinya hanya angka) sehingga dibuat');
    }

    /**
     * Temuan #11: bug yang sama persis dengan #10, ditulis ulang independen di
     * CTemporary_CustomDirectory (bukti duplikasi logic isFilePath()/removeFilenameFromPath()
     * antara Directory dan CustomDirectory, lihat §1 & §2 dokumen analisa).
     */
    public function testCustomDirectoryPathCreatesASubdirectoryNamedWithADot() {
        $dir = CTemporary::customDirectory()->force()->create();

        try {
            $withoutDot = $dir->path('subfolder');
            $withDot = $dir->path('v1.2');

            $this->assertDirectoryExists($withoutDot, 'subdirektori tanpa titik dibuat dengan benar');
            $this->assertDirectoryExists($withDot, 'sama seperti Directory, di implementasi CustomDirectory');
        } finally {
            $dir->delete();
        }
    }

    /**
     * Temuan #7: CTemporary::put($folder, $content, $filename) vs
     * CTemporary_Instance::put($content, $folder, $filename) — nama method sama persis,
     * urutan parameter terbalik. Memanggil Instance::put() dengan urutan gaya facade
     * (folder dulu, content kedua) diam-diam menukar peran keduanya: string yang
     * dimaksud sebagai folder malah tersimpan sebagai ISI file, dan string yang
     * dimaksud sebagai isi malah dipakai sebagai NAMA folder.
     */
    public function testInstancePutSilentlySwapsFolderAndContentWhenCalledFacadeStyle() {
        $intendedFolder = 'uji-bug7';
        $intendedContent = 'isi asli file uji bug 7';
        $this->createdFolders[] = $intendedFolder;
        $this->createdFolders[] = $intendedContent;

        $path = CTemporary::instance()->put($intendedFolder, $intendedContent);
        $this->createdFiles[] = $path;

        $this->assertStringNotContainsString(
            $intendedFolder,
            $path,
            'BUG #7: nama folder di path yang tersimpan seharusnya "uji-bug7", tapi urutan parameter terbalik membuatnya memakai isi $content sebagai folder'
        );
        $this->assertStringContainsString(
            $intendedContent,
            $path,
            'BUG #7: string yang dimaksud sebagai ISI file malah muncul sebagai nama FOLDER di path'
        );
        $this->assertSame(
            $intendedFolder,
            CTemporary::disk()->get($path),
            'BUG #7: string yang dimaksud sebagai nama FOLDER malah tersimpan sebagai ISI file'
        );
    }

    /**
     * Temuan #14 (diperbaiki): getFileName() memakai CStorage_Adapter::path() sehingga tidak lagi bergantung
     * pada API Flysystem v1 (getAdapter()->getPathPrefix()) yang sudah tidak ada.
     */
    public function testLocalFileGetFileNameReturnsTheAbsolutePathOfTheStoredFile() {
        $file = CTemporary::createLocalFile('isi uji bug 14');

        $path = $file->getFileName();

        $this->assertFileExists($path);
        $this->assertSame('isi uji bug 14', file_get_contents($path));
        $this->assertSame($path, (string) $file, '__toString() memakai getFileName()');
        $this->assertStringStartsWith(rtrim(CTemporary::local()->disk()->path(''), '/'), $path, 'berada di bawah root disk temp lokal');
    }

    public function testLocalFileDeleteRemovesTheFileAndToleratesAMissingOne() {
        $file = CTemporary::createLocalFile('akan dihapus', null, null, false);
        $path = $file->getFileName();
        $this->assertFileExists($path);

        $this->assertTrue($file->delete());
        $this->assertFileDoesNotExist($path);
        $this->assertFalse($file->delete(), 'berkas yang sudah tidak ada dilaporkan false, bukan galat');
    }

    public function testLocalFileIsDeletedWhenTheObjectIsDestroyed() {
        $file = CTemporary::createLocalFile('hilang saat objek dibuang');
        $path = $file->getFileName();
        $this->assertFileExists($path);

        unset($file);

        $this->assertFileDoesNotExist($path);
    }
}
