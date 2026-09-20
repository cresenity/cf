<?php
use PHPUnit\Framework\TestCase;

/**
 * Kolektor yang menangkap put()/push alih-alih menulis berkas atau membuka socket.
 */
class UjiDebug_DeprecatedCollector extends CDebug_Collector_Deprecated {
    /** @var array */
    public $stored = [];

    /** @var array */
    public $pushed = [];

    /** @var bool */
    public $pushSucceeds = true;

    /** @var bool */
    public $failPut = false;

    public function put($data) {
        if ($this->failPut) {
            throw new RuntimeException('disk penuh');
        }
        $this->stored[] = $data;

        return true;
    }

    protected function pushToDevcloud($data, $url) {
        $this->pushed[] = ['url' => $url, 'data' => $data];

        return $this->pushSucceeds;
    }
}

/**
 * Fungsi "deprecated" uji: memanggil CF::deprecated() seperti method framework yang ditandai @deprecated.
 */
class UjiDebug_LegacyApi {
    public static function oldWay() {
        CF::deprecated('UjiDebug_LegacyApi::oldWay', 'UjiDebug_LegacyApi::newWay()', '1.9');

        return 'lama';
    }

    public function oldMethod($message) {
        CDebug::collector()->collectDeprecated($message);
    }
}

/**
 * CDebug_Collector_Deprecated: dedupe per pemanggil, payload terstruktur, push lalu fallback berkas,
 * dimatikan lewat config, dan tidak pernah melempar.
 */
class DeprecatedCollectorTest extends TestCase {
    /** @var UjiDebug_DeprecatedCollector */
    protected $collector;

    /** @var array */
    protected $originalConfig = [];

    protected function setUp(): void {
        foreach (['collector.deprecated', 'collector.deprecatedPush', 'collector.deprecatedLimit'] as $key) {
            $this->originalConfig[$key] = CConfig::repository()->get($key);
        }
        CConfig::repository()->set('collector.deprecated', true);
        CConfig::repository()->set('collector.deprecatedPush', false);
        CConfig::repository()->set('collector.deprecatedLimit', 50);
        $this->collector = new UjiDebug_DeprecatedCollector();
        $property = new ReflectionProperty(CDebug_CollectorManager::class, 'deprecated');
        $property->setAccessible(true);
        $property->setValue(CDebug::collector(), $this->collector);
    }

    protected function tearDown(): void {
        foreach ($this->originalConfig as $key => $value) {
            CConfig::repository()->set($key, $value);
        }
        $property = new ReflectionProperty(CDebug_CollectorManager::class, 'deprecated');
        $property->setAccessible(true);
        $property->setValue(CDebug::collector(), null);
    }

    public function testCfDeprecatedRecordsTheApiAndTheCallerOutsideTheFramework() {
        $line = __LINE__ + 1;
        UjiDebug_LegacyApi::oldWay();

        $this->assertCount(1, $this->collector->stored);
        $data = $this->collector->stored[0];
        $this->assertSame('Deprecated', $data['error']);
        $this->assertSame('UjiDebug_LegacyApi::oldWay', $data['api']);
        $this->assertSame('UjiDebug_LegacyApi::newWay()', $data['replacement']);
        $this->assertSame('1.9', $data['since']);
        $this->assertSame('UjiDebug_LegacyApi::oldWay sudah deprecated sejak 1.9, pakai UjiDebug_LegacyApi::newWay()', $data['message']);
        $this->assertSame('UjiDebug_LegacyApi::oldWay', $data['deprecatedIn'], 'fungsi deprecated = frame pertama setelah kolektor');
        $this->assertSame(__FILE__, $data['file'], 'pemanggil = berkas di luar system/');
        $this->assertSame($line, $data['line']);
        $this->assertTrue($data['isCli']);
        $this->assertSame(CF::version(), $data['CFVersion']);
        $this->assertNotEmpty($data['uuid']);
        $this->assertSame('UjiDebug_LegacyApi::oldWay (' . __FILE__ . ':' . $line . ')', json_decode($data['trace'], true)[0], 'trace ringkas: fungsi (berkas:baris)');
        $this->assertArrayHasKey('datetime', $data);
    }

