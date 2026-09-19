<?php
use PHPUnit\Framework\TestCase;
use Ratchet\ConnectionInterface;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Channel menyimpan clone koneksi (CWebSocket_Channel::saveConnection), jadi pesan dicatat
 * di log statis per socketId supaya terlihat dari objek asli di test.
 */
class UjiWs_Connection implements ConnectionInterface {
    /** @var array */
    public static $log = [];

    /** @var string */
    public $socketId;

    /** @var CWebSocket_App */
    public $app;

    /** @var bool */
    public $closed = false;

    public function __construct(CWebSocket_App $app, $socketId) {
        $this->app = $app;
        $this->socketId = $socketId;
        static::$log[$socketId] = [];
    }

    public function send($data) {
        static::$log[$this->socketId][] = $data;

        return $this;
    }

    public function close() {
        $this->closed = true;
    }

    /**
     * @return string[]
     */
    public function sent() {
        return static::$log[$this->socketId];
    }

    /**
     * @return array
     */
    public function lastMessage() {
        $sent = $this->sent();

        return json_decode(end($sent), true);
    }
}

/**
 * CWebSocket tanpa server: LocalChannelManager + Channel/PrivateChannel/PresenceChannel dengan koneksi palsu,
 * tanda tangan Pusher, helper Redis/Pusher, dan model App.
 */
class ChannelManagerTest extends TestCase {
    /** @var CWebSocket_ChannelManager_LocalChannelManager */
    protected $manager;

    /** @var CWebSocket_App */
    protected $app;

    /** @var mixed */
    protected $previousManager;

    /** @var mixed */
    protected $previousLogger;

    protected function setUp(): void {
        $this->previousManager = CWebSocket::channelManager();
        $this->previousLogger = CWebSocket::connectionLogger();
        $this->manager = new CWebSocket_ChannelManager_LocalChannelManager(React\EventLoop\Factory::create());
        CWebSocket::setChannelManager($this->manager);
        CWebSocket::setConnectionLogger((new CWebSocket_Server_Logger_ConnectionLogger(new NullOutput()))->enable(false));
        $this->app = (new CWebSocket_App(7, 'kunci', 'rahasia'))->setName('Uji');
    }

    protected function tearDown(): void {
        CWebSocket::setChannelManager($this->previousManager);
        CWebSocket::setConnectionLogger($this->previousLogger);
    }

    /**
     * @param string $socketId
     *
     * @return UjiWs_Connection
     */
    protected function connection($socketId) {
        return new UjiWs_Connection($this->app, $socketId);
    }

    /**
     * @param mixed $promise
     *
     * @return mixed
     */
    protected function value($promise) {
        $resolved = null;
        $promise->then(function ($value) use (&$resolved) {
            $resolved = $value;
        });

        return $resolved;
    }

    /**
     * Tanda tangan pusher: key:hmac_sha256(socketId:channel[:channel_data], secret).
     *
     * @param string      $socketId
     * @param string      $channel
     * @param null|string $channelData
     *
     * @return string
     */
    protected function auth($socketId, $channel, $channelData = null) {
        $signature = $socketId . ':' . $channel . ($channelData !== null ? ':' . $channelData : '');

        return 'kunci:' . hash_hmac('sha256', $signature, 'rahasia');
    }

    public function testChannelClassFollowsThePrefix() {
        $this->assertInstanceOf(CWebSocket_Channel::class, $this->manager->findOrCreate(7, 'berita'));
        $this->assertNotInstanceOf(CWebSocket_Channel_PrivateChannel::class, $this->manager->find(7, 'berita'));
        $this->assertInstanceOf(CWebSocket_Channel_PrivateChannel::class, $this->manager->findOrCreate(7, 'private-chat'));
        $this->assertInstanceOf(CWebSocket_Channel_PresenceChannel::class, $this->manager->findOrCreate(7, 'presence-ruang'));
        $this->assertNull($this->manager->find(7, 'tidak-ada'));
        $this->assertSame($this->manager->find(7, 'berita'), $this->manager->findOrCreate(7, 'berita'), 'instance yang sama');
        $this->assertNull($this->manager->find(8, 'berita'), 'per app');
    }

