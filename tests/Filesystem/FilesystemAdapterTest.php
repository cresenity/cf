<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CStorage_Adapter di atas driver lokal - padanan suite hulu untuk FilesystemAdapter. Disk
 * dibuat sekali pakai lewat `CStorage::instance()->build($root)` di direktori sementara sendiri,
 * jadi tidak menyentuh disk `local`/`local-temp` yang dipakai aplikasi.
 */
class FilesystemAdapterTest extends TestCase {
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var CStorage_Adapter
     */
    private $disk;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-storage-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->disk = CStorage::instance()->build($this->tempDir);
    }

    protected function tearDown(): void {
        CFile::deleteDirectory($this->tempDir);
    }

    public function testBuildReturnsAnAdapterRootedAtTheGivenDirectory() {
        $this->assertInstanceOf(CStorage_Adapter::class, $this->disk);
        $this->assertSame($this->tempDir . DIRECTORY_SEPARATOR . 'file.txt', $this->disk->path('file.txt'));
    }

    public function testResponse() {
        $this->disk->put('file.txt', 'Hello World');
        $response = $this->disk->response('file.txt');

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('Hello World', $content);
        $this->assertSame('inline; filename="file.txt"', $response->headers->get('content-disposition'));
    }

    public function testDownload() {
        $this->disk->put('file.txt', 'Hello World');
        $response = $this->disk->download('file.txt', 'hello.txt');
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('attachment; filename="hello.txt"', $response->headers->get('content-disposition'));
    }

    public function testDownloadNonAsciiFilename() {
        $this->disk->put('file.txt', 'Hello World');
        $response = $this->disk->download('file.txt', 'пиздюк.txt');
        $this->assertSame('attachment; filename="pizdyuk.txt"; filename*=utf-8\'\'%D0%BF%D0%B8%D0%B7%D0%B4%D1%8E%D0%BA.txt', $response->headers->get('content-disposition'));
    }

    public function testDownloadPercentInFilename() {
        $this->disk->put('Hello%World.txt', 'Hello World');
        $response = $this->disk->download('Hello%World.txt', 'Hello%World.txt');
        $this->assertSame('attachment; filename="HelloWorld.txt"; filename*=utf-8\'\'Hello%25World.txt', $response->headers->get('content-disposition'));
    }

    public function testExistsAndMissing() {
        $this->disk->put('file.txt', 'Hello World');
        $this->assertTrue($this->disk->exists('file.txt'));
        $this->assertTrue($this->disk->fileExists('file.txt'));
        $this->assertFalse($this->disk->missing('file.txt'));
        $this->assertFalse($this->disk->fileMissing('file.txt'));

        $this->assertFalse($this->disk->exists('tidak-ada.txt'));
        $this->assertTrue($this->disk->missing('tidak-ada.txt'));
        $this->assertTrue($this->disk->fileMissing('tidak-ada.txt'));
    }

    public function testDirectoryExistsAndMissing() {
        $this->disk->put('foo/bar/file.txt', 'Hello World');
        $this->assertTrue($this->disk->directoryExists('foo/bar'));
        $this->assertFalse($this->disk->directoryMissing('foo/bar'));
        $this->assertFalse($this->disk->directoryExists('foo/baz'));
        $this->assertTrue($this->disk->directoryMissing('foo/baz'));
    }

    public function testGet() {
        $this->disk->put('file.txt', 'Hello World');
        $this->assertSame('Hello World', $this->disk->get('file.txt'));
    }

    public function testGetFileNotFoundReturnsNullByDefault() {
        $this->assertNull($this->disk->get('file.txt'));
    }

    public function testGetFileNotFoundThrowsWhenTheDiskIsConfiguredToThrow() {
        $disk = CStorage::instance()->build(['driver' => 'local', 'root' => $this->tempDir, 'throw' => true]);
        $this->expectException(League\Flysystem\UnableToReadFile::class);
        $disk->get('file.txt');
    }

    public function testPut() {
        $this->disk->put('file.txt', 'Something inside');
        $this->assertStringEqualsFile($this->tempDir . '/file.txt', 'Something inside');
    }

    public function testPrepend() {
        file_put_contents($this->tempDir . '/file.txt', 'World');
        $this->disk->prepend('file.txt', 'Hello ');
        $this->assertStringEqualsFile($this->tempDir . '/file.txt', 'Hello ' . PHP_EOL . 'World');
    }

    public function testAppend() {
        file_put_contents($this->tempDir . '/file.txt', 'Hello ');
        $this->disk->append('file.txt', 'Moon');
        $this->assertStringEqualsFile($this->tempDir . '/file.txt', 'Hello ' . PHP_EOL . 'Moon');
    }

    public function testDelete() {
        file_put_contents($this->tempDir . '/file.txt', 'Hello World');
        $this->assertTrue($this->disk->delete('file.txt'));
        $this->assertFileDoesNotExist($this->tempDir . '/file.txt');
    }

    public function testDeleteReturnsTrueWhenFileNotFound() {
        $this->assertTrue($this->disk->delete('file.txt'));
    }

    public function testDeleteSeveralFiles() {
        file_put_contents($this->tempDir . '/a.txt', 'a');
        file_put_contents($this->tempDir . '/b.txt', 'b');
        $this->assertTrue($this->disk->delete(['a.txt', 'b.txt']));
        $this->assertFileDoesNotExist($this->tempDir . '/a.txt');
        $this->assertFileDoesNotExist($this->tempDir . '/b.txt');
    }

    public function testCopy() {
        $this->disk->put('foo/foo.txt', 'Hello World');
        $this->disk->copy('foo/foo.txt', 'foo/foo2.txt');
        $this->assertFileExists($this->tempDir . '/foo/foo.txt');
        $this->assertStringEqualsFile($this->tempDir . '/foo/foo.txt', 'Hello World');
        $this->assertFileExists($this->tempDir . '/foo/foo2.txt');
        $this->assertStringEqualsFile($this->tempDir . '/foo/foo2.txt', 'Hello World');
    }

    public function testMove() {
        $this->disk->put('foo/foo.txt', 'Hello World');
        $this->disk->move('foo/foo.txt', 'foo/foo2.txt');
        $this->assertFileDoesNotExist($this->tempDir . '/foo/foo.txt');
        $this->assertFileExists($this->tempDir . '/foo/foo2.txt');
        $this->assertStringEqualsFile($this->tempDir . '/foo/foo2.txt', 'Hello World');
    }

    public function testStream() {
        $this->disk->put('file.txt', $original = 'Hello World');
        $readStream = $this->disk->readStream('file.txt');
        $this->disk->writeStream('copy.txt', $readStream);
        $this->assertSame($original, $this->disk->get('copy.txt'));
    }

    public function testStreamToExistingFileOverwrites() {
        $this->disk->put('file.txt', 'Hello World');
        $this->disk->put('existing.txt', 'Dear Kate');
        $readStream = $this->disk->readStream('file.txt');
        $this->disk->writeStream('existing.txt', $readStream);
        $this->assertSame('Hello World', $this->disk->get('existing.txt'));
    }

    public function testReadStreamNonExistentFileReturnsNull() {
        $this->assertNull($this->disk->readStream('nonexistent.txt'));
    }

    public function testPutWithResource() {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'dari stream');
        rewind($resource);
        $this->disk->put('stream.txt', $resource);
        $this->assertSame('dari stream', $this->disk->get('stream.txt'));
    }

    public function testPutFileAs() {
        file_put_contents($filePath = $this->tempDir . '/foo.txt', 'uploaded file content');

        $storagePath = $this->disk->putFileAs('/', new CHTTP_File($filePath), 'new.txt');
        $this->assertSame('new.txt', $storagePath);
        $this->assertFileExists($filePath);
        $this->assertStringEqualsFile($this->tempDir . '/new.txt', 'uploaded file content');
    }

    public function testPutFileAsWithAbsoluteFilePath() {
        file_put_contents($filePath = $this->tempDir . '/foo.txt', 'normal file content');

        $storagePath = $this->disk->putFileAs('/', $filePath, 'new.txt');
        $this->assertSame('new.txt', $storagePath);
        $this->assertStringEqualsFile($this->tempDir . '/new.txt', 'normal file content');
    }

    public function testPutFile() {
        file_put_contents($filePath = $this->tempDir . '/foo.txt', 'uploaded file content');

        $storagePath = $this->disk->putFile('/', new CHTTP_File($filePath));
        $this->assertSame(44, strlen($storagePath)); // random 40 characters + ".txt"
        $this->assertFileExists($filePath);
        $this->assertStringEqualsFile($this->tempDir . '/' . $storagePath, 'uploaded file content');
    }

    public function testPutFileWithAbsoluteFilePath() {
        file_put_contents($filePath = $this->tempDir . '/foo.txt', 'normal file content');

        $storagePath = $this->disk->putFile('/', $filePath);
        $this->assertSame(44, strlen($storagePath));
        $this->assertStringEqualsFile($this->tempDir . '/' . $storagePath, 'normal file content');
    }

    public function testFilesAndDirectories() {
        $this->disk->put('foo/a.txt', 'a');
        $this->disk->put('foo/b.txt', 'b');
        $this->disk->put('foo/bar/c.txt', 'c');
        $this->disk->put('foo/bar/baz/d.txt', 'd');

        $this->assertEqualsCanonicalizing(['foo/a.txt', 'foo/b.txt'], $this->disk->files('foo'));
        $this->assertEqualsCanonicalizing(['foo/a.txt', 'foo/b.txt', 'foo/bar/c.txt', 'foo/bar/baz/d.txt'], $this->disk->allFiles('foo'));
        $this->assertEqualsCanonicalizing(['foo/bar'], $this->disk->directories('foo'));
        $this->assertEqualsCanonicalizing(['foo/bar', 'foo/bar/baz'], $this->disk->allDirectories('foo'));
    }

    public function testMakeAndDeleteDirectory() {
        $this->assertTrue($this->disk->makeDirectory('dir/sub'));
        $this->assertDirectoryExists($this->tempDir . '/dir/sub');
        $this->disk->put('dir/sub/file.txt', 'x');
        $this->assertTrue($this->disk->deleteDirectory('dir'));
        $this->assertDirectoryDoesNotExist($this->tempDir . '/dir');
    }

    public function testMetadata() {
        $this->disk->put('file.txt', 'Hello World');
        $this->assertSame(11, $this->disk->size('file.txt'));
        $this->assertSame('text/plain', $this->disk->mimeType('file.txt'));
        $this->assertEquals(filemtime($this->tempDir . '/file.txt'), $this->disk->lastModified('file.txt'));
    }

    public function testVisibility() {
        $this->disk->put('file.txt', 'Hello World', 'public');
        $this->assertSame('public', $this->disk->getVisibility('file.txt'));
        $this->disk->setVisibility('file.txt', 'private');
        $this->assertSame('private', $this->disk->getVisibility('file.txt'));
    }

    public function testPrefixesUrls() {
        $disk = CStorage::instance()->build(['driver' => 'local', 'root' => $this->tempDir, 'url' => 'https://cdn.example.com/files']);
        $disk->put('foo/bar.txt', 'x');
        $this->assertSame('https://cdn.example.com/files/foo/bar.txt', $disk->url('foo/bar.txt'));
    }

    public function testMacroable() {
        $this->disk->put('file.txt', 'Hello World');
        CStorage_Adapter::macro('getFileContent', function ($path) {
            return $this->get($path);
        });
        $this->assertSame('Hello World', $this->disk->getFileContent('file.txt'));
    }

    public function testAssertExistsAndMissing() {
        $this->disk->put('file.txt', 'Hello World');
        $this->disk->assertExists('file.txt');
        $this->disk->assertExists('file.txt', 'Hello World');
        $this->disk->assertMissing('tidak-ada.txt');
        $this->disk->assertDirectoryEmpty('kosong');
    }
}