    public function testCollectDeprecatedWithAMessageOnlyDerivesTheApiFromTheDeprecatedFunction() {
        (new UjiDebug_LegacyApi())->oldMethod('oldMethod tidak dipakai lagi');

        $data = $this->collector->stored[0];
        $this->assertSame('UjiDebug_LegacyApi->oldMethod', $data['api']);
        $this->assertSame('UjiDebug_LegacyApi->oldMethod', $data['deprecatedIn']);
        $this->assertSame('oldMethod tidak dipakai lagi', $data['message']);
        $this->assertNull($data['replacement']);
        $this->assertSame(__FILE__, $data['file']);
    }

    public function testSameCallSiteIsCollectedOnceButDifferentLinesAreDistinct() {
        for ($i = 0; $i < 3; $i++) {
            UjiDebug_LegacyApi::oldWay();
        }
        $this->assertCount(1, $this->collector->stored, 'baris pemanggil yang sama = satu entri per proses');

        UjiDebug_LegacyApi::oldWay();
        $this->assertCount(2, $this->collector->stored, 'baris berbeda = entri baru');

        $this->collector->flush();
        UjiDebug_LegacyApi::oldWay();
        $this->assertCount(3, $this->collector->stored, 'flush() melupakan yang sudah dikumpulkan');
    }

    public function testLimitCapsUniqueEntriesPerProcess() {
        CConfig::repository()->set('collector.deprecatedLimit', 2);
        (new UjiDebug_LegacyApi())->oldMethod('a');
        (new UjiDebug_LegacyApi())->oldMethod('b');
        (new UjiDebug_LegacyApi())->oldMethod('c');

        $this->assertCount(2, $this->collector->stored);
    }

    public function testDisabledByConfig() {
        CConfig::repository()->set('collector.deprecated', false);
        $this->assertSame('lama', UjiDebug_LegacyApi::oldWay());
        $this->assertNull($this->collector->collect('x'));
        $this->assertSame([], $this->collector->stored);
        $this->assertSame([], $this->collector->pushed);
    }

    public function testPushToDevcloudTakesPrecedenceAndFallsBackToTheFile() {
        CConfig::repository()->set('collector.deprecatedPush', 'https://devcloud.test/v1/deprecations');
        UjiDebug_LegacyApi::oldWay();
        $this->assertCount(1, $this->collector->pushed);
        $this->assertSame('https://devcloud.test/v1/deprecations', $this->collector->pushed[0]['url']);
        $this->assertSame('UjiDebug_LegacyApi::oldWay', $this->collector->pushed[0]['data']['api']);
        $this->assertSame([], $this->collector->stored, 'push berhasil → tidak ditulis ke berkas');

        $this->collector->pushSucceeds = false;
        (new UjiDebug_LegacyApi())->oldMethod('gagal push');
        $this->assertCount(2, $this->collector->pushed);
        $this->assertCount(1, $this->collector->stored, 'push gagal → fallback berkas');
    }

    public function testCollectorNeverThrows() {
        $this->collector->failPut = true;
        $this->assertNull($this->collector->collect('apa pun'));
        $this->assertSame('lama', UjiDebug_LegacyApi::oldWay(), 'API yang deprecated tetap berjalan walau kolektor gagal');
    }

