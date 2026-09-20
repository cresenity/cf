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
    public function testDirectoryGetPathFailsToCreateASubdirectoryNamedWithADot() {
        $this->createdFolders[] = 'uji-bug10';
        $dir = CTemporary::createDirectory('uji-bug10');

        $withoutDot = $dir->getPath('subfolder');
        $withDot = $dir->getPath('v1.2');

        $this->assertDirectoryExists($withoutDot, 'subdirektori tanpa titik dibuat dengan benar');
        $this->assertDirectoryDoesNotExist(
            $withDot,
            'BUG #10: subdirektori "v1.2" salah dikira nama berkas (karena mengandung titik) sehingga tidak dibuat sama sekali'
        );
    }

    /**
     * Temuan #11: bug yang sama persis dengan #10, ditulis ulang independen di
     * CTemporary_CustomDirectory (bukti duplikasi logic isFilePath()/removeFilenameFromPath()
     * antara Directory dan CustomDirectory, lihat §1 & §2 dokumen analisa).
     */
    public function testCustomDirectoryPathFailsToCreateASubdirectoryNamedWithADot() {
        $dir = CTemporary::customDirectory()->force()->create();

        try {
            $withoutDot = $dir->path('subfolder');
            $withDot = $dir->path('v1.2');

            $this->assertDirectoryExists($withoutDot, 'subdirektori tanpa titik dibuat dengan benar');
            $this->assertDirectoryDoesNotExist(
                $withDot,
                'BUG #11: sama seperti #10, tapi di implementasi CustomDirectory yang terpisah'
            );
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
     * Temuan #14: CTemporary_LocalFile::getFileName() memanggil
     * ->getDriver()->getAdapter()->getPathPrefix(), API gaya Flysystem v1 lama.
     * CStorage_Adapter::getDriver() di kode saat ini mengembalikan objek
     * League\Flysystem\Filesystem (v2/v3), yang TIDAK punya method getAdapter() publik
     * — jadi baris ini fatal error setiap kali dipanggil, bukan cuma risiko teoretis.
     */
    public function testLocalFileGetFileNameFailsAgainstTheCurrentFlysystemApi() {
        $file = CTemporary::createLocalFile('isi apa saja untuk uji bug 14');

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('getAdapter');

        $file->getFileName();
    }
}
