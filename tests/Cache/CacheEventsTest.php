<?php
use PHPUnit\Framework\TestCase;

/**
 * Port CacheEventsTest hulu: urutan event CacheHit/CacheMissed/KeyWritten/KeyForgotten yang
 * dipancarkan CCache_Repository dan CCache_TaggedCache (event tagged membawa nama tag).
 */
class CacheEventsTest extends TestCase {
    /**
     * @var array
     */
    protected $events = [];

    protected function setUp(): void {
        $this->events = [];
        foreach ([CCache_Event_CacheHit::class, CCache_Event_CacheMissed::class, CCache_Event_KeyWritten::class, CCache_Event_KeyForgotten::class] as $class) {
            CEvent::dispatcher()->listen($class, function ($event) {
                $this->events[] = $event;
            });
        }
    }

    protected function tearDown(): void {
        foreach ([CCache_Event_CacheHit::class, CCache_Event_CacheMissed::class, CCache_Event_KeyWritten::class, CCache_Event_KeyForgotten::class] as $class) {
            CEvent::dispatcher()->forget($class);
        }
    }

    /**
     * @return CCache_Repository
     */
    protected function repo() {
        return new CCache_Repository(new CCache_Driver_ArrayDriver());
    }

    /**
     * @return array
     */
    protected function fired() {
        return array_map(function ($event) {
            return [get_class($event), $event->key];
        }, $this->events);
    }

    public function testHasTriggersEvents() {
        $repo = $this->repo();
        $repo->has('foo');
        $repo->getDriver()->put('foo', 'bar', 10);
        $repo->has('foo');
        $this->assertSame([[CCache_Event_CacheMissed::class, 'foo'], [CCache_Event_CacheHit::class, 'foo']], $this->fired());
    }

    public function testGetTriggersEvents() {
        $repo = $this->repo();
        $this->assertNull($repo->get('foo'));
        $repo->getDriver()->put('foo', 'bar', 10);
        $this->assertSame('bar', $repo->get('foo'));
        $this->assertSame([[CCache_Event_CacheMissed::class, 'foo'], [CCache_Event_CacheHit::class, 'foo']], $this->fired());
        $this->assertSame('bar', $this->events[1]->value);
    }

    public function testManyTriggersOneEventPerKey() {
        $repo = $this->repo();
        $repo->getDriver()->put('foo', 'bar', 10);
        $repo->many(['foo', 'baz']);
        $this->assertSame([[CCache_Event_CacheHit::class, 'foo'], [CCache_Event_CacheMissed::class, 'baz']], $this->fired());
    }

    public function testPullTriggersEvents() {
        $repo = $this->repo();
        $repo->getDriver()->put('foo', 'bar', 10);
        $this->assertSame('bar', $repo->pull('foo'));
        $this->assertSame([[CCache_Event_CacheHit::class, 'foo'], [CCache_Event_KeyForgotten::class, 'foo']], $this->fired());
    }

    public function testPullOfMissingKeyOnlyReportsTheMiss() {
        $repo = $this->repo();
        $repo->pull('foo');
        $this->assertSame([[CCache_Event_CacheMissed::class, 'foo']], $this->fired(), 'forget yang tidak menghapus apa pun tidak memancarkan KeyForgotten');
    }

    public function testPutTriggersEvents() {
        $repo = $this->repo();
        $repo->put('foo', 'bar', 99);
        $this->assertSame([[CCache_Event_KeyWritten::class, 'foo']], $this->fired());
        $this->assertSame(99, $this->events[0]->seconds);
    }

    public function testPutManyTriggersOneEventPerKey() {
        $repo = $this->repo();
        $repo->putMany(['foo' => 1, 'bar' => 2], 99);
        $this->assertSame([[CCache_Event_KeyWritten::class, 'foo'], [CCache_Event_KeyWritten::class, 'bar']], $this->fired());
    }

    public function testAddTriggersEvents() {
        $repo = $this->repo();
        $this->assertTrue($repo->add('foo', 'bar'));
        $this->assertSame([[CCache_Event_CacheMissed::class, 'foo'], [CCache_Event_KeyWritten::class, 'foo']], $this->fired());
        $this->events = [];
        $this->assertFalse($repo->add('foo', 'baz'));
        $this->assertSame([[CCache_Event_CacheHit::class, 'foo']], $this->fired());
    }

    public function testForeverTriggersEvents() {
        $repo = $this->repo();
        $repo->forever('foo', 'bar');
        $this->assertSame([[CCache_Event_KeyWritten::class, 'foo']], $this->fired());
        $this->assertNull($this->events[0]->seconds, 'forever tidak punya ttl');
    }

    public function testRememberTriggersEvents() {
        $repo = $this->repo();
        $repo->remember('foo', 99, function () {
            return 'bar';
        });
        $this->assertSame([[CCache_Event_CacheMissed::class, 'foo'], [CCache_Event_KeyWritten::class, 'foo']], $this->fired());
        $this->events = [];
        $repo->remember('foo', 99, function () {
            return 'other';
        });
        $this->assertSame([[CCache_Event_CacheHit::class, 'foo']], $this->fired());
    }

    public function testRememberForeverTriggersEvents() {
        $repo = $this->repo();
        $repo->rememberForever('foo', function () {
            return 'bar';
        });
        $this->assertSame([[CCache_Event_CacheMissed::class, 'foo'], [CCache_Event_KeyWritten::class, 'foo']], $this->fired());
    }

    public function testForgetTriggersEvents() {
        $repo = $this->repo();
        $repo->getDriver()->put('foo', 'bar', 10);
        $this->assertTrue($repo->forget('foo'));
        $this->assertSame([[CCache_Event_KeyForgotten::class, 'foo']], $this->fired());
    }

    public function testTaggedEventsCarryTheTags() {
        $repo = $this->repo();
        $tagged = $repo->tags(['taylor', 'otwell']);
        $tagged->put('foo', 'bar', 10);
        $tagged->get('foo');
        $tagged->get('nope');
        $tagged->forget('foo');
        $this->assertSame([
            [CCache_Event_KeyWritten::class, 'foo'],
            [CCache_Event_CacheHit::class, 'foo'],
            [CCache_Event_CacheMissed::class, 'nope'],
            [CCache_Event_KeyForgotten::class, 'foo'],
        ], $this->fired(), 'event tagged memakai kunci polos, bukan kunci ber-namespace');
        foreach ($this->events as $event) {
            $this->assertSame(['taylor', 'otwell'], $event->tags);
        }
    }
}
