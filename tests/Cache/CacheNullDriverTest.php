<?php
use PHPUnit\Framework\TestCase;

class CacheNullDriverTest extends TestCase {
    public function testItemsCanNotBeCached() {
        $store = new CCache_Driver_NullDriver([]);
        $this->assertFalse($store->put('foo', 'bar', 10));
        $this->assertNull($store->get('foo'));
        $this->assertFalse($store->forever('foo', 'bar'));
        $this->assertNull($store->get('foo'));
    }

    public function testGetMultipleReturnsMultipleNulls() {
        $store = new CCache_Driver_NullDriver([]);
        $this->assertSame(['foo' => null, 'bar' => null], $store->many(['foo', 'bar']));
    }

    public function testIncrementAndDecrementReturnFalse() {
        $store = new CCache_Driver_NullDriver([]);
        $this->assertFalse($store->increment('foo'));
        $this->assertFalse($store->decrement('foo'));
    }

    public function testForgetAndFlushSucceed() {
        $store = new CCache_Driver_NullDriver([]);
        $this->assertTrue($store->forget('foo'));
        $this->assertTrue($store->flush());
        $this->assertSame('', $store->getPrefix());
    }

    public function testRepositoryOverNullDriverAlwaysMisses() {
        $repo = new CCache_Repository(new CCache_Driver_NullDriver([]));
        $repo->put('foo', 'bar', 10);
        $this->assertFalse($repo->has('foo'));
        $this->assertSame('dflt', $repo->get('foo', 'dflt'));
        $calls = 0;
        $repo->remember('foo', 10, function () use (&$calls) {
            return ++$calls;
        });
        $repo->remember('foo', 10, function () use (&$calls) {
            return ++$calls;
        });
        $this->assertSame(2, $calls, 'remember selalu menghitung ulang di null driver');
    }

    public function testRepositoryDefaultDriverIsNull() {
        $repo = new CCache_Repository();
        $this->assertInstanceOf(CCache_Driver_NullDriver::class, $repo->getDriver());
    }
}
