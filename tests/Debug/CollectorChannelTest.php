<?php
use PHPUnit\Framework\TestCase;

/**
 * Menangkap pushToDevcloud()/put() tanpa benar-benar push/menulis file, supaya test channel
 * fokus ke CHTTP palsu untuk slack/discord/sentry saja.
 */
class UjiDebug_ChannelExceptionCollector extends CDebug_Collector_Exception {
    /**
     * @var array
     */
    public $pushed = [];

    /**
     * @var array
     */
    public $stored = [];

    protected function pushToDevcloud($data, $url) {
        $this->pushed[] = ['url' => $url, 'data' => $data];

        return true;
    }

    public function put($data) {
        $this->stored[] = $data;

        return true;
    }
}

/**
 * CDebug_Collector_Exception::dispatch(): default (collector.exceptionChannels kosong) tetap
 * devcloud saja seperti sebelum fitur channel ada; channel lain (slack/discord/sentry) lewat
 * CDebug_Collector_Channel_* → CVendor_* → CHTTP::client() palsu.
 */
class CollectorChannelTest extends TestCase {
    /**
     * @var UjiDebug_ChannelExceptionCollector
     */
    protected $collector;

    /**
     * @var array
     */
    protected $originalConfig = [];

    protected function setUp(): void {
        foreach ([
            'collector.exception', 'collector.exceptionPush', 'collector.exceptionChannels',
            'collector.slack', 'collector.discord', 'collector.sentry',
        ] as $key) {
            $this->originalConfig[$key] = CConfig::repository()->get($key);
        }
        CConfig::repository()->set('collector.exception', true);
        CConfig::repository()->set('collector.exceptionPush', false);
        CConfig::repository()->set('collector.exceptionChannels', []);
        CConfig::repository()->set('collector.slack', ['webhookUrl' => false]);
        CConfig::repository()->set('collector.discord', ['webhookUrl' => false]);
        CConfig::repository()->set('collector.sentry', ['dsn' => false]);

        $this->collector = new UjiDebug_ChannelExceptionCollector();
        $property = new ReflectionProperty(CDebug_CollectorManager::class, 'exception');
        $property->setAccessible(true);
        $property->setValue(CDebug::collector(), $this->collector);
    }

