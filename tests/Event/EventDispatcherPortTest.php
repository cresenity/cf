<?php
use PHPUnit\Framework\TestCase;

/**
 * Port EventsDispatcherTest hulu yang belum tercakup: listener pada interface event, event kelas
 * sebagai payload, wildcard menerima nama event, listener ganda, event bersarang, falsy tidak
 * menghentikan, cache wildcard, dispatch objek dengan nama kustom.
 */
class EventDispatcherPortTest extends TestCase {
    /**
     * @var CEvent_Dispatcher
     */
    protected $d;

    protected function setUp(): void {
        $this->d = new CEvent_Dispatcher(CContainer::getInstance());
        UjiEvent_Log::$seen = [];
    }

    public function testEventClassesArePayloadAndNameIsTheClass() {
        $this->d->listen(UjiEvent_Ping::class, function ($event) {
            UjiEvent_Log::$seen[] = get_class($event) . ':' . $event->value;
        });
        $this->d->dispatch(new UjiEvent_Ping('a'));
        $this->assertSame([UjiEvent_Ping::class . ':a'], UjiEvent_Log::$seen);
    }

    public function testInterfacesWork() {
        $this->d->listen(UjiEvent_MarkerInterface::class, function ($event) {
            UjiEvent_Log::$seen[] = 'iface:' . $event->value;
        });
        $this->d->dispatch(new UjiEvent_Ping('x'));
        $this->assertSame(['iface:x'], UjiEvent_Log::$seen, 'listener pada interface menerima event kelas yang mengimplementasikannya');
        $this->assertFalse($this->d->hasListeners(UjiEvent_Ping::class), 'hasListeners tidak melihat interface (hulu juga)');
        $this->assertTrue($this->d->hasListeners(UjiEvent_MarkerInterface::class));
    }

    public function testBothClassesAndInterfacesWorkInRegistrationOrder() {
        $this->d->listen(UjiEvent_Ping::class, function () {
            UjiEvent_Log::$seen[] = 'class';
        });
        $this->d->listen(UjiEvent_MarkerInterface::class, function () {
            UjiEvent_Log::$seen[] = 'iface';
        });
        $this->d->dispatch(new UjiEvent_Ping('x'));
        $this->assertSame(['class', 'iface'], UjiEvent_Log::$seen, 'listener kelas dulu, lalu interface');
    }

    public function testWildcardListenersReceiveTheEventNameFirst() {
        $this->d->listen('uji.*', function ($eventName, $payload) {
            UjiEvent_Log::$seen[] = [$eventName, $payload];
        });
        $this->d->dispatch('uji.satu', ['a', 'b']);
        $this->assertSame([['uji.satu', ['a', 'b']]], UjiEvent_Log::$seen);
    }

    public function testEventPassedFirstToWildcards() {
        $this->d->listen('uji.*', function ($eventName, $payload) {
            UjiEvent_Log::$seen[] = 'wild:' . $eventName;
        });
        $this->d->listen('uji.satu', function ($a) {
            UjiEvent_Log::$seen[] = 'exact:' . $a;
        });
        $this->d->dispatch('uji.satu', ['x']);
        $this->assertSame(['exact:x', 'wild:uji.satu'], UjiEvent_Log::$seen, 'listener persis dulu, wildcard sesudahnya');
    }

    public function testWildcardListenersWithResponses() {
        $this->d->listen('uji.*', function () {
            return 'a';
        });
        $this->d->listen('uji.*', function () {
            return 'b';
        });
        $this->assertSame(['a', 'b'], $this->d->dispatch('uji.x'));
    }

    public function testReturningFalsyValuesContinuesPropagation() {
        $this->d->listen('uji', function () {
            return 0;
        });
        $this->d->listen('uji', function () {
            return '';
        });
        $this->d->listen('uji', function () {
            return null;
        });
        $this->d->listen('uji', function () {
            return 'last';
        });
        $this->assertSame([0, '', null, 'last'], $this->d->dispatch('uji'), 'hanya false yang menghentikan');
    }

    public function testHaltingReturnsTheFirstNonNullEvenIfFalsy() {
        $this->d->listen('uji', function () {
            return null;
        });
        $this->d->listen('uji', function () {
            return 0;
        });
        $this->d->listen('uji', function () {
            return 'never';
        });
        $this->assertSame(0, $this->d->until('uji'));
    }

