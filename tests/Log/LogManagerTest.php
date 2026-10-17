<?php

use Monolog\Logger as Monolog;
use PHPUnit\Framework\TestCase;
use Monolog\Handler\TestHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * CLogger_Manager - padanan suite hulu untuk LogManager, dibatasi pada channel yang dibangun
 * on-demand lewat build() dan konfigurasi `log.channels` yang disetel sendiri di test, supaya
 * tidak menulis ke logs/ aplikasi.
 */
class LogManagerTest extends TestCase {
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var array
     */
    private $originalChannels;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-log-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->originalChannels = CConfig::repository()->get('log.channels');
    }

    protected function tearDown(): void {
        CConfig::repository()->set('log.channels', $this->originalChannels);
        foreach (['cf-test-single', 'cf-test-daily', 'cf-test-custom', 'cf-test-stack', 'cf-test-a', 'cf-test-b'] as $channel) {
            CLogger_Manager::instance()->forgetChannel($channel);
        }
        CLogger_Manager::instance()->flushSharedContext();
        CFile::deleteDirectory($this->tempDir);
    }

    /**
     * @param string $name
     */
    private function defineChannel($name, array $config) {
        CConfig::repository()->set('log.channels.' . $name, $config);
    }

    public function testBuildCreatesAnOnDemandSingleChannel() {
        $logger = CLogger_Manager::instance()->build([
            'driver' => 'single',
            'path' => $this->tempDir . '/single.log',
            'level' => 'debug',
        ]);

        $this->assertInstanceOf(CLogger_Logger::class, $logger);
        $this->assertInstanceOf(Monolog::class, $logger->getLogger());
        $this->assertInstanceOf(StreamHandler::class, $logger->getHandlers()[0]);

        $logger->info('halo dari build');
        $this->assertStringContainsString('halo dari build', file_get_contents($this->tempDir . '/single.log'));
    }

    public function testBuildReplacesThePreviousOnDemandChannel() {
        $manager = CLogger_Manager::instance();
        $first = $manager->build(['driver' => 'single', 'path' => $this->tempDir . '/a.log']);
        $second = $manager->build(['driver' => 'single', 'path' => $this->tempDir . '/b.log']);

        //build() membuang channel 'ondemand' sebelumnya, jadi tiap panggilan menghasilkan logger baru
        $this->assertNotSame($first, $second);
        $this->assertSame($second, $manager->getChannels()['ondemand']);
        $manager->forgetChannel('ondemand');
    }

    public function testConfiguredSingleChannelIsResolvedOnceAndCached() {
        $this->defineChannel('cf-test-single', ['driver' => 'single', 'path' => $this->tempDir . '/single.log', 'level' => 'debug']);
        $manager = CLogger_Manager::instance();

        $logger = $manager->channel('cf-test-single');
        $this->assertSame($logger, $manager->channel('cf-test-single'));
        $this->assertSame($logger, $manager->driver('cf-test-single'));
        $this->assertArrayHasKey('cf-test-single', $manager->getChannels());

        $logger->warning('tulis sekali');
        $this->assertStringContainsString('WARNING: tulis sekali', file_get_contents($this->tempDir . '/single.log'));
    }

    public function testDailyChannelUsesARotatingHandler() {
        $this->defineChannel('cf-test-daily', ['driver' => 'daily', 'path' => $this->tempDir . '/daily.log', 'days' => 3]);

        $logger = CLogger_Manager::instance()->channel('cf-test-daily');
        $this->assertInstanceOf(RotatingFileHandler::class, $logger->getHandlers()[0]);

        $logger->error('per hari');
        $this->assertFileExists($this->tempDir . '/daily-' . date('Y-m-d') . '.log');
    }

    public function testLevelIsRespected() {
        $this->defineChannel('cf-test-single', ['driver' => 'single', 'path' => $this->tempDir . '/level.log', 'level' => 'error']);
        $logger = CLogger_Manager::instance()->channel('cf-test-single');

        $logger->info('tidak masuk');
        $logger->error('masuk');

        $content = file_get_contents($this->tempDir . '/level.log');
        $this->assertStringNotContainsString('tidak masuk', $content);
        $this->assertStringContainsString('masuk', $content);
    }

    public function testStackCombinesTheHandlersOfItsChannels() {
        $this->defineChannel('cf-test-a', ['driver' => 'single', 'path' => $this->tempDir . '/a.log']);
        $this->defineChannel('cf-test-b', ['driver' => 'single', 'path' => $this->tempDir . '/b.log']);
        $this->defineChannel('cf-test-stack', ['driver' => 'stack', 'channels' => ['cf-test-a', 'cf-test-b']]);

        $logger = CLogger_Manager::instance()->channel('cf-test-stack');
        $this->assertCount(2, $logger->getHandlers());

        $logger->info('ke dua-duanya');
        $this->assertStringContainsString('ke dua-duanya', file_get_contents($this->tempDir . '/a.log'));
        $this->assertStringContainsString('ke dua-duanya', file_get_contents($this->tempDir . '/b.log'));
    }

    public function testStackMethodBuildsAnAdHocStack() {
        $this->defineChannel('cf-test-a', ['driver' => 'single', 'path' => $this->tempDir . '/a.log']);
        $this->defineChannel('cf-test-b', ['driver' => 'single', 'path' => $this->tempDir . '/b.log']);

        $logger = CLogger_Manager::instance()->stack(['cf-test-a', 'cf-test-b']);
        $logger->info('stack langsung');

        $this->assertStringContainsString('stack langsung', file_get_contents($this->tempDir . '/a.log'));
        $this->assertStringContainsString('stack langsung', file_get_contents($this->tempDir . '/b.log'));
    }

    public function testCustomDriverViaCallable() {
        $handler = new TestHandler();
        $this->defineChannel('cf-test-custom', [
            'driver' => 'custom',
            'via' => function (array $config) use ($handler) {
                return new Monolog('custom-' . $config['name'], [$handler]);
            },
            'name' => 'x',
        ]);

        $logger = CLogger_Manager::instance()->channel('cf-test-custom');
        $this->assertSame('custom-x', $logger->getName());

        $logger->notice('lewat custom');
        $this->assertTrue($handler->hasNoticeThatContains('lewat custom'));
    }

    public function testExtendRegistersADriverFactory() {
        $handler = new TestHandler();
        $manager = CLogger_Manager::instance();
        $manager->extend('cf-test-driver', function (array $config) use ($handler) {
            return new Monolog('extended', [$handler]);
        });
        $this->defineChannel('cf-test-single', ['driver' => 'cf-test-driver']);

        $logger = $manager->channel('cf-test-single');
        $logger->info('lewat extend');
        $this->assertTrue($handler->hasInfoThatContains('lewat extend'));
    }

    public function testForgetChannelDropsTheCachedInstance() {
        $this->defineChannel('cf-test-single', ['driver' => 'single', 'path' => $this->tempDir . '/single.log']);
        $manager = CLogger_Manager::instance();

        $first = $manager->channel('cf-test-single');
        $manager->forgetChannel('cf-test-single');
        $this->assertArrayNotHasKey('cf-test-single', $manager->getChannels());
        $this->assertNotSame($first, $manager->channel('cf-test-single'));
    }

    public function testSharedContextIsAppliedToExistingAndFutureChannels() {
        $this->defineChannel('cf-test-a', ['driver' => 'single', 'path' => $this->tempDir . '/a.log']);
        $this->defineChannel('cf-test-b', ['driver' => 'single', 'path' => $this->tempDir . '/b.log']);
        $manager = CLogger_Manager::instance();

        $existing = $manager->channel('cf-test-a');
        $manager->shareContext(['request_id' => 'abc123']);
        $this->assertSame(['request_id' => 'abc123'], $manager->sharedContext());

        $existing->info('sudah ada');
        $manager->channel('cf-test-b')->info('baru');

        $this->assertStringContainsString('"request_id":"abc123"', file_get_contents($this->tempDir . '/a.log'));
        $this->assertStringContainsString('"request_id":"abc123"', file_get_contents($this->tempDir . '/b.log'));

        $manager->flushSharedContext();
        $this->assertSame([], $manager->sharedContext());
    }

    public function testUnknownDriverFallsBackToTheEmergencyLogger() {
        $this->defineChannel('cf-test-single', ['driver' => 'tidak-ada']);

        //kesalahan konfigurasi log tidak boleh menjatuhkan request: yang dikembalikan logger darurat
        $logger = CLogger_Manager::instance()->channel('cf-test-single');
        $this->assertInstanceOf(CLogger_Logger::class, $logger);
        $this->assertArrayNotHasKey('cf-test-single', CLogger_Manager::instance()->getChannels());
    }

    public function testActionLevelWrapsTheHandlerInFingersCrossed() {
        $this->defineChannel('cf-test-single', [
            'driver' => 'single',
            'path' => $this->tempDir . '/fingers.log',
            'action_level' => 'error',
        ]);

        $logger = CLogger_Manager::instance()->channel('cf-test-single');
        $this->assertArrayHasKey('cf-test-single', CLogger_Manager::instance()->getChannels(), 'bukan emergency logger');
        $this->assertInstanceOf(FingersCrossedHandler::class, $logger->getHandlers()[0]);

        //info ditahan sampai ada log selevel action_level
        $logger->info('info tertahan');
        $this->assertFileDoesNotExist($this->tempDir . '/fingers.log');

        $logger->error('pemicu');
        $content = file_get_contents($this->tempDir . '/fingers.log');
        $this->assertStringContainsString('info tertahan', $content);
        $this->assertStringContainsString('pemicu', $content);
    }

    public function testInvalidActionLevelFallsBackToTheEmergencyLogger() {
        $this->defineChannel('cf-test-single', [
            'driver' => 'single',
            'path' => $this->tempDir . '/fingers.log',
            'action_level' => 'tidak-ada',
        ]);

        CLogger_Manager::instance()->channel('cf-test-single');
        $this->assertArrayNotHasKey('cf-test-single', CLogger_Manager::instance()->getChannels());
    }

    public function testNoticeCanBeCalledStatically() {
        $this->defineChannel('cf-test-single', ['driver' => 'single', 'path' => $this->tempDir . '/notice.log']);
        $originalDefault = CConfig::repository()->get('log.default');
        CConfig::repository()->set('log.default', 'cf-test-single');

        try {
            CLogger::notice('pemberitahuan');
        } finally {
            CConfig::repository()->set('log.default', $originalDefault);
        }

        $this->assertStringContainsString('NOTICE: pemberitahuan', file_get_contents($this->tempDir . '/notice.log'));
    }

    public function testUnknownIntegerLevelIsRejected() {
        $this->expectException(InvalidArgumentException::class);
        CLogger::log(99, 'level aneh');
    }

    public function testSyslogIdentFallsBackToTheAppCode() {
        $logger = CLogger_Manager::instance()->build(['driver' => 'syslog']);
        $ident = new ReflectionProperty(get_class($logger->getHandlers()[0]), 'ident');
        $ident->setAccessible(true);

        $this->assertNotSame('', $ident->getValue($logger->getHandlers()[0]));
        $this->assertSame(cstr::snake(CF::config('app.name') ?: CF::appCode(), '-'), $ident->getValue($logger->getHandlers()[0]));
    }

    public function testChannelWithoutADriverFallsBackToTheEmergencyLoggerWithoutAWarning() {
        $this->defineChannel('cf-test-single', ['path' => $this->tempDir . '/x.log']);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        try {
            $logger = CLogger_Manager::instance()->channel('cf-test-single');
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
        $this->assertInstanceOf(CLogger_Logger::class, $logger);
        $this->assertArrayNotHasKey('cf-test-single', CLogger_Manager::instance()->getChannels());
    }

    public function testReplacePlaceholders() {
        $manager = CLogger_Manager::instance();
        $manager->build(['driver' => 'single', 'path' => $this->tempDir . '/ph.log', 'replace_placeholders' => true])
            ->info('halo {name}', ['name' => 'budi']);
        $manager->build(['driver' => 'daily', 'path' => $this->tempDir . '/ph-daily.log', 'replace_placeholders' => true])
            ->info('halo {name}', ['name' => 'ani']);
        $manager->build(['driver' => 'single', 'path' => $this->tempDir . '/raw.log'])
            ->info('halo {name}', ['name' => 'budi']);
        $manager->forgetChannel('ondemand');

        $this->assertStringContainsString('halo budi', file_get_contents($this->tempDir . '/ph.log'));
        $this->assertStringContainsString('halo ani', file_get_contents($this->tempDir . '/ph-daily-' . date('Y-m-d') . '.log'));
        $this->assertStringContainsString('halo {name}', file_get_contents($this->tempDir . '/raw.log'));
    }

    public function testMonologDriverAcceptsProcessors() {
        $logger = CLogger_Manager::instance()->build([
            'driver' => 'monolog',
            'handler' => TestHandler::class,
            'processors' => [
                PsrLogMessageProcessor::class,
                ['processor' => PsrLogMessageProcessor::class, 'with' => ['removeUsedContextFields' => true]],
            ],
        ]);
        CLogger_Manager::instance()->forgetChannel('ondemand');

        $processors = $logger->getLogger()->getProcessors();
        $this->assertCount(2, $processors);
        $this->assertContainsOnlyInstancesOf(PsrLogMessageProcessor::class, $processors);
    }

    public function testErrorlogDriverHonoursTheChannelConfig() {
        $logger = CLogger_Manager::instance()->build(['driver' => 'errorlog', 'action_level' => 'error', 'replace_placeholders' => true]);
        CLogger_Manager::instance()->forgetChannel('ondemand');

        $this->assertInstanceOf(FingersCrossedHandler::class, $logger->getHandlers()[0]);
        $this->assertCount(1, $logger->getLogger()->getProcessors());
    }
}
