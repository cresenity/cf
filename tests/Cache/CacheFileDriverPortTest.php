<?php
use PHPUnit\Framework\TestCase;

/**
 * Port CacheFileStoreTest hulu ke CCache_Driver_FileDriver (engine Temp): payload berkas
 * `<10 digit expiry><serialize>`, lokasi per app di temp/cache/<appCode>/<directory>, flush
 * yang juga membersihkan lokasi lama bersama, dan lock berbasis berkas.
 */
class CacheFileDriverPortTest extends TestCase {
    const DIRECTORY = 'uji-file-port';

    /**
     * @return CCache_Driver_FileDriver
     */
    protected function store() {
        return new CCache_Driver_FileDriver(['options' => ['directory' => static::DIRECTORY]]);
    }

    protected function setUp(): void {
        CCarbon::setTestNow(null);
    }

    protected function tearDown(): void {
        CCarbon::setTestNow(null);
        $this->store()->flush();
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function pathFor($key) {
        $hash = sha1($key);
        $parts = array_slice(str_split($hash, 2), 0, 2);

        return CTemporary::createFile('cache/' . static::DIRECTORY . '/' . implode('/', $parts) . '/' . $hash . '.cache')->getPath();
    }

    public function testNullIsReturnedIfFileDoesntExist() {
        $this->assertNull($this->store()->get('foo'));
    }

    public function testPutCreatesMissingDirectoriesUnderTheAppFolder() {
        $store = $this->store();
        $this->assertTrue($store->put('foo', 'bar', 10));
        $path = $this->pathFor('foo');
        $this->assertFileExists($path);
        $expected = rtrim(DOCROOT, '/') . '/temp/cache/' . CF::appCode() . '/' . static::DIRECTORY . '/';
        $this->assertStringStartsWith($expected, $path, 'berkas cache per app: temp/cache/<appCode>/<directory>/');
    }

    public function testPayloadIsTenDigitExpiryFollowedBySerializedValue() {
        CCarbon::setTestNow(CCarbon::create(2026, 1, 1, 0, 0, 0));
        $store = $this->store();
        $store->put('foo', 'bar', 10);
        $contents = file_get_contents($this->pathFor('foo'));
        $this->assertSame((string) (CCarbon::now()->getTimestamp() + 10), substr($contents, 0, 10));
        $this->assertSame('bar', unserialize(substr($contents, 10)));
    }

    public function testPutWillConsiderZeroAsEternalTime() {
        $store = $this->store();
        $store->put('foo', 'bar', 0);
        $this->assertSame('9999999999', substr(file_get_contents($this->pathFor('foo')), 0, 10));
    }

    public function testPutWillConsiderBigValuesAsEternalTime() {
        $store = $this->store();
        $store->put('foo', 'bar', PHP_INT_MAX);
        $this->assertSame('9999999999', substr(file_get_contents($this->pathFor('foo')), 0, 10));
    }

    public function testForeversAreStoredWithHighTimestamp() {
        $store = $this->store();
        $store->forever('foo', 'bar');
        $this->assertSame('9999999999', substr(file_get_contents($this->pathFor('foo')), 0, 10));
        $this->assertSame('bar', $store->get('foo'));
    }

    public function testExpiredItemsReturnNullAndGetDeleted() {
        $store = $this->store();
        $store->put('foo', 'bar', 10);
        $path = $this->pathFor('foo');
        CCarbon::setTestNow(CCarbon::now()->addSeconds(11));
        $this->assertNull($store->get('foo'));
        $this->assertFileDoesNotExist($path, 'berkas kedaluwarsa dihapus saat dibaca');
    }

    public function testValidItemReturnsContents() {
        $store = $this->store();
        $store->put('foo', ['nested' => [1, 2, 3]], 10);
        CCarbon::setTestNow(CCarbon::now()->addSeconds(9));
        $this->assertSame(['nested' => [1, 2, 3]], $store->get('foo'));
    }

    public function testForeversAreNotRemovedOnIncrement() {
        $store = $this->store();
        $store->forever('foo', 5);
        $this->assertSame(6, $store->increment('foo'));
        $this->assertSame('9999999999', substr(file_get_contents($this->pathFor('foo')), 0, 10));
    }

    public function testIncrementDoesNotExtendCacheLife() {
        $store = $this->store();
        $store->put('foo', 5, 50);
        $expected = substr(file_get_contents($this->pathFor('foo')), 0, 10);
        $store->increment('foo');
        $this->assertSame($expected, substr(file_get_contents($this->pathFor('foo')), 0, 10));
    }

    public function testIncrementExpiredKeysStartsFromZero() {
        $store = $this->store();
        $store->put('foo', 5, 10);
        CCarbon::setTestNow(CCarbon::now()->addSeconds(11));
        $this->assertSame(1, $store->increment('foo'));
    }

    public function testIncrementNonExistentKeys() {
        $store = $this->store();
        $this->assertSame(1, $store->increment('foo'));
        $this->assertSame(5, $store->increment('foo', 4));
        $this->assertSame(3, $store->decrement('foo', 2));
    }

    public function testIncrementNonNumericValuesTreatsThemAsZero() {
        $store = $this->store();
        $store->put('foo', 'bar', 10);
        $this->assertSame(1, $store->increment('foo'));
    }

    public function testAddOnlyWritesMissingOrExpiredKeys() {
        $store = $this->store();
        $this->assertTrue($store->add('foo', 'bar', 10));
        $this->assertFalse($store->add('foo', 'baz', 10));
        $this->assertSame('bar', $store->get('foo'));
        CCarbon::setTestNow(CCarbon::now()->addSeconds(11));
        $this->assertTrue($store->add('foo', 'qux', 10), 'kunci kedaluwarsa boleh diambil alih add');
        $this->assertSame('qux', $store->get('foo'));
    }

    public function testRemoveDeletesFile() {
        $store = $this->store();
        $store->put('foo', 'bar', 10);
        $this->assertTrue($store->forget('foo'));
        $this->assertFileDoesNotExist($this->pathFor('foo'));
    }

    public function testRemoveOfMissingKeyReturnsFalse() {
        $this->assertFalse($this->store()->forget('missing'));
    }

    public function testFlushCleansAppDirectoryAndLegacySharedDirectory() {
        $store = $this->store();
        $store->put('foo', 'bar', 10);
        // pathFor() lewat CTemporary::createFile yang membuat ulang direktori - hitung sebelum flush
        $path = $this->pathFor('foo');
        $legacy = rtrim(DOCROOT, '/') . '/temp/cache/' . static::DIRECTORY . '/legacy.cache';
        @mkdir(dirname($legacy), 0777, true);
        file_put_contents($legacy, 'x');

        $this->assertTrue($store->flush());
        $this->assertFileDoesNotExist($path);
        $this->assertDirectoryDoesNotExist(rtrim(DOCROOT, '/') . '/temp/cache/' . CF::appCode() . '/' . static::DIRECTORY);
        $this->assertDirectoryDoesNotExist(dirname($legacy), 'lokasi lama bersama ikut dibersihkan');
    }

    public function testFlushIgnoreNonExistingDirectory() {
        $store = $this->store();
        $store->flush();
        $this->assertFalse($store->flush(), 'tidak ada yang dihapus → false, bukan exception');
        $this->assertNull($store->get('foo'));
    }

    public function testManyAndPutMany() {
        $store = $this->store();
        $store->putMany(['foo' => 'bar', 'baz' => 'qux'], 10);
        $this->assertSame(['foo' => 'bar', 'baz' => 'qux', 'nope' => null], $store->many(['foo', 'baz', 'nope']));
        $this->assertSame('', $store->getPrefix());
    }

    public function testLocksAreFileBacked() {
        $store = $this->store();
        $lock = $store->lock('job', 10, 'owner-1');
        $this->assertInstanceOf(CCache_LockInterface::class, $lock);
        $this->assertTrue($lock->acquire());
        $this->assertFalse($store->lock('job', 10, 'owner-2')->acquire());
        $this->assertFalse($store->lock('job', 10, 'owner-2')->release(), 'pemilik lain tidak bisa melepas');
        $this->assertTrue($store->restoreLock('job', 'owner-1')->release());
        $this->assertTrue($store->lock('job', 10, 'owner-2')->acquire());
    }

    public function testLockExpires() {
        $store = $this->store();
        $store->lock('job', 5, 'owner-1')->acquire();
        CCarbon::setTestNow(CCarbon::now()->addSeconds(6));
        $this->assertTrue($store->lock('job', 5, 'owner-2')->acquire());
    }
}