    public function testPublicSubscribeAndUnsubscribe() {
        $connection = $this->connection('1.1');
        $this->assertTrue($this->value($this->manager->subscribeToChannel($connection, 'berita', (object) [])));
        $this->assertSame(['event' => 'pusher_internal:subscription_succeeded', 'channel' => 'berita'], $connection->lastMessage());
        $channel = $this->manager->find(7, 'berita');
        $this->assertTrue($channel->hasConnections());
        $this->assertTrue($channel->hasConnection($connection));
        $this->assertSame(1, $this->value($this->manager->getLocalConnectionsCount(7, 'berita')));
        $this->assertSame(1, $this->value($this->manager->getGlobalConnectionsCount(7)));

        $this->assertTrue($this->value($this->manager->unsubscribeFromChannel($connection, 'berita', (object) [])));
        $this->assertFalse($channel->hasConnections());
        $this->assertFalse($this->value($this->manager->unsubscribeFromChannel($connection, 'berita', (object) [])), 'sudah tidak berlangganan → false');
        $this->assertSame(0, $this->value($this->manager->getLocalConnectionsCount(7)));
    }

    public function testBroadcastReachesEveryoneOrEveryoneExcept() {
        $a = $this->connection('a.1');
        $b = $this->connection('b.2');
        $this->manager->subscribeToChannel($a, 'berita', (object) []);
        $this->manager->subscribeToChannel($b, 'berita', (object) []);
        $channel = $this->manager->find(7, 'berita');
        $channel->broadcastLocally(7, (object) ['event' => 'hai', 'data' => '1']);
        $this->assertSame('hai', $a->lastMessage()['event']);
        $this->assertSame('hai', $b->lastMessage()['event']);

        $channel->broadcastToEveryoneExcept((object) ['event' => 'kecuali-a'], 'a.1', 7, false);
        $this->assertSame('hai', $a->lastMessage()['event'], 'pengirim tidak menerima');
        $this->assertSame('kecuali-a', $b->lastMessage()['event']);
        $this->assertSame(['a.1', 'b.2'], array_keys($channel->getConnections()));
    }

    public function testUnsubscribeFromAllChannelsRemovesTheConnectionEverywhere() {
        $connection = $this->connection('x.9');
        $this->manager->subscribeToChannel($connection, 'satu', (object) []);
        $this->manager->subscribeToChannel($connection, 'dua', (object) []);
        $this->assertSame(1, $this->value($this->manager->getLocalConnectionsCount(7)), 'koneksi unik, bukan jumlah langganan');
        $this->manager->unsubscribeFromAllChannels($connection);
        $this->assertSame(0, $this->value($this->manager->getLocalConnectionsCount(7)));
    }

    public function testPrivateChannelRequiresAValidSignature() {
        $connection = $this->connection('5.5');
        $payload = (object) ['auth' => $this->auth('5.5', 'private-chat'), 'channel' => 'private-chat'];
        $this->assertTrue($this->value($this->manager->subscribeToChannel($connection, 'private-chat', $payload)));
        $this->assertSame('pusher_internal:subscription_succeeded', $connection->lastMessage()['event']);

        $other = $this->connection('6.6');
        $this->expectException(CWebSocket_Exception_InvalidSignature::class);
        $this->manager->subscribeToChannel($other, 'private-chat', (object) ['auth' => $this->auth('5.5', 'private-chat')]);
    }

    public function testPrivateChannelRejectsSignatureOfAnotherChannel() {
        $connection = $this->connection('5.5');
        $this->expectException(CWebSocket_Exception_InvalidSignature::class);
        $this->manager->subscribeToChannel($connection, 'private-chat', (object) ['auth' => $this->auth('5.5', 'private-lain')]);
    }

