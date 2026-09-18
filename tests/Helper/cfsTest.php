<?php
use PHPUnit\Framework\TestCase;

/**
 * cfs - utilitas sistem berkas (daftar berkas/direktori, hapus rekursif, mkdir, atomic write).
 */
class cfsTest extends TestCase {
    /** @var string */
    protected $root;

    protected function setUp(): void {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uji-cfs-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/sub/deep', 0777, true);
        mkdir($this->root . '/abaikan', 0777, true);
        file_put_contents($this->root . '/a.txt', 'a');
        file_put_contents($this->root . '/b.txt', 'b');
        file_put_contents($this->root . '/Thumbs.db', 'x');
        file_put_contents($this->root . '/sub/c.txt', 'c');
        file_put_contents($this->root . '/sub/deep/d.txt', 'd');
        file_put_contents($this->root . '/abaikan/e.txt', 'e');
    }

    protected function tearDown(): void {
        cfs::delete_dir($this->root);
    }

    public function testListFilesOnlyReturnsFilesAtTheTopLevel() {
        $files = array_map('basename', cfs::list_files($this->root));
        sort($files);
        $this->assertSame(['a.txt', 'b.txt'], $files, 'direktori dan Thumbs.db dilewati');
        $this->assertSame([], cfs::list_files($this->root . '/tidak-ada'));
        $this->assertStringStartsWith($this->root . DIRECTORY_SEPARATOR, cfs::list_files($this->root)[0], 'path lengkap dengan pemisah');
    }

    public function testListDirOnlyReturnsDirectories() {
        $dirs = array_map('basename', cfs::list_dir($this->root));
        sort($dirs);
        $this->assertSame(['abaikan', 'sub'], $dirs);
    }

    public function testListFilesInDirIsRecursiveAndCanIgnoreDirectories() {
        $all = array_map(function ($p) {
            return substr($p, strlen(realpath($this->root)) + 1);
        }, cfs::list_files_in_dir($this->root));
        sort($all);
        $this->assertContains('sub/deep/d.txt', $all);
        $this->assertContains('abaikan/e.txt', $all);
        $this->assertContains('sub', $all, 'direktori ikut dicantumkan setelah isinya');
        $this->assertContains('Thumbs.db', $all, 'versi rekursif tidak menyaring Thumbs.db');

        $filtered = array_map(function ($p) {
            return substr($p, strlen(realpath($this->root)) + 1);
        }, cfs::list_files_in_dir($this->root, $results, ['abaikan']));
        $this->assertNotContains('abaikan/e.txt', $filtered);
        $this->assertContains('sub/c.txt', $filtered);
    }

    public function testDeleteDirRemovesEverything() {
        $this->assertTrue(cfs::delete_dir($this->root . '/sub'));
        $this->assertDirectoryDoesNotExist($this->root . '/sub');
        $this->assertFalse(cfs::delete_dir($this->root . '/tidak-ada'));
        $this->assertTrue(cfs::delete_dir($this->root . '/abaikan/'), 'slash di ujung diterima');
        $this->assertDirectoryDoesNotExist($this->root . '/abaikan');
    }

    public function testMkdirCreatesNestedDirectories() {
        $this->assertTrue(cfs::mkdir($this->root . '/x/y/z'));
        $this->assertDirectoryExists($this->root . '/x/y/z');
        $this->assertTrue(cfs::mkdir($this->root . '/x/y/z'), 'sudah ada dan bisa ditulis = true');
        $this->assertTrue(cfs::is_dir($this->root . '/x'));
        $this->assertFalse(cfs::is_dir($this->root . '/a.txt'));
    }

    public function testIsFileAndFileExistsStripNullBytes() {
        $this->assertTrue(cfs::is_file($this->root . '/a.txt'));
        $this->assertTrue(cfs::is_file($this->root . "/a.txt\0"), 'byte nol dibuang, tidak ValueError');
        $this->assertFalse(cfs::is_file($this->root));
        $this->assertTrue(cfs::file_exists($this->root . '/a.txt'));
        $this->assertFalse(cfs::file_exists($this->root), 'direktori bukan berkas');
        $this->assertFalse(cfs::file_exists($this->root . '/tidak-ada'));
        $this->assertSame('a.txt', cfs::basename($this->root . '/a.txt'));
    }

    public function testMtimeAndMtimeDiff() {
        $file = $this->root . '/a.txt';
        touch($file, $t = time() - 3600);
        clearstatcache();
        $this->assertSame($t, cfs::mtime($file));
        $this->assertEqualsWithDelta(3600, cfs::mtime_diff($file), 5);
        $this->assertSame(600, cfs::mtime_diff($file, $t + 600));
        $this->assertSame(60, cfs::mtime_diff($file, date('Y-m-d H:i:s', $t + 60)), 'waktu berupa string di-parse');
    }

    public function testAtomicWriteReplacesTheFileAndLeavesNoTempBehind() {
        $file = $this->root . '/atomic.txt';
        $this->assertSame(5, cfs::atomic_write($file, 'halo1'));
        $this->assertSame('halo1', file_get_contents($file));
        $this->assertSame(5, cfs::atomic_write($file, 'halo2'));
        $this->assertSame('halo2', file_get_contents($file));
        $this->assertFileDoesNotExist($file . '.atomictmp');
        $this->assertFalse(cfs::atomic_write($this->root . '/tidak/ada/x.txt', 'x'), 'direktori tak ada → false, bukan warning fatal');
    }
}
