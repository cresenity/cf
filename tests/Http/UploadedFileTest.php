<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for CHTTP_UploadedFile (system/libraries/CHTTP/UploadedFile.php).
 *
 * store()/storeAs() delegate to CStorage::instance()->disk($disk)->putFileAs(...), which
 * needs a configured filesystem disk. The framework's default "local" disk (system/config
 * /storage.php) points at DOCROOT itself, which would be unsafe to write into from a test.
 * Instead we register a throwaway disk (backed by a scratch temp directory) directly into
 * the CStorage singleton's internal disk cache via reflection, so store()/storeAs() get
 * real, isolated disk I/O coverage without touching the real docroot or requiring any app
 * -level filesystem config.
 */
class UploadedFileTest extends TestCase {
    /**
     * @var string
     */
    protected $tmpUploadPath;

    /**
     * @var string
     */
    protected $scratchDiskRoot;

    protected function setUp(): void {
        parent::setUp();

        $this->tmpUploadPath = tempnam(sys_get_temp_dir(), 'chttp_upload_');
        file_put_contents($this->tmpUploadPath, 'hello world');

        $this->scratchDiskRoot = sys_get_temp_dir() . '/chttp_uploaded_file_test_' . cstr::random(8);
        mkdir($this->scratchDiskRoot, 0777, true);
    }

    protected function tearDown(): void {
        parent::tearDown();

        if (is_file($this->tmpUploadPath)) {
            unlink($this->tmpUploadPath);
        }

        $this->removeDirectory($this->scratchDiskRoot);
    }

    protected function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Registers a "scratch" disk backed by $this->scratchDiskRoot directly into
     * CStorage's internal disk cache, bypassing the config-driven resolve() path.
     *
     * @return string the disk name
     */
    protected function registerScratchDisk() {
        $diskName = 'scratch-test-disk';

        $adapter = CStorage::instance()->build([
            'driver' => 'local',
            'root' => $this->scratchDiskRoot,
        ]);

        $reflection = new ReflectionClass(CStorage::instance());
        $property = $reflection->getProperty('disks');
        $property->setAccessible(true);
        $disks = $property->getValue(CStorage::instance());
        $disks[$diskName] = $adapter;
        $property->setValue(CStorage::instance(), $disks);

        return $diskName;
    }

    /**
     * @return CHTTP_UploadedFile
     */
    protected function makeUploadedFile($test = true) {
        return new CHTTP_UploadedFile($this->tmpUploadPath, 'original.txt', 'text/plain', null, null, $test);
    }

    public function testConstructionFromRealTempFile() {
        $file = $this->makeUploadedFile();

        $this->assertInstanceOf(Symfony\Component\HttpFoundation\File\UploadedFile::class, $file);
        $this->assertSame('original.txt', $file->getClientOriginalName());
        $this->assertSame('text/plain', $file->getClientMimeType());
    }

    public function testIsValidInTestMode() {
        $file = $this->makeUploadedFile(true);

        $this->assertTrue($file->isValid());
    }

    public function testIsValidFalseWhenNotTestModeAndNotRealUpload() {
        // Outside test mode, isValid() additionally requires is_uploaded_file() to be true,
        // which a plain tempnam() file never satisfies.
        $file = $this->makeUploadedFile(false);

        $this->assertFalse($file->isValid());
    }

    public function testPathReturnsRealPath() {
        $file = $this->makeUploadedFile();

        $this->assertSame(realpath($this->tmpUploadPath), $file->path());
    }

    public function testExtensionGuessesFromFileContent() {
        $file = $this->makeUploadedFile();

        // guessExtension() inspects the file's actual content/mime, not the client name.
        $this->assertSame($file->guessExtension(), $file->extension());
    }

    public function testClientExtensionUsesClientMimeType() {
        $file = $this->makeUploadedFile();

        $this->assertSame($file->guessClientExtension(), $file->clientExtension());
    }

    public function testHashNameGeneratesRandomNameWithExtensionAndIsStable() {
        $file = $this->makeUploadedFile();

        $name1 = $file->hashName();
        $name2 = $file->hashName();

        // Same instance should reuse the cached hash name.
        $this->assertSame($name1, $name2);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{40}(\.[a-zA-Z0-9]+)?$/', $name1);
    }

    public function testHashNameWithPathPrefix() {
        $file = $this->makeUploadedFile();

        $name = $file->hashName('uploads');

        $this->assertStringStartsWith('uploads/', $name);
    }

    public function testGetThrowsWhenNotValid() {
        $file = $this->makeUploadedFile(false);

        $this->expectException(CStorage_Exception_FileNotFoundException::class);

        $file->get();
    }