    public function testPresenceChannelTracksMembersAndNotifiesJoinsAndLeaves() {
        $hery = $this->connection('h.1');
        $heryData = json_encode(['user_id' => 10, 'user_info' => ['name' => 'Hery']]);
        $this->manager->subscribeToChannel($hery, 'presence-ruang', (object) ['auth' => $this->auth('h.1', 'presence-ruang', $heryData), 'channel_data' => $heryData]);
        $first = json_decode($hery->sent()[0], true);
        $this->assertSame('pusher_internal:subscription_succeeded', $first['event']);
        $presence = json_decode($first['data'], true)['presence'];
        $this->assertSame(['10'], $presence['ids']);
        $this->assertSame(['10' => ['name' => 'Hery']], $presence['hash']);
        $this->assertSame(1, $presence['count']);

        $budi = $this->connection('b.2');
        $budiData = json_encode(['user_id' => 11, 'user_info' => ['name' => 'Budi']]);
        $this->manager->subscribeToChannel($budi, 'presence-ruang', (object) ['auth' => $this->auth('b.2', 'presence-ruang', $budiData), 'channel_data' => $budiData]);
        $added = $hery->lastMessage();
        $this->assertSame('pusher_internal:member_added', $added['event'], 'anggota lama diberi tahu');
        $this->assertSame(11, json_decode($added['data'], true)['user_id']);
        $this->assertSame(['10', '11'], json_decode(json_decode($budi->sent()[0], true)['data'], true)['presence']['ids']);

        $members = $this->value($this->manager->getChannelMembers(7, 'presence-ruang'));
        $this->assertCount(2, $members);
        $this->assertSame(['presence-ruang' => 2], $this->value($this->manager->getChannelsMembersCount(7, ['presence-ruang'])));
        $this->assertSame(['b.2'], $this->value($this->manager->getMemberSockets(11, 7, 'presence-ruang')));

        $this->manager->unsubscribeFromChannel($budi, 'presence-ruang', (object) []);
        $removed = $hery->lastMessage();
        $this->assertSame('pusher_internal:member_removed', $removed['event']);
        $this->assertSame(11, json_decode($removed['data'], true)['user_id']);
        $this->assertCount(1, $this->value($this->manager->getChannelMembers(7, 'presence-ruang')));
    }

    public function testPresenceMemberAddedOnlyOnFirstSocketOfAUser() {
        $tab1 = $this->connection('t.1');
        $tab2 = $this->connection('t.2');
        $watcher = $this->connection('w.0');
        $data = json_encode(['user_id' => 10]);
        $watcherData = json_encode(['user_id' => 99]);
        $this->manager->subscribeToChannel($watcher, 'presence-r', (object) ['auth' => $this->auth('w.0', 'presence-r', $watcherData), 'channel_data' => $watcherData]);
        $this->manager->subscribeToChannel($tab1, 'presence-r', (object) ['auth' => $this->auth('t.1', 'presence-r', $data), 'channel_data' => $data]);
        $this->manager->subscribeToChannel($tab2, 'presence-r', (object) ['auth' => $this->auth('t.2', 'presence-r', $data), 'channel_data' => $data]);
        $events = array_map(function ($message) {
            return json_decode($message, true)['event'];
        }, $watcher->sent());
        $this->assertSame(1, count(array_keys($events, 'pusher_internal:member_added', true)), 'tab kedua user yang sama tidak memicu member_added lagi');
        $this->assertCount(2, $this->value($this->manager->getChannelMembers(7, 'presence-r')), 'anggota unik per user_id');
        $this->assertSame(['t.1', 't.2'], $this->value($this->manager->getMemberSockets(10, 7, 'presence-r')));
    }

    public function testHelpers() {
        $this->assertSame(['a' => '1', 'b' => '2'], CWebSocket_Helper::redisListToArray(['a', '1', 'b', '2']));
        $this->assertSame('x=1;y=a,b', CWebSocket_Helper::pusherArrayImplode('=', ';', ['x' => 1, 'y' => ['a', 'b']]), 'nilai array selalu digabung koma');
        $this->assertSame('mentah', CWebSocket_Helper::pusherArrayImplode('=', ',', 'mentah'));
        $this->assertSame(42, $this->value(CWebSocket_Helper::createFulfilledPromise(42)));
    }

    public function testAppModelSetters() {
        $app = (new CWebSocket_App(1, 'k', 's'))->setName('Nama')->setHost('ws.uji.test')->setPath('/ws')->enableClientMessages()->setCapacity(100)->enableStatistics(false)->setAllowedOrigins(['https://uji.test']);
        $this->assertSame(1, $app->id);
        $this->assertSame('k', $app->key);
        $this->assertSame('s', $app->secret);
        $this->assertSame('Nama', $app->name);
        $this->assertSame('ws.uji.test', $app->host);
        $this->assertSame('/ws', $app->path);
        $this->assertTrue($app->clientMessagesEnabled);
        $this->assertSame(100, $app->capacity);
        $this->assertFalse($app->statisticsEnabled);
        $this->assertSame(['https://uji.test'], $app->allowedOrigins);
    }
}
