<?php
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Broadcaster palsu yang merekam siaran, dipasang lewat Manager::extend() supaya seluruh
 * jalur (event ShouldBroadcast → dispatcher → BroadcastEvent → connection()->broadcast) bisa
 * dipastikan tanpa Pusher/Redis.
 */
class UjiBroadcast_Recorder extends CBroadcast_BroadcasterAbstract {
    /** @var array[] */
    public static $sent = [];

    /** @var array */
    public static $authenticated = [];

    public function auth($request) {
        return $this->verifyUserCanAccessChannel($request, $request->channel_name);
    }

    public function validAuthenticationResponse($request, $result) {
        return ['result' => $result, 'user' => $request->user()];
    }

    public function broadcast(array $channels, $event, array $payload = []) {
        static::$sent[] = ['channels' => $this->formatChannels($channels), 'event' => $event, 'payload' => $payload];
    }
}

class UjiBroadcast_Order implements CBroadcast_Contract_ShouldBroadcastInterface {
    use CBroadcast_Trait_InteractWithSocketTrait;

    public $orderId;

    public $items;

    public $broadcastQueue = 'siaran';

    protected $secret = 'tidak ikut';

    public function __construct($orderId, $items = []) {
        $this->orderId = $orderId;
        $this->items = $items;
    }

    public function broadcastOn() {
        return [new CBroadcast_Channel_PrivateChannel('order.' . $this->orderId), 'public-feed'];
    }
}

class UjiBroadcast_NamedOrder extends UjiBroadcast_Order {
    public function broadcastAs() {
        return 'order.updated';
    }

    public function broadcastWith() {
        return ['id' => $this->orderId, 'total' => count($this->items)];
    }

    public function broadcastConnections() {
        return ['uji_rekam'];
    }
}

class UjiBroadcast_NowOrder extends UjiBroadcast_Order implements CBroadcast_Contract_ShouldBroadcastNowInterface {
}

class UjiBroadcast_ConditionalOrder extends UjiBroadcast_Order {
    public function broadcastWhen() {
        return $this->orderId > 0;
    }
}

class UjiBroadcast_Request {
    public $channel_name;

    protected $user;

    public function __construct($channel, $user) {
        $this->channel_name = $channel;
        $this->user = $user;
    }

    public function user($guard = null) {
        return $this->user;
    }
}

/**
 * CBroadcast: manager (driver log/null/kustom, default dari config), BroadcastEvent (nama,
 * channel, payload dari properti publik atau broadcastWith, koneksi), jalur ShouldBroadcast
 * lewat CEvent (antrean vs langsung, broadcastWhen), dan otorisasi channel BroadcasterAbstract
 * (pola {param}, opsi guard, penolakan).
 */
class BroadcastTest extends TestCase {
    /** @var array */
    protected $originalConnections;

    /** @var string */
    protected $originalDefault;

    /** @var string */
    protected $originalQueue;

    protected function setUp(): void {
        UjiBroadcast_Recorder::$sent = [];
        $this->originalConnections = CConfig::repository()->get('broadcast.connections');
        $this->originalDefault = CConfig::repository()->get('broadcast.default');
        CConfig::repository()->set('broadcast.connections.uji_rekam', ['driver' => 'uji_rekam']);
        CConfig::repository()->set('broadcast.default', 'uji_rekam');
        CBroadcast::manager()->extend('uji_rekam', function () {
            return new UjiBroadcast_Recorder();
        });
        CBroadcast::manager()->purge('uji_rekam');
        $this->originalQueue = CQueue::queuer()->getDefaultDriver();
        CQueue::queuer()->setDefaultDriver('sync');
    }

    protected function tearDown(): void {
        CConfig::repository()->set('broadcast.connections', $this->originalConnections);
        CConfig::repository()->set('broadcast.default', $this->originalDefault);
        CBroadcast::manager()->forgetDrivers();
        CQueue::queuer()->setDefaultDriver($this->originalQueue);
    }

    // ---- manager ----

    public function testManagerResolvesBuiltInAndCustomDrivers() {
        $manager = new CBroadcast_Manager();
        $this->assertInstanceOf(CBroadcast_Broadcaster_NullBroadcaster::class, $manager->connection('null'));
        $this->assertInstanceOf(CBroadcast_Broadcaster_NullBroadcaster::class, $manager->driver('null'));
        CConfig::repository()->set('broadcast.connections.uji_log', ['driver' => 'log']);
        $this->assertInstanceOf(CBroadcast_Broadcaster_LogBroadcaster::class, $manager->connection('uji_log'));
        $manager->extend('uji_rekam', function () {
            return new UjiBroadcast_Recorder();
        });
        $recorder = $manager->connection('uji_rekam');
        $this->assertInstanceOf(UjiBroadcast_Recorder::class, $recorder);
        $this->assertSame($recorder, $manager->connection('uji_rekam'), 'di-memoize');
        $this->assertSame('uji_rekam', $manager->getDefaultDriver());
        $this->assertSame($recorder, $manager->connection(), 'tanpa nama = default config');
        $manager->purge('uji_rekam');
        $this->assertNotSame($recorder, $manager->connection('uji_rekam'));
    }

