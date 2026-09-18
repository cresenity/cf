<?php
use PHPUnit\Framework\TestCase;

/**
 * Port CacheTaggedCacheTest hulu ke CCache_TaggedCache di atas ArrayDriver.
 */
class CacheTaggedCacheTest extends TestCase {
    /**
     * @return CCache_Repository
     */
    protected function repo() {
        return new CCache_Repository(new CCache_Driver_ArrayDriver());
    }

    public function testCacheCanBeSavedWithMultipleTags() {
        $store = $this->repo();
        $tags = ['bop', 'zap'];
        $store->tags($tags)->put('foo', 'bar', 10);
        $this->assertSame('bar', $store->tags($tags)->get('foo'));
    }

    public function testCacheCanBeSetWithDatetimeArgument() {
        $store = $this->repo();
        $tags = ['bop', 'zap'];
        $duration = new DateTime();
        $duration->add(new DateInterval('PT10M'));
        $store->tags($tags)->put('foo', 'bar', $duration);
        $this->assertSame('bar', $store->tags($tags)->get('foo'));
    }

    public function testCacheSavedWithMultipleTagsCanBeFlushed() {
        $store = $this->repo();
        $tags1 = ['bop', 'zap'];
        $store->tags($tags1)->put('foo', 'bar', 10);
        $tags2 = ['bam', 'pow'];
        $store->tags($tags2)->put('foo', 'bar', 10);
        $store->tags('zap')->flush();
        $this->assertNull($store->tags($tags1)->get('foo'), 'flush satu tag mengosongkan semua kombinasi yang memuat tag itu');
        $this->assertSame('bar', $store->tags($tags2)->get('foo'), 'kombinasi tag lain tidak ikut terhapus');
    }

    public function testTagsWithStringArgument() {
        $store = $this->repo();
        $store->tags('bop')->put('foo', 'bar', 10);
        $this->assertSame('bar', $store->tags('bop')->get('foo'));
    }

    public function testTagsWithVariadicArguments() {
        $store = $this->repo();
        $store->tags('bop', 'zap')->put('foo', 'bar', 10);
        $this->assertSame('bar', $store->tags(['bop', 'zap'])->get('foo'));
        $this->assertNull($store->tags(['bop'])->get('foo'), 'set tag berbeda = namespace berbeda');
    }

    public function testTagsDoNotLeakIntoUntaggedKeys() {
        $store = $this->repo();
        $store->put('foo', 'plain', 10);
        $store->tags('bop')->put('foo', 'tagged', 10);
        $this->assertSame('plain', $store->get('foo'));
        $this->assertSame('tagged', $store->tags('bop')->get('foo'));
    }

    public function testWithIncrement() {
        $store = $this->repo();
        $store->tags('bop')->put('foo', 5, 10);
        $store->tags('bop')->increment('foo');
        $store->tags('bop')->increment('foo', 4);
        $this->assertEquals(10, $store->tags('bop')->get('foo'));
        $this->assertNull($store->get('foo'), 'increment pada tagged cache tidak menyentuh kunci polos');
    }

    public function testWithDecrement() {
        $store = $this->repo();
        $store->tags('bop')->put('foo', 50, 10);
        $store->tags('bop')->decrement('foo');
        $store->tags('bop')->decrement('foo', 9);
        $this->assertEquals(40, $store->tags('bop')->get('foo'));
    }

    public function testIncrementReturnsTheNewValue() {
        $store = $this->repo();
        $store->tags('bop')->put('foo', 5, 10);
        $this->assertEquals(6, $store->tags('bop')->increment('foo'));
        $this->assertEquals(4, $store->tags('bop')->decrement('foo', 2));
    }

    public function testMany() {
        $store = $this->repo();
        $store->tags('bop')->putMany(['foo' => 'bar', 'baz' => 'qux'], 10);
        $this->assertSame(['foo' => 'bar', 'baz' => 'qux', 'nope' => null], $store->tags('bop')->many(['foo', 'baz', 'nope']));
    }

    public function testManyWithDefaultValues() {
        $store = $this->repo();
        $store->tags('bop')->put('foo', 'bar', 10);
        $this->assertSame(['foo' => 'bar', 'baz' => 'dflt'], $store->tags('bop')->many(['foo', 'baz' => 'dflt']));
    }

    public function testGetMultiple() {
        $store = $this->repo();
        $store->tags('bop')->putMany(['foo' => 'bar', 'baz' => 'qux'], 10);
        $this->assertSame(['foo' => 'bar', 'baz' => 'qux', 'nope' => 'dflt'], $store->tags('bop')->getMultiple(['foo', 'baz', 'nope'], 'dflt'));
    }

    public function testTagsWithIncrementCanBeFlushed() {
        $store = $this->repo();
        $store->tags('bop')->increment('foo', 5);
        $this->assertEquals(5, $store->tags('bop')->get('foo'));
        $store->tags('bop')->flush();
        $this->assertNull($store->tags('bop')->get('foo'));
    }

    public function testTagsCacheForever() {
        $store = $this->repo();
        $store->tags(['bop', 'zap'])->forever('foo', 'bar');
        $this->assertSame('bar', $store->tags(['bop', 'zap'])->get('foo'));
        $store->tags(['bop', 'zap'])->putMany(['a' => 1, 'b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $store->tags(['bop', 'zap'])->many(['a', 'b']), 'putMany tanpa ttl = forever');
    }

    public function testTaggedItemKeyUsesTagNamespace() {
        $store = $this->repo();
        $tagged = $store->tags(['bop', 'zap']);
        $namespace = $tagged->getTags()->getNamespace();
        $this->assertSame(sha1($namespace) . ':foo', $tagged->taggedItemKey('foo'));
        $this->assertSame(['bop', 'zap'], $tagged->getTags()->getNames());
    }

    public function testTagsOnStoreWithoutTagSupportThrows() {
        $store = new CCache_Repository(new CCache_Driver_NullDriver([]));
        $this->assertFalse($store->supportsTags());
        $this->expectException(BadMethodCallException::class);
        $store->tags('bop');
    }

    public function testRememberOnTaggedCacheStoresUnderTheTag() {
        $store = $this->repo();
        $calls = 0;
        $value = $store->tags('bop')->remember('foo', 10, function () use (&$calls) {
            $calls++;

            return 'computed';
        });
        $this->assertSame('computed', $value);
        $this->assertSame('computed', $store->tags('bop')->remember('foo', 10, function () use (&$calls) {
            $calls++;

            return 'other';
        }));
        $this->assertSame(1, $calls);
        $this->assertNull($store->get('foo'));
    }
}