    public function testDuplicateListenersWillFire() {
        $listener = function () {
            UjiEvent_Log::$seen[] = 'x';
        };
        $this->d->listen('uji', $listener);
        $this->d->listen('uji', $listener);
        $this->d->dispatch('uji');
        $this->assertCount(2, UjiEvent_Log::$seen);
    }

    public function testNestedEvent() {
        $d = $this->d;
        $d->listen('outer', function () use ($d) {
            UjiEvent_Log::$seen[] = 'outer-start';
            $d->dispatch('inner');
            UjiEvent_Log::$seen[] = 'outer-end';
        });
        $d->listen('inner', function () {
            UjiEvent_Log::$seen[] = 'inner';
        });
        $d->dispatch('outer');
        $this->assertSame(['outer-start', 'inner', 'outer-end'], UjiEvent_Log::$seen);
    }

    public function testWildcardCacheIsClearedWhenListenersChange() {
        $this->d->listen('uji.*', function () {
            return 'first';
        });
        $this->assertSame(['first'], $this->d->dispatch('uji.a'));
        $this->d->listen('uji.*', function () {
            return 'second';
        });
        $this->assertSame(['first', 'second'], $this->d->dispatch('uji.a'), 'listener baru masuk meski hasil wildcard sudah pernah di-cache');
        $this->d->forget('uji.*');
        $this->assertSame([], $this->d->dispatch('uji.a'));
        $this->assertFalse($this->d->hasListeners('uji.a'));
    }

    public function testListenersCanBeFoundIncludingWildcards() {
        $this->d->listen('uji.satu', function () {
        });
        $this->d->listen('uji.*', function () {
        });
        $this->assertCount(2, $this->d->getListeners('uji.satu'));
        $this->assertCount(1, $this->d->getListeners('uji.dua'));
        $this->assertCount(0, $this->d->getListeners('lain'));
    }

    public function testListenersAreResolvedLazilyFromClassNames() {
        UjiEvent_LazyListener::$constructed = 0;
        $this->d->listen('uji', UjiEvent_LazyListener::class);
        $this->assertSame(0, UjiEvent_LazyListener::$constructed, 'mendaftar tidak membuat instance');
        $this->d->dispatch('uji', ['v']);
        $this->assertSame(1, UjiEvent_LazyListener::$constructed);
        $this->assertSame(['v'], UjiEvent_Log::$seen);
        $this->d->dispatch('uji', ['w']);
        $this->assertSame(2, UjiEvent_LazyListener::$constructed, 'instance baru tiap dispatch');
    }

    public function testClassAtMethodListenerReceivesPayload() {
        $this->d->listen('uji', UjiEvent_LazyListener::class . '@custom');
        $this->d->dispatch('uji', [1, 2]);
        $this->assertSame(['custom:1:2'], UjiEvent_Log::$seen);
    }

    public function testDispatchObjectEventWithArrayListenerAndMultipleNames() {
        $this->d->listen([UjiEvent_Ping::class, 'uji.string'], function ($event) {
            UjiEvent_Log::$seen[] = is_object($event) ? 'obj' : $event;
        });
        $this->d->dispatch(new UjiEvent_Ping('a'));
        $this->d->dispatch('uji.string', ['str']);
        $this->assertSame(['obj', 'str'], UjiEvent_Log::$seen);
    }

    public function testPushAndFlushWithObjectPayload() {
        $this->d->listen('uji.later', function ($event) {
            UjiEvent_Log::$seen[] = $event->value;
        });
        $this->d->push('uji.later', [new UjiEvent_Ping('queued')]);
        $this->assertSame([], UjiEvent_Log::$seen);
        $this->d->flush('uji.later');
        $this->assertSame(['queued'], UjiEvent_Log::$seen);
    }
}

interface UjiEvent_MarkerInterface {
}

class UjiEvent_Ping implements UjiEvent_MarkerInterface {
    public $value;

    public function __construct($value) {
        $this->value = $value;
    }
}

class UjiEvent_Log {
    public static $seen = [];
}

class UjiEvent_LazyListener {
    public static $constructed = 0;

    public function __construct() {
        static::$constructed++;
    }

    public function handle($value) {
        UjiEvent_Log::$seen[] = $value;
    }

    public function custom($a, $b) {
        UjiEvent_Log::$seen[] = 'custom:' . $a . ':' . $b;
    }
}