    public function testCemailSenderAndLinkedinDriverReportThemselves() {
        $originalSwitch = CConfig::repository()->get('email.legacy_sender_via_mailer');
        CConfig::repository()->set('email.legacy_sender_via_mailer', false);
        try {
            CEmail::sender(['driver' => 'null']);
            CEmail::sender(['smtp_host' => 'smtp.sendgrid.net', 'smtp_password' => 'k']);
            CSocialLogin::driver('linkedin', ['client_id' => 'a', 'client_secret' => 'b', 'redirect' => 'https://cb']);
        } finally {
            CConfig::repository()->set('email.legacy_sender_via_mailer', $originalSwitch);
        }

        $apis = array_column($this->collector->stored, 'api');
        $this->assertSame(['CEmail::sender', 'CEmail_Driver_NullDriver', 'CEmail::sender', 'CEmail_Config smtp_* options', 'CEmail_Driver_SendGridDriver', 'CSocialLogin driver linkedin'], $apis, 'fasad (per baris pemanggil), driver lama yang benar-benar dipakai, dan bentuk config smtp_* masing-masing dilaporkan');
        $this->assertSame('CEmail::mailer()', $this->collector->stored[0]['replacement']);
        $this->assertSame("CEmail::mailer() dengan transport 'sendgrid' (atau email.legacy_sender_via_mailer)", $this->collector->stored[4]['replacement']);
        foreach ([0, 1, 2, 3, 4, 5] as $i) {
            $this->assertSame(__FILE__, $this->collector->stored[$i]['file'], 'entri #' . $i . ': walau lewat beberapa lapis framework, pemanggil yang dicatat tetap kode di luar system/');
        }
        $this->assertSame('CSocialLogin_DriverManager->createLinkedinDriver', $this->collector->stored[5]['deprecatedIn']);
        $this->assertSame('CEmail_Factory::createDriver', $this->collector->stored[1]['deprecatedIn']);
        $this->assertSame('CEmail_Config->reformatOptions', $this->collector->stored[3]['deprecatedIn']);
    }

    public function testSenderThroughTheMailerAdapterOnlyReportsTheFacade() {
        $originalSwitch = CConfig::repository()->get('email.legacy_sender_via_mailer');
        CConfig::repository()->set('email.legacy_sender_via_mailer', true);
        try {
            CEmail::sender(['driver' => 'null']);
        } finally {
            CConfig::repository()->set('email.legacy_sender_via_mailer', $originalSwitch);
            CEmail_Sender_MailerDriver::forgetMailers();
        }

        $this->assertSame(['CEmail::sender'], array_column($this->collector->stored, 'api'), 'adaptor bukan jalur deprecated, hanya fasadnya yang dilaporkan');
    }

    public function testClassesAutoloadedFromModulesAreReported() {
        if (!is_file(DOCROOT . 'modules/cresenity/vendor/Facebook/FacebookBatchRequest.php')) {
            $this->markTestSkipped('modules/cresenity tidak ada di checkout ini');
        }
        if (class_exists('Facebook\\FacebookBatchRequest', false)) {
            $this->markTestSkipped('kelas sudah dimuat proses lain');
        }

        $line = __LINE__ + 1;
        $this->assertTrue(class_exists('Facebook\\FacebookBatchRequest'));

        $reports = array_values(array_filter($this->collector->stored, function ($data) {
            return strpos($data['api'], 'modules: ') === 0;
        }));
        $this->assertNotEmpty($reports, 'kelas dari modules/ dilaporkan');
        $this->assertSame('modules: Facebook\\FacebookBatchRequest', $reports[0]['api']);
        $this->assertStringContainsString('modules/cresenity/vendor/Facebook/FacebookBatchRequest.php', $reports[0]['message']);
        $this->assertSame(__FILE__, $reports[0]['file'], 'pemanggil = kode yang memicu autoload');
        $this->assertSame($line, $reports[0]['line']);
        $this->assertSame('1.9', $reports[0]['since']);

        $this->collector->stored = [];
        $this->assertTrue(class_exists('CEmail_Address'));
        $this->assertSame([], array_filter($this->collector->stored, function ($data) {
            return strpos($data['api'], 'modules: ') === 0;
        }), 'kelas dari system/ tidak dilaporkan');
    }

    public function testFileFallbackWritesOneJsonLinePerEntryUnderTempCollectorDeprecated() {
        $real = new CDebug_Collector_Deprecated();
        $path = DOCROOT . 'temp' . DS . 'collector' . DS . 'deprecated' . DS . date('Ymd') . '.txt';
        $before = is_file($path) ? count(file($path)) : 0;
        $property = new ReflectionProperty(CDebug_CollectorManager::class, 'deprecated');
        $property->setAccessible(true);
        $property->setValue(CDebug::collector(), $real);

        UjiDebug_LegacyApi::oldWay();

        $this->assertFileExists($path);
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount($before + 1, $lines);
        $decoded = json_decode(end($lines), true);
        $this->assertSame('UjiDebug_LegacyApi::oldWay', $decoded['api']);
        $this->assertSame(__FILE__, $decoded['file']);
    }
}
