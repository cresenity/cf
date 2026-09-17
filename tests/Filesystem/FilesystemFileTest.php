<?php

use PHPUnit\Framework\TestCase;

/**
 * CFile - padanan suite hulu untuk kelas Filesystem (46 dari 47 method sama nama). Semua
 * berjalan di direktori sementara sendiri; API-nya statis, jadi tidak ada instance yang dibuat.
 *
 * `CFile::missing()` dan `CFile::json()` sengaja tidak diuji: keduanya dideklarasikan non-statis
 * di kelas yang seluruhnya statis, sehingga `CFile::missing()` fatal di PHP 8 - lihat docs/NOTES.md.
 */
class FilesystemFileTest extends TestCase {
    /**
     * @var string
     */
    private $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-file-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void {
        CFile::deleteDirectory($this->tempDir);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function path($name) {
        return $this->tempDir . '/' . $name;
    }

    public function testGetRetrievesFiles() {
        file_put_contents($this->path('file.txt'), 'Hello World');
        $this->assertSame('Hello World', CFile::get($this->path('file.txt')));
    }

    public function testPutStoresFiles() {
        CFile::put($this->path('file.txt'), 'Hello World');
        $this->assertStringEqualsFile($this->path('file.txt'), 'Hello World');
    }

    public function testLines() {
        $path = $this->path('file.txt');
        $contents = ' ' . PHP_EOL . ' spaces around ' . PHP_EOL . PHP_EOL . 'Line 2' . PHP_EOL . '1 trailing empty line ->' . PHP_EOL . PHP_EOL;
        file_put_contents($path, $contents);

        $this->assertInstanceOf(CCollection_LazyCollection::class, CFile::lines($path));
        $this->assertSame(
            [' ', ' spaces around ', '', 'Line 2', '1 trailing empty line ->', '', ''],
            CFile::lines($path)->all()
        );

        ftruncate(fopen($path, 'w'), 0);
        $this->assertSame([''], CFile::lines($path)->all());
    }

    public function testLinesThrowsExceptionNonexistingFile() {
        $this->expectException(CStorage_Exception_FileNotFoundException::class);
        CFile::lines($this->path('unknown-file.txt'));
    }

    public function testReplaceCreatesFile() {
        CFile::replace($this->path('file.txt'), 'Hello World');
        $this->assertStringEqualsFile($this->path('file.txt'), 'Hello World');
    }

    public function testReplaceInFileCorrectlyReplaces() {
        CFile::put($this->path('file.txt'), 'Hello World');
        CFile::replaceInFile('Hello World', 'Hello Taylor', $this->path('file.txt'));
        $this->assertStringEqualsFile($this->path('file.txt'), 'Hello Taylor');
    }

    public function testSetChmod() {
        file_put_contents($this->path('file.txt'), 'Hello World');
        CFile::chmod($this->path('file.txt'), 0755);
        $this->assertEquals('0755', substr(sprintf('%o', fileperms($this->path('file.txt'))), -4));
    }

    public function testGetChmod() {
        file_put_contents($this->path('file.txt'), 'Hello World');
        chmod($this->path('file.txt'), 0755);
        $this->assertEquals('0755', CFile::chmod($this->path('file.txt')));
    }

    public function testDeleteRemovesFiles() {
        file_put_contents($this->path('file1.txt'), 'Hello World');
        file_put_contents($this->path('file2.txt'), 'Hello World');
        file_put_contents($this->path('file3.txt'), 'Hello World');

        CFile::delete($this->path('file1.txt'));
        $this->assertFileDoesNotExist($this->path('file1.txt'));

        CFile::delete([$this->path('file2.txt'), $this->path('file3.txt')]);
        $this->assertFileDoesNotExist($this->path('file2.txt'));
        $this->assertFileDoesNotExist($this->path('file3.txt'));
    }

    public function testPrependExistingFiles() {
        CFile::put($this->path('file.txt'), 'World');
        CFile::prepend($this->path('file.txt'), 'Hello ');
        $this->assertStringEqualsFile($this->path('file.txt'), 'Hello World');
    }

    public function testPrependNewFiles() {
        CFile::prepend($this->path('file.txt'), 'Hello World');
        $this->assertStringEqualsFile($this->path('file.txt'), 'Hello World');
    }

    public function testDeleteDirectory() {
        mkdir($this->path('foo'));
        file_put_contents($this->path('foo/file.txt'), 'Hello World');
        CFile::deleteDirectory($this->path('foo'));
        $this->assertDirectoryDoesNotExist($this->path('foo'));
        $this->assertFileDoesNotExist($this->path('foo/file.txt'));
    }

    public function testDeleteDirectoryReturnFalseWhenNotADirectory() {
        mkdir($this->path('bar'));
        file_put_contents($this->path('bar/file.txt'), 'Hello World');
        $this->assertFalse(CFile::deleteDirectory($this->path('bar/file.txt')));
    }

    public function testDeleteDirectoryPreserve() {
        mkdir($this->path('keep'));
        file_put_contents($this->path('keep/file.txt'), 'Hello World');
        CFile::deleteDirectory($this->path('keep'), true);
        $this->assertDirectoryExists($this->path('keep'));
        $this->assertFileDoesNotExist($this->path('keep/file.txt'));
    }

    public function testCleanDirectory() {
        mkdir($this->path('baz'));
        file_put_contents($this->path('baz/file.txt'), 'Hello World');
        CFile::cleanDirectory($this->path('baz'));
        $this->assertDirectoryExists($this->path('baz'));
        $this->assertFileDoesNotExist($this->path('baz/file.txt'));
    }

    public function testFilesMethod() {
        mkdir($this->path('views'));
        file_put_contents($this->path('views/1.txt'), '1');
        file_put_contents($this->path('views/2.txt'), '2');
        mkdir($this->path('views/_layouts'));

        $results = CFile::files($this->path('views'));
        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(SplFileInfo::class, $results);
    }

    public function testCopyDirectoryReturnsFalseIfSourceIsntDirectory() {
        $this->assertFalse(CFile::copyDirectory($this->path('breeze/boom/foo/bar/baz'), $this->tempDir));
    }

    public function testCopyDirectoryMovesEntireDirectory() {
        mkdir($this->path('tmp'), 0777, true);
        file_put_contents($this->path('tmp/foo.txt'), '');
        file_put_contents($this->path('tmp/bar.txt'), '');
        mkdir($this->path('tmp/nested'), 0777, true);
        file_put_contents($this->path('tmp/nested/baz.txt'), '');

        CFile::copyDirectory($this->path('tmp'), $this->path('tmp2'));

        $this->assertDirectoryExists($this->path('tmp2'));
        $this->assertFileExists($this->path('tmp2/foo.txt'));
        $this->assertFileExists($this->path('tmp2/bar.txt'));
        $this->assertDirectoryExists($this->path('tmp2/nested'));
        $this->assertFileExists($this->path('tmp2/nested/baz.txt'));
    }

    public function testMoveDirectoryMovesEntireDirectory() {
        mkdir($this->path('tmp2'), 0777, true);
        file_put_contents($this->path('tmp2/foo.txt'), '');
        file_put_contents($this->path('tmp2/bar.txt'), '');
        mkdir($this->path('tmp2/nested'), 0777, true);
        file_put_contents($this->path('tmp2/nested/baz.txt'), '');

        CFile::moveDirectory($this->path('tmp2'), $this->path('tmp3'));

        $this->assertDirectoryExists($this->path('tmp3'));
        $this->assertFileExists($this->path('tmp3/foo.txt'));
        $this->assertFileExists($this->path('tmp3/bar.txt'));
        $this->assertDirectoryExists($this->path('tmp3/nested'));
        $this->assertFileExists($this->path('tmp3/nested/baz.txt'));
        $this->assertDirectoryDoesNotExist($this->path('tmp2'));
    }

    public function testMoveDirectoryMovesEntireDirectoryAndOverwrites() {
        mkdir($this->path('tmp4'), 0777, true);
        file_put_contents($this->path('tmp4/foo.txt'), '');
        file_put_contents($this->path('tmp4/bar.txt'), '');
        mkdir($this->path('tmp4/nested'), 0777, true);
        file_put_contents($this->path('tmp4/nested/baz.txt'), '');
        mkdir($this->path('tmp5'), 0777, true);
        file_put_contents($this->path('tmp5/foo2.txt'), '');
        file_put_contents($this->path('tmp5/bar2.txt'), '');

        CFile::moveDirectory($this->path('tmp4'), $this->path('tmp5'), true);

        $this->assertDirectoryExists($this->path('tmp5'));
        $this->assertFileExists($this->path('tmp5/foo.txt'));
        $this->assertFileExists($this->path('tmp5/bar.txt'));
        $this->assertDirectoryExists($this->path('tmp5/nested'));
        $this->assertFileExists($this->path('tmp5/nested/baz.txt'));
        $this->assertFileDoesNotExist($this->path('tmp5/foo2.txt'));
        $this->assertFileDoesNotExist($this->path('tmp5/bar2.txt'));
        $this->assertDirectoryDoesNotExist($this->path('tmp4'));
    }

    public function testGetThrowsExceptionNonexistingFile() {
        $this->expectException(CStorage_Exception_FileNotFoundException::class);
        CFile::get($this->path('unknown-file.txt'));
    }

    public function testGetRequireReturnsProperly() {
        file_put_contents($this->path('file.php'), '<?php return "Howdy?"; ?>');
        $this->assertSame('Howdy?', CFile::getRequire($this->path('file.php')));
    }

    public function testGetRequireSharesData() {
        file_put_contents($this->path('data.php'), '<?php return $name . "!";');
        $this->assertSame('Hery!', CFile::getRequire($this->path('data.php'), ['name' => 'Hery']));
    }

    public function testGetRequireThrowsExceptionNonExistingFile() {
        $this->expectException(CStorage_Exception_FileNotFoundException::class);
        CFile::getRequire($this->path('unknown-file.txt'));
    }

    public function testAppendAddsDataToFile() {
        file_put_contents($this->path('file.txt'), 'foo');
        $bytesWritten = CFile::append($this->path('file.txt'), 'bar');
        $this->assertEquals(mb_strlen('bar', '8bit'), $bytesWritten);
        $this->assertFileExists($this->path('file.txt'));
        $this->assertStringEqualsFile($this->path('file.txt'), 'foobar');
    }

    public function testMoveMovesFiles() {
        file_put_contents($this->path('foo.txt'), 'foo');
        CFile::move($this->path('foo.txt'), $this->path('bar.txt'));
        $this->assertFileExists($this->path('bar.txt'));
        $this->assertFileDoesNotExist($this->path('foo.txt'));
    }

    public function testPathParts() {
        file_put_contents($this->path('foobar.txt'), 'foo');
        $this->assertSame('foobar', CFile::name($this->path('foobar.txt')));
        $this->assertSame('txt', CFile::extension($this->path('foobar.txt')));
        $this->assertSame('foobar.txt', CFile::basename($this->path('foobar.txt')));
        $this->assertEquals($this->tempDir, CFile::dirname($this->path('foobar.txt')));
    }

    public function testTypeIdentifiesFileAndDirectory() {
        file_put_contents($this->path('foo.txt'), 'foo');
        mkdir($this->path('foo-dir'));
        $this->assertSame('file', CFile::type($this->path('foo.txt')));
        $this->assertSame('dir', CFile::type($this->path('foo-dir')));
    }

    public function testSizeOutputsSize() {
        $size = file_put_contents($this->path('foo.txt'), 'foo');
        $this->assertEquals($size, CFile::size($this->path('foo.txt')));
    }

    public function testMimeTypeOutputsMimeType() {
        file_put_contents($this->path('foo.txt'), 'foo');
        $this->assertSame('text/plain', CFile::mimeType($this->path('foo.txt')));
    }

    public function testIsWritable() {
        file_put_contents($this->path('foo.txt'), 'foo');
        @chmod($this->path('foo.txt'), 0444);
        $this->assertFalse(CFile::isWritable($this->path('foo.txt')));
        @chmod($this->path('foo.txt'), 0777);
        $this->assertTrue(CFile::isWritable($this->path('foo.txt')));
    }

    public function testIsReadable() {
        file_put_contents($this->path('foo.txt'), 'foo');
        @chmod($this->path('foo.txt'), 0000);
        $this->assertFalse(CFile::isReadable($this->path('foo.txt')));
        @chmod($this->path('foo.txt'), 0777);
        $this->assertTrue(CFile::isReadable($this->path('foo.txt')));
        $this->assertFalse(CFile::isReadable($this->path('doesnotexist.txt')));
    }

    public function testIsEmptyDirectory() {
        mkdir($this->path('foo-dir'));
        file_put_contents($this->path('foo-dir/.hidden'), 'foo');
        mkdir($this->path('bar-dir'));
        file_put_contents($this->path('bar-dir/foo.txt'), 'foo');
        mkdir($this->path('baz-dir'));
        mkdir($this->path('baz-dir/.hidden'));
        mkdir($this->path('quz-dir'));
        mkdir($this->path('quz-dir/not-hidden'));

        $this->assertTrue(CFile::isEmptyDirectory($this->path('foo-dir'), true));
        $this->assertFalse(CFile::isEmptyDirectory($this->path('foo-dir')));
        $this->assertFalse(CFile::isEmptyDirectory($this->path('bar-dir'), true));
        $this->assertFalse(CFile::isEmptyDirectory($this->path('bar-dir')));
        $this->assertTrue(CFile::isEmptyDirectory($this->path('baz-dir'), true));
        $this->assertFalse(CFile::isEmptyDirectory($this->path('baz-dir')));
        $this->assertFalse(CFile::isEmptyDirectory($this->path('quz-dir'), true));
        $this->assertFalse(CFile::isEmptyDirectory($this->path('quz-dir')));
    }

    public function testGlobFindsFiles() {
        file_put_contents($this->path('foo.txt'), 'foo');
        file_put_contents($this->path('bar.txt'), 'bar');
        $glob = CFile::glob($this->path('*.txt'));
        $this->assertContains($this->path('foo.txt'), $glob);
        $this->assertContains($this->path('bar.txt'), $glob);
    }

    public function testAllFilesFindsFiles() {
        file_put_contents($this->path('foo.txt'), 'foo');
        file_put_contents($this->path('bar.txt'), 'bar');
        mkdir($this->path('deep'));
        file_put_contents($this->path('deep/baz.txt'), 'baz');

        $allFiles = [];
        foreach (CFile::allFiles($this->tempDir) as $file) {
            $allFiles[] = $file->getFilename();
        }
        $this->assertContains('foo.txt', $allFiles);
        $this->assertContains('bar.txt', $allFiles);
        $this->assertContains('baz.txt', $allFiles);
        $this->assertContainsOnlyInstancesOf(SplFileInfo::class, CFile::allFiles($this->tempDir));
    }

    public function testDirectoriesFindsDirectories() {
        mkdir($this->path('film'));
        mkdir($this->path('music'));
        $directories = CFile::directories($this->tempDir);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'film', $directories);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'music', $directories);
    }

    public function testMakeDirectory() {
        $this->assertTrue(CFile::makeDirectory($this->path('created')));
        $this->assertFileExists($this->path('created'));
        $this->assertTrue(CFile::makeDirectory($this->path('a/b/c'), 0755, true));
        $this->assertTrue(CFile::isDirectory($this->path('a/b/c')));
    }

    public function testRequireOnceRequiresFileProperly() {
        mkdir($this->path('scripts'));
        file_put_contents($this->path('scripts/foo.php'), '<?php function cf_file_test_random_function_xyz(){};');
        CFile::requireOnce($this->path('scripts/foo.php'));
        file_put_contents($this->path('scripts/foo.php'), '<?php function cf_file_test_random_function_xyz_changed(){};');
        CFile::requireOnce($this->path('scripts/foo.php'));
        $this->assertTrue(function_exists('cf_file_test_random_function_xyz'));
        $this->assertFalse(function_exists('cf_file_test_random_function_xyz_changed'));
    }

    public function testMissingFile() {
        $this->assertTrue(CFile::missing($this->path('file.txt')));
        file_put_contents($this->path('file.txt'), 'x');
        $this->assertFalse(CFile::missing($this->path('file.txt')));
    }

    public function testJsonReturnsDecodedJsonData() {
        file_put_contents($this->path('file.json'), '{"foo": "bar"}');
        $this->assertSame(['foo' => 'bar'], CFile::json($this->path('file.json')));

        file_put_contents($this->path('rusak.json'), '{"foo":');
        $this->assertNull(CFile::json($this->path('rusak.json')));
    }

    public function testRequireOnceThrowsExceptionNonexistingFile() {
        $this->expectException(CStorage_Exception_FileNotFoundException::class);
        CFile::requireOnce($this->path('unknown-file.txt'));
    }

    public function testCopyCopiesFileProperly() {
        mkdir($this->path('text'));
        file_put_contents($this->path('text/foo.txt'), 'contents');
        CFile::copy($this->path('text/foo.txt'), $this->path('text/foo2.txt'));
        $this->assertFileExists($this->path('text/foo2.txt'));
        $this->assertEquals('contents', file_get_contents($this->path('text/foo2.txt')));
    }

    public function testHasSameHashChecksFileHashes() {
        mkdir($this->path('text'));
        file_put_contents($this->path('text/foo.txt'), 'contents');
        file_put_contents($this->path('text/foo2.txt'), 'contents');
        file_put_contents($this->path('text/foo3.txt'), 'invalid');
        $this->assertTrue(CFile::hasSameHash($this->path('text/foo.txt'), $this->path('text/foo2.txt')));
        $this->assertFalse(CFile::hasSameHash($this->path('text/foo.txt'), $this->path('text/foo3.txt')));
        $this->assertFalse(CFile::hasSameHash($this->path('text/foo4.txt'), $this->path('text/foo.txt')));
        $this->assertFalse(CFile::hasSameHash($this->path('text/foo.txt'), $this->path('text/foo4.txt')));
    }

    public function testIsFileChecksFilesProperly() {
        mkdir($this->path('help'));
        file_put_contents($this->path('help/foo.txt'), 'contents');
        $this->assertTrue(CFile::isFile($this->path('help/foo.txt')));
        $this->assertFalse(CFile::isFile($this->path('help')));
    }

    public function testHash() {
        file_put_contents($this->path('foo.txt'), 'foo');
        $this->assertSame('acbd18db4cc2f85cedef654fccc4a4d8', CFile::hash($this->path('foo.txt')));
        $this->assertSame('0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33', CFile::hash($this->path('foo.txt'), 'sha1'));
    }

    public function testLastModifiedReturnsTimestamp() {
        $path = $this->path('timestamp.txt');
        file_put_contents($path, 'test content');
        $timestamp = CFile::lastModified($path);
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
        $this->assertEquals(filemtime($path), $timestamp);
    }

    public function testDirectoryOperationsWithSubdirectories() {
        $dirPath = $this->path('test_dir');
        $subDirPath = $dirPath . '/sub_dir';

        $this->assertTrue(CFile::makeDirectory($dirPath));
        $this->assertTrue(CFile::isDirectory($dirPath));
        $this->assertTrue(CFile::makeDirectory($subDirPath));
        $this->assertTrue(CFile::isDirectory($subDirPath));

        CFile::put($subDirPath . '/test.txt', 'test content');
        $this->assertTrue(CFile::exists($subDirPath . '/test.txt'));

        $allFiles = CFile::allFiles($dirPath);
        $this->assertCount(1, $allFiles);
        $this->assertSame('test.txt', $allFiles[0]->getFilename());
    }
}
