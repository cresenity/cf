<?php

use Monolog\Logger as Monolog;
use PHPUnit\Framework\TestCase;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

/**
 * Path log default logs/<appCode>/<Y>/<m>/ harus mengikuti pergantian bulan pada proses yang hidup lama.
 */
class LogMonthlyDirectoryTest extends TestCase {
    /**
     * @var string
     */
    private $tempDir;

    /**
     * @var array
     */
    private $originalChannels;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-log-monthly-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->originalChannels = CConfig::repository()->get('log.channels');
    }

    protected function tearDown(): void {
        CConfig::repository()->set('log.channels', $this->originalChannels);
        foreach (['cf-test-monthly-single', 'cf-test-monthly-daily'] as $channel) {
            CLogger_Manager::instance()->forgetChannel($channel);
        }
        CFile::deleteDirectory($this->tempDir);
    }

    /**
     * @param object $object
     * @param string $property
     * @param mixed  $value
     */
    private function setProperty($object, $property, $value) {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }

    private function currentMonthDir() {
        return $this->tempDir . DS . date('Y') . DS . date('m');
    }

    public function testSingleMovesToTheCurrentMonthFolder() {
        $handler = new CLogger_Handler_MonthlyStreamHandler($this->tempDir, 'log.log');
        //proses yang start di bulan lain
        $this->setProperty($handler, 'url', $this->tempDir . DS . '2000' . DS . '01' . DS . 'log.log');

        (new Monolog('test', [$handler]))->info('pesan bulan ini');

        $this->assertStringContainsString('pesan bulan ini', file_get_contents($this->currentMonthDir() . DS . 'log.log'));
        $this->assertDirectoryDoesNotExist($this->tempDir . DS . '2000');
    }

    public function testDailyMovesToTheCurrentMonthFolderOnRotation() {
        $handler = new CLogger_Handler_MonthlyRotatingFileHandler($this->tempDir, 'log.log', 14);
        $oldPath = $this->tempDir . DS . '2000' . DS . '01' . DS . 'log.log';
        $this->setProperty($handler, 'filename', $oldPath);
        $this->setProperty($handler, 'url', $this->tempDir . DS . '2000' . DS . '01' . DS . 'log-2000-01-31.log');
        $this->setProperty($handler, 'nextRotation', new DateTimeImmutable('2000-02-01'));
        $this->setProperty($handler, 'mustRotate', false);

        (new Monolog('test', [$handler]))->info('pesan bulan ini');

        $file = $this->currentMonthDir() . DS . 'log-' . date('Y-m-d') . '.log';
        $this->assertStringContainsString('pesan bulan ini', file_get_contents($file));
        $this->assertDirectoryDoesNotExist($this->tempDir . DS . '2000');
    }

    public function testDefaultPathUsesTheMonthlyHandlersAndExplicitPathDoesNot() {
        CConfig::repository()->set('log.channels.cf-test-monthly-single', ['driver' => 'single']);
        CConfig::repository()->set('log.channels.cf-test-monthly-daily', ['driver' => 'daily', 'path' => $this->tempDir . '/x.log']);

        $single = CLogger::channel('cf-test-monthly-single')->getLogger()->getHandlers()[0];
        $this->assertInstanceOf(CLogger_Handler_MonthlyStreamHandler::class, $single);
        $this->assertSame(DOCROOT . 'logs' . DS . CF::appCode() . DS . date('Y') . DS . date('m') . DS . 'log.log', $single->getUrl());

        $daily = CLogger::channel('cf-test-monthly-daily')->getLogger()->getHandlers()[0];
        $this->assertInstanceOf(RotatingFileHandler::class, $daily);
        $this->assertNotInstanceOf(CLogger_Handler_MonthlyRotatingFileHandler::class, $daily);

        $this->assertInstanceOf(StreamHandler::class, CLogger_Manager::instance()->build(['driver' => 'single', 'path' => $this->tempDir . '/y.log'])->getLogger()->getHandlers()[0]);
        $this->assertNotInstanceOf(CLogger_Handler_MonthlyStreamHandler::class, CLogger_Manager::instance()->build(['driver' => 'single', 'path' => $this->tempDir . '/y.log'])->getLogger()->getHandlers()[0]);
    }
}