    protected function tearDown(): void {
        foreach ($this->originalConfig as $key => $value) {
            CConfig::repository()->set($key, $value);
        }
        $property = new ReflectionProperty(CDebug_CollectorManager::class, 'exception');
        $property->setAccessible(true);
        $property->setValue(CDebug::collector(), null);

        $instanceProperty = new ReflectionProperty(CHTTP_Client::class, 'instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);
    }

    public function testEmptyChannelsDefaultsToDevcloudOnlyUnchangedFromBeforeThisFeature() {
        CConfig::repository()->set('collector.exceptionPush', 'https://devcloud.test/v1/exceptions');

        CDebug::collector()->collectException(new RuntimeException('boom'));

        $this->assertCount(1, $this->collector->pushed);
        $this->assertSame('https://devcloud.test/v1/exceptions', $this->collector->pushed[0]['url']);
        $this->assertSame([], $this->collector->stored);
    }

    public function testSlackOnlySkipsDevcloudEntirely() {
        CHTTP::client()->fake([
            'hooks.slack.test/*' => CHTTP_Client::response(['ok' => true], 200),
        ]);
        CConfig::repository()->set('collector.exceptionChannels', ['slack']);
        CConfig::repository()->set('collector.exceptionPush', 'https://devcloud.test/v1/exceptions');
        CConfig::repository()->set('collector.slack', ['webhookUrl' => 'https://hooks.slack.test/services/x']);

        CDebug::collector()->collectException(new RuntimeException('boom slack'));

        $this->assertSame([], $this->collector->pushed, 'devcloud tidak ikut terpanggil - slack saja yang diminta');
        $this->assertSame([], $this->collector->stored);
        CHTTP::client()->assertSentCount(1);
        CHTTP::client()->assertSent(function (CHTTP_Client_Request $request) {
            $payload = $request->data();

            return $request->url() === 'https://hooks.slack.test/services/x'
                && strpos($payload['text'], 'boom slack') !== false
                && strpos($payload['text'], 'RuntimeException') !== false;
        });
    }

    public function testDevcloudPlusSlackSendsToBoth() {
        CHTTP::client()->fake([
            'hooks.slack.test/*' => CHTTP_Client::response(['ok' => true], 200),
        ]);
        CConfig::repository()->set('collector.exceptionChannels', ['devcloud', 'slack']);
        CConfig::repository()->set('collector.exceptionPush', 'https://devcloud.test/v1/exceptions');
        CConfig::repository()->set('collector.slack', ['webhookUrl' => 'https://hooks.slack.test/services/x']);

        CDebug::collector()->collectException(new RuntimeException('boom both'));

        $this->assertCount(1, $this->collector->pushed, 'devcloud tetap terpanggil karena ada di daftar channel');
        CHTTP::client()->assertSentCount(1);
    }

    public function testDiscordChannelPostsAnEmbedViaCVendorDiscordWebhook() {
        CHTTP::client()->fake([
            'discord.test/*' => CHTTP_Client::response(['id' => '123'], 200),
        ]);
        CConfig::repository()->set('collector.exceptionChannels', ['discord']);
        CConfig::repository()->set('collector.discord', ['webhookUrl' => 'https://discord.test/api/webhooks/x']);

        CDebug::collector()->collectException(new RuntimeException('boom discord'));

        CHTTP::client()->assertSent(function (CHTTP_Client_Request $request) {
            $payload = $request->data();
            $embed = $payload['embeds'][0];

            return $request->url() === 'https://discord.test/api/webhooks/x'
                && strpos($embed['title'], 'RuntimeException') !== false
                && strpos($embed['description'], 'boom discord') !== false;
        });
    }

    public function testSentryChannelPostsAStoreEventWithDsnAuthHeaderViaCVendorSentry() {
        CHTTP::client()->fake([
            'o123.ingest.sentry.test/*' => CHTTP_Client::response([], 200),
        ]);
        CConfig::repository()->set('collector.exceptionChannels', ['sentry']);
        CConfig::repository()->set('collector.sentry', ['dsn' => 'https://abcdef@o123.ingest.sentry.test/9']);

        CDebug::collector()->collectException(new RuntimeException('boom sentry'));

        CHTTP::client()->assertSent(function (CHTTP_Client_Request $request) {
            $payload = $request->data();

            return $request->url() === 'https://o123.ingest.sentry.test/api/9/store/'
                && $request->hasHeader('X-Sentry-Auth')
                && strpos($request->header('X-Sentry-Auth')[0], 'sentry_key=abcdef') !== false
                && $payload['exception']['values'][0]['type'] === 'RuntimeException'
                && $payload['exception']['values'][0]['value'] === 'boom sentry';
        });
    }

    public function testChannelWithoutItsWebhookOrDsnConfiguredIsSilentlySkipped() {
        CConfig::repository()->set('collector.exceptionChannels', ['slack']);
        // collector.slack.webhookUrl sengaja dibiarkan false (default setUp)

        $data = CDebug::collector()->collectException(new RuntimeException('tanpa webhook'));

        $this->assertNotNull($data, 'collect() tetap mengembalikan data walau channel tidak terkonfigurasi');
    }

    public function testUnknownChannelNameIsLoggedButNeverThrowsOutOfCollect() {
        CConfig::repository()->set('collector.exceptionChannels', ['nama-channel-tidak-ada']);

        $data = CDebug::collector()->collectException(new RuntimeException('channel aneh'));

        $this->assertNotNull($data, 'channel tak dikenal tidak boleh menjatuhkan collect()');
    }
}