    public function testUnknownDriverThrows() {
        CConfig::repository()->set('broadcast.connections.uji_bad', ['driver' => 'alien']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [alien] is not supported.');
        (new CBroadcast_Manager())->connection('uji_bad');
    }

    public function testManagerForwardsBroadcastToTheDefaultDriver() {
        CBroadcast::manager()->broadcast(['satu', new CBroadcast_Channel_PresenceChannel('ruang')], 'ping', ['a' => 1]);
        $this->assertCount(1, UjiBroadcast_Recorder::$sent);
        $this->assertSame(['satu', 'presence-ruang'], UjiBroadcast_Recorder::$sent[0]['channels'], 'objek channel diformat ke string');
        $this->assertSame('ping', UjiBroadcast_Recorder::$sent[0]['event']);
        $this->assertSame(['a' => 1], UjiBroadcast_Recorder::$sent[0]['payload']);
    }

    public function testChannelClassesPrefixTheirNames() {
        $this->assertSame('private-order.1', (string) new CBroadcast_Channel_PrivateChannel('order.1'));
        $this->assertSame('presence-ruang', (string) new CBroadcast_Channel_PresenceChannel('ruang'));
        $this->assertSame('polos', (string) new CBroadcast_Channel('polos'));
    }

    // ---- BroadcastEvent ----

    public function testBroadcastEventUsesClassNameAndPublicProperties() {
        $event = new UjiBroadcast_Order(5, ['x', 'y']);
        $job = new CBroadcast_BroadcastEvent($event);
        $this->assertSame(UjiBroadcast_Order::class, $job->displayName());
        $job->execute();
        $sent = UjiBroadcast_Recorder::$sent[0];
        $this->assertSame(UjiBroadcast_Order::class, $sent['event'], 'tanpa broadcastAs = nama kelas');
        $this->assertSame(['private-order.5', 'public-feed'], $sent['channels']);
        $this->assertSame(['orderId' => 5, 'items' => ['x', 'y'], 'socket' => null], $sent['payload'], 'properti publik saja; broadcastQueue dan protected dibuang');
    }

    public function testBroadcastEventHonoursBroadcastAsWithAndConnections() {
        $event = new UjiBroadcast_NamedOrder(9, ['a']);
        (new CBroadcast_BroadcastEvent($event))->execute();
        $sent = UjiBroadcast_Recorder::$sent[0];
        $this->assertSame('order.updated', $sent['event']);
        $this->assertSame(['id' => 9, 'total' => 1, 'socket' => null], $sent['payload'], 'broadcastWith + socket');
    }

    public function testBroadcastEventCarriesSocketToExcludeTheSender() {
        $event = new UjiBroadcast_Order(1);
        $event->socket = 'sock-123';
        (new CBroadcast_BroadcastEvent($event))->execute();
        $this->assertSame('sock-123', UjiBroadcast_Recorder::$sent[0]['payload']['socket']);
    }

    public function testBroadcastEventWithoutChannelsSendsNothing() {
        $event = new class() implements CBroadcast_Contract_ShouldBroadcastInterface {
            public function broadcastOn() {
                return [];
            }
        };
        (new CBroadcast_BroadcastEvent($event))->execute();
        $this->assertSame([], UjiBroadcast_Recorder::$sent);
    }

    public function testBroadcastEventCopiesTriesTimeoutAndClonesTheEvent() {
        $event = new UjiBroadcast_Order(1);
        $event->tries = 3;
        $event->timeout = 20;
        $job = new CBroadcast_BroadcastEvent($event);
        $this->assertSame(3, $job->tries);
        $this->assertSame(20, $job->timeout);
        $copy = clone $job;
        $this->assertNotSame($job->event, $copy->event);
        $this->assertEquals($job->event, $copy->event);
    }

    // ---- lewat CEvent ----

    public function testDispatchingAShouldBroadcastEventQueuesItOnTheEventQueue() {
        CEvent::dispatch(new UjiBroadcast_Order(3));
        $this->assertCount(1, UjiBroadcast_Recorder::$sent, 'koneksi sync menjalankan job siaran langsung');
        $this->assertSame(['private-order.3', 'public-feed'], UjiBroadcast_Recorder::$sent[0]['channels']);
    }

    public function testShouldBroadcastNowSkipsTheQueue() {
        $original = CQueue::queuer()->getDefaultDriver();
        CQueue::queuer()->setDefaultDriver('null');
        try {
            CEvent::dispatch(new UjiBroadcast_Order(4));
            $this->assertSame([], UjiBroadcast_Recorder::$sent, 'event biasa masuk antrean null → tidak pernah tersiar');
            CEvent::dispatch(new UjiBroadcast_NowOrder(5));
            $this->assertCount(1, UjiBroadcast_Recorder::$sent, 'ShouldBroadcastNow dijalankan langsung');
        } finally {
            CQueue::queuer()->setDefaultDriver($original);
        }
    }

    public function testBroadcastWhenGatesTheBroadcast() {
        CEvent::dispatch(new UjiBroadcast_ConditionalOrder(0));
        $this->assertSame([], UjiBroadcast_Recorder::$sent);
        CEvent::dispatch(new UjiBroadcast_ConditionalOrder(1));
        $this->assertCount(1, UjiBroadcast_Recorder::$sent);
    }

    public function testPendingBroadcastDispatchesOnDestructWithViaAndToOthers() {
        $event = new UjiBroadcast_Order(6);
        $pending = CBroadcast::manager()->event($event);
        $this->assertInstanceOf(CBroadcast_PendingBroadcast::class, $pending);
        $this->assertSame([], UjiBroadcast_Recorder::$sent, 'belum disiarkan selama objek pending hidup');
        $pending->toOthers();
        unset($pending);
        $this->assertCount(1, UjiBroadcast_Recorder::$sent);
    }

    // ---- otorisasi channel ----

    public function testChannelAuthorizationWithParametersAndOptions() {
        $broadcaster = new UjiBroadcast_Recorder();
        $seen = [];
        $broadcaster->channel('order.{orderId}', function ($user, $orderId) use (&$seen) {
            $seen[] = [$user, $orderId];

            return $user === 'pemilik' && $orderId === '10';
        }, ['guards' => ['web']]);
        $this->assertSame($broadcaster, $broadcaster->channel('lain', function () {
            return false;
        }));

        $response = $broadcaster->auth(new UjiBroadcast_Request('order.10', 'pemilik'));
        $this->assertSame(['result' => true, 'user' => 'pemilik'], $response);
        $this->assertSame([['pemilik', '10']], $seen, 'parameter {orderId} diekstrak dari nama channel');
    }

    public function testChannelAuthorizationDeniesWhenCallbackIsFalseOrNoPatternMatches() {
        $broadcaster = new UjiBroadcast_Recorder();
        $broadcaster->channel('order.{orderId}', function ($user, $orderId) {
            return false;
        });
        try {
            $broadcaster->auth(new UjiBroadcast_Request('order.10', 'siapa'));
            $this->fail('harus ditolak');
        } catch (AccessDeniedHttpException $e) {
            $this->assertTrue(true);
        }
        $this->expectException(AccessDeniedHttpException::class);
        $broadcaster->auth(new UjiBroadcast_Request('tidak.terdaftar', 'siapa'));
    }

    public function testChannelAuthorizationCanReturnPresenceData() {
        $broadcaster = new UjiBroadcast_Recorder();
        $broadcaster->channel('ruang.{id}', function ($user, $id) {
            return ['id' => $user, 'ruang' => $id];
        });
        $this->assertSame(['result' => ['id' => 'u1', 'ruang' => '7'], 'user' => 'u1'], $broadcaster->auth(new UjiBroadcast_Request('ruang.7', 'u1')));
    }

    public function testChannelClassHandlerIsResolvedFromTheContainer() {
        $broadcaster = new UjiBroadcast_Recorder();
        $broadcaster->channel('kelas.{id}', UjiBroadcast_ChannelHandler::class);
        $this->assertSame(['result' => 'join:u2:3', 'user' => 'u2'], $broadcaster->auth(new UjiBroadcast_Request('kelas.3', 'u2')));
    }

    public function testFacadeRegisterChannel() {
        CBroadcast::registerChannel('fasad.{id}', function ($user, $id) {
            return $id === '1';
        });
        $this->assertSame(['result' => true, 'user' => 'x'], CBroadcast::manager()->connection()->auth(new UjiBroadcast_Request('fasad.1', 'x')));
    }
}

class UjiBroadcast_ChannelHandler {
    public function join($user, $id) {
        return 'join:' . $user . ':' . $id;
    }
}
