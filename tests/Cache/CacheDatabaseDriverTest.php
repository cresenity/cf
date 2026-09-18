<?php
use PHPUnit\Framework\TestCase;

/**
 * Port CacheDatabaseStoreTest hulu: CCache_Driver_DatabaseDriver di atas SQLite in-memory,
 * termasuk DatabaseLock yang memakai tabel cache_lock.
 */
class CacheDatabaseDriverTest extends TestCase {
    const CONNECTION = 'uji_cache';

    /**
     * @var CDatabase_Connection
     */
    protected $connection;

    protected function setUp(): void {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('butuh ekstensi pdo_sqlite');
        }
        $manager = CDatabase_Manager::instance();
        $manager->purge(static::CONNECTION);
        $manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], static::CONNECTION);
        $this->connection = $manager->connection(static::CONNECTION);
        $this->connection->statement('create table cache (key varchar(191) primary key, value text, expiration integer)');
        $this->connection->statement('create table cache_lock (key varchar(191) primary key, owner varchar(191), expiration integer)');
        CCarbon::setTestNow(null);
    }

    protected function tearDown(): void {
        CCarbon::setTestNow(null);
        CDatabase_Manager::instance()->purge(static::CONNECTION);
    }

    /**
     * @param string $prefix
     *
     * @return CCache_Driver_DatabaseDriver
     */
    protected function store($prefix = 'prefix') {
        $store = new CCache_Driver_DatabaseDriver($this->connection, 'cache', $prefix, 'cache_lock', [0, 100]);

        return $store->setLockConnection($this->connection);
    }

    /**
     * @param string $key
     *
     * @return null|object
     */
    protected function row($key) {
        $row = $this->connection->table('cache')->where('key', $key)->first();

        return $row === null ? null : (object) $row;
    }

    public function testNullIsReturnedWhenItemNotFound() {
        $this->assertNull($this->store()->get('foo'));
    }

    public function testNullIsReturnedAndItemDeletedWhenItemIsExpired() {
        $store = $this->store();
        $this->connection->table('cache')->insert(['key' => 'prefixfoo', 'value' => serialize('bar'), 'expiration' => time() - 10]);
        $this->assertNull($store->get('foo'));
        $this->assertNull($this->row('prefixfoo'), 'baris kedaluwarsa dihapus saat dibaca');
    }

    public function testValueIsReturnedWhenItemIsValid() {
        $store = $this->store();
        $this->connection->table('cache')->insert(['key' => 'prefixfoo', 'value' => serialize('bar'), 'expiration' => time() + 100]);
        $this->assertSame('bar', $store->get('foo'));
    }

    public function testValueIsUpserted() {
        $store = $this->store();
        $this->assertTrue($store->put('foo', 'bar', 60));
        $this->assertSame('bar', $store->get('foo'));
        $this->assertTrue($store->put('foo', 'baz', 60), 'kunci yang sudah ada diperbarui, bukan gagal karena PK duplikat');
        $this->assertSame('baz', $store->get('foo'));
        $this->assertSame(1, $this->connection->table('cache')->count());
    }

    public function testPutStoresPrefixedKeyAndExpiration() {
        CCarbon::setTestNow(CCarbon::create(2026, 1, 1, 0, 0, 0));
        $this->store()->put('foo', ['a' => 1], 60);
        $row = $this->row('prefixfoo');
        $this->assertNotNull($row);
        $this->assertSame(['a' => 1], unserialize($row->value));
        $this->assertEquals(CCarbon::now()->getTimestamp() + 60, $row->expiration);
    }

    public function testForeverStoresWithReallyLongTime() {
        CCarbon::setTestNow(CCarbon::create(2026, 1, 1, 0, 0, 0));
        $this->assertTrue($this->store()->forever('foo', 'bar'));
        $this->assertEquals(CCarbon::now()->getTimestamp() + 315360000, $this->row('prefixfoo')->expiration, 'forever = 10 tahun');
    }

    public function testAddOnlyWritesWhenMissingOrExpired() {
        $store = $this->store();
        $this->assertTrue($store->add('foo', 'bar', 60));
        $this->assertFalse($store->add('foo', 'baz', 60), 'add pada kunci hidup gagal');
        $this->assertSame('bar', $store->get('foo'));

        $this->connection->table('cache')->where('key', 'prefixfoo')->update(['expiration' => time() - 10]);
        $this->assertTrue($store->add('foo', 'qux', 60), 'add mengambil alih kunci yang sudah kedaluwarsa');
        $this->assertSame('qux', $store->get('foo'));
    }

    public function testItemsMayBeRemovedFromCache() {
        $store = $this->store();
        $store->put('foo', 'bar', 60);
        $this->assertTrue($store->forget('foo'));
        $this->assertNull($store->get('foo'));
    }

    public function testItemsMayBeFlushedFromCache() {
        $store = $this->store();
        $store->put('foo', 'bar', 60);
        $store->put('baz', 'qux', 60);
        $this->assertTrue($store->flush());
        $this->assertSame(0, $this->connection->table('cache')->count());
    }

    public function testIncrementReturnsCorrectValues() {
        $store = $this->store();
        $this->assertFalse($store->increment('foo'), 'kunci tak ada → false');
        $store->put('foo', 'bar', 60);
        $this->assertFalse($store->increment('foo'), 'nilai bukan angka → false');
        $store->put('foo', 2, 60);
        $this->assertSame(3, $store->increment('foo'));
        $this->assertSame(6, $store->increment('foo', 3));
        $this->assertEquals(6, $store->get('foo'));
    }

    public function testDecrementReturnsCorrectValues() {
        $store = $this->store();
        $this->assertFalse($store->decrement('foo'));
        $store->put('foo', 3, 60);
        $this->assertSame(2, $store->decrement('foo'));
        $this->assertSame(-1, $store->decrement('foo', 3));
    }

    public function testIncrementKeepsExpiration() {
        $store = $this->store();
        $store->put('foo', 1, 60);
        $before = $this->row('prefixfoo')->expiration;
        $store->increment('foo');
        $this->assertEquals($before, $this->row('prefixfoo')->expiration);
    }

    public function testManyAndPutMany() {
        $store = $this->store();
        $store->putMany(['foo' => 'bar', 'baz' => 'qux'], 60);
        $this->assertSame(['foo' => 'bar', 'baz' => 'qux', 'nope' => null], $store->many(['foo', 'baz', 'nope']));
    }

    public function testPrefixIsolatesStores() {
        $a = $this->store('a:');
        $b = $this->store('b:');
        $a->put('foo', 'from-a', 60);
        $this->assertNull($b->get('foo'));
        $this->assertSame('a:', $a->getPrefix());
        $this->assertSame($this->connection, $a->getConnection());
    }

    public function testLockCanBeAcquiredAndReleased() {
        $store = $this->store();
        $lock = $store->lock('job', 10);
        $this->assertInstanceOf(CCache_Lock_DatabaseLock::class, $lock);
        $this->assertTrue($lock->acquire());
        $this->assertSame(1, $this->connection->table('cache_lock')->count());
        $this->assertTrue($lock->release());
        $this->assertSame(0, $this->connection->table('cache_lock')->count());
    }

    public function testLockCannotBeAcquiredTwiceByDifferentOwners() {
        $store = $this->store();
        $first = $store->lock('job', 10, 'owner-1');
        $second = $store->lock('job', 10, 'owner-2');
        $this->assertTrue($first->acquire());
        $this->assertFalse($second->acquire(), 'pemilik lain tidak bisa merebut lock yang masih hidup');
        $this->assertTrue($first->acquire(), 'pemilik yang sama boleh memperbarui lock-nya');
        $this->assertFalse($second->release());
        $this->assertTrue($first->release());
        $this->assertTrue($second->acquire(), 'setelah dilepas pemilik lain bisa mengambil');
    }

    public function testExpiredLockCanBeTakenOver() {
        $store = $this->store();
        $first = $store->lock('job', 10, 'owner-1');
        $this->assertTrue($first->acquire());
        // nama lock ikut memakai prefix store
        $this->connection->table('cache_lock')->where('key', 'prefixjob')->update(['expiration' => time() - 5]);
        $this->assertTrue($store->lock('job', 10, 'owner-2')->acquire(), 'lock kedaluwarsa boleh diambil pemilik lain');
        $this->assertSame('owner-2', ((object) $this->connection->table('cache_lock')->where('key', 'prefixjob')->first())->owner);
    }

    public function testRestoreLockReleasesByOwner() {
        $store = $this->store();
        $lock = $store->lock('job', 10, 'owner-1');
        $lock->acquire();
        $this->assertTrue($store->restoreLock('job', 'owner-1')->release());
        $this->assertSame(0, $this->connection->table('cache_lock')->count());
    }

    public function testForceReleaseIgnoresOwner() {
        $store = $this->store();
        $store->lock('job', 10, 'owner-1')->acquire();
        $store->lock('job', 10, 'owner-2')->forceRelease();
        $this->assertSame(0, $this->connection->table('cache_lock')->count());
    }

    public function testRepositoryOverDatabaseDriver() {
        $repo = new CCache_Repository($this->store());
        $calls = 0;
        $value = $repo->remember('foo', 60, function () use (&$calls) {
            $calls++;

            return 'computed';
        });
        $this->assertSame('computed', $value);
        $this->assertSame('computed', $repo->remember('foo', 60, function () use (&$calls) {
            $calls++;
        }));
        $this->assertSame(1, $calls);
        $this->assertSame('computed', $repo->pull('foo'));
        $this->assertFalse($repo->has('foo'));
    }
}