    public function testGetReturnsFileContentsWhenValid() {
        $file = $this->makeUploadedFile(true);

        $this->assertSame('hello world', $file->get());
    }

    public function testCreateFromBaseWrapsSymfonyUploadedFile() {
        $base = new Symfony\Component\HttpFoundation\File\UploadedFile(
            $this->tmpUploadPath,
            'from-base.txt',
            'text/plain',
            null,
            true
        );

        $wrapped = CHTTP_UploadedFile::createFromBase($base);

        $this->assertInstanceOf(CHTTP_UploadedFile::class, $wrapped);
        $this->assertSame('from-base.txt', $wrapped->getClientOriginalName());
    }

    public function testCreateFromBaseReturnsSameInstanceIfAlreadyCHttpUploadedFile() {
        $file = $this->makeUploadedFile();

        $result = CHTTP_UploadedFile::createFromBase($file);

        $this->assertSame($file, $result);
    }

    public function testFakeReturnsFileFactory() {
        $this->assertInstanceOf(CHTTP_Testing_FileFactory::class, CHTTP_UploadedFile::fake());
    }

    public function testStoreWritesFileToDiskAndReturnsGeneratedPath() {
        $diskName = $this->registerScratchDisk();
        $file = $this->makeUploadedFile();

        $result = $file->store('avatars', $diskName);

        $this->assertNotFalse($result);
        $this->assertStringStartsWith('avatars/', $result);
        $this->assertFileExists($this->scratchDiskRoot . '/' . $result);
        $this->assertSame('hello world', file_get_contents($this->scratchDiskRoot . '/' . $result));
    }

    public function testStoreAsWritesFileWithGivenName() {
        $diskName = $this->registerScratchDisk();
        $file = $this->makeUploadedFile();

        $result = $file->storeAs('docs', 'custom-name.txt', $diskName);

        $this->assertSame('docs/custom-name.txt', $result);
        $this->assertFileExists($this->scratchDiskRoot . '/docs/custom-name.txt');
    }

    public function testStoreAsAcceptsDiskInsideOptionsArray() {
        $diskName = $this->registerScratchDisk();
        $file = $this->makeUploadedFile();

        $result = $file->storeAs('docs', 'via-options.txt', ['disk' => $diskName]);

        $this->assertSame('docs/via-options.txt', $result);
        $this->assertFileExists($this->scratchDiskRoot . '/docs/via-options.txt');
    }

    public function testStorePubliclyAsSetsPublicVisibilityOption() {
        $diskName = $this->registerScratchDisk();
        $file = $this->makeUploadedFile();

        $result = $file->storePubliclyAs('public-docs', 'public-name.txt', $diskName);

        $this->assertSame('public-docs/public-name.txt', $result);
        $this->assertFileExists($this->scratchDiskRoot . '/public-docs/public-name.txt');
    }

    public function testStoreWithoutAPathWritesAtTheDiskRoot() {
        $diskName = $this->registerScratchDisk();

        $result = $this->makeUploadedFile()->store('', $diskName);

        $this->assertStringNotContainsString('/', $result);
        $this->assertFileExists($this->scratchDiskRoot . '/' . $result);
    }

    public function testStoreAsAcceptsTheNameAsTheOnlyArgument() {
        $diskName = $this->registerScratchDisk();

        $result = $this->makeUploadedFile()->storeAs('solo.txt', ['disk' => $diskName]);

        $this->assertSame('solo.txt', $result);
        $this->assertFileExists($this->scratchDiskRoot . '/solo.txt');
    }

    public function testStorePubliclyAsAcceptsTheNameAsTheOnlyArgument() {
        $diskName = $this->registerScratchDisk();

        $result = $this->makeUploadedFile()->storePubliclyAs('publik.txt', ['disk' => $diskName]);

        $this->assertSame('publik.txt', $result);
        $this->assertFileExists($this->scratchDiskRoot . '/publik.txt');
    }

    public function testDimensionsReadsAnImageAndIsFalseForOtherFiles() {
        $png = sys_get_temp_dir() . '/chttp_dimensions_' . cstr::random(8) . '.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAADCAYAAAC56t6BAAAAEklEQVR4nGNgYGD4z8DAwAADAAMEAQExXm3KAAAAAElFTkSuQmCC'));

        try {
            $image = new CHTTP_UploadedFile($png, 'gambar.png', 'image/png', null, null, true);
            $dimensions = $image->dimensions();
            $this->assertSame(2, $dimensions[0]);
            $this->assertSame(3, $dimensions[1]);
        } finally {
            unlink($png);
        }

        $this->assertFalse($this->makeUploadedFile()->dimensions());
    }
}
