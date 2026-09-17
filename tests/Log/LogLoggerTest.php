<?php

use Monolog\Logger as Monolog;
use PHPUnit\Framework\TestCase;
use Monolog\Handler\TestHandler;

/**
 * CLogger_Logger - pembungkus PSR logger + event dispatcher, padanan suite hulu untuk Logger.
 * Dipakai handler uji Monolog sungguhan, bukan mock, supaya yang diuji jalur tulisnya.
 */
class LogLoggerTest extends TestCase {
    /**
     * @var TestHandler
     */
    private $handler;

    /**
     * @param null|CEvent_Dispatcher $events
     *
     * @return CLogger_Logger
     */
    private function logger($events = null) {
        $monolog = new Monolog('test');
        $this->handler = new TestHandler();
        $monolog->pushHandler($this->handler);

        return new CLogger_Logger($monolog, $events);
    }

    /**
     * @return array
     */
    private function lastRecord() {
        $records = $this->handler->getRecords();

        return end($records);
    }

    public function testMethodsPassThroughToMonolog() {
        $writer = $this->logger();
        $writer->error('foo');

        $this->assertTrue($this->handler->hasErrorRecords());
        $record = $this->lastRecord();
        $this->assertSame('foo', $record['message']);
        $this->assertSame([], $record['context']);
    }

    public function testEveryLevelMethodWritesAtItsLevel() {
        $writer = $this->logger();
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
            $writer->{$level}('pesan ' . $level);
            $this->assertSame(strtoupper($level), $this->lastRecord()['level_name'], $level);
        }

        $writer->log('warning', 'lewat log()');
        $this->assertSame('WARNING', $this->lastRecord()['level_name']);
        $writer->write('notice', 'lewat write()');
        $this->assertSame('NOTICE', $this->lastRecord()['level_name']);
    }

    public function testContextIsAddedToAllSubsequentLogs() {
        $writer = $this->logger();
        $writer->withContext(['bar' => 'baz']);

        $writer->error('foo');
        $this->assertSame(['bar' => 'baz'], $this->lastRecord()['context']);

        $writer->info('lagi', ['extra' => 1]);
        $this->assertSame(['bar' => 'baz', 'extra' => 1], $this->lastRecord()['context']);
    }

    public function testContextIsFlushed() {
        $writer = $this->logger();
        $writer->withContext(['bar' => 'baz']);
        $writer->withoutContext();

        $writer->error('foo');
        $this->assertSame([], $this->lastRecord()['context']);
    }

    public function testWithContextMergesAndOverrides() {
        $writer = $this->logger();
        $writer->withContext(['user_id' => 123, 'action' => 'login']);
        $writer->withContext(['ip' => '127.0.0.1', 'action' => 'logout']);

        $writer->info('User action');
        $this->assertSame(['user_id' => 123, 'action' => 'logout', 'ip' => '127.0.0.1'], $this->lastRecord()['context']);
    }

    public function testLoggerFiresEventsDispatcher() {
        $events = new CEvent_Dispatcher();
        $writer = $this->logger($events);

        $captured = [];
        $events->listen(CLogger_Event_MessageLogged::class, function ($event) use (&$captured) {
            $captured = [$event->level, $event->message, $event->context];
        });

        $writer->error('foo', ['a' => 1]);
        $this->assertSame(['error', 'foo', ['a' => 1]], $captured);
    }

    public function testListenShortcutFailsWithNoDispatcher() {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Events dispatcher has not been set.');

        $this->logger()->listen(function () {
        });
    }

    public function testListenShortcut() {
        $events = new CEvent_Dispatcher();
        $writer = $this->logger($events);

        $seen = null;
        $writer->listen(function (CLogger_Event_MessageLogged $event) use (&$seen) {
            $seen = $event->message;
        });

        $writer->debug('lewat listen');
        $this->assertSame('lewat listen', $seen);
    }

    public function testArrayAndArrayableMessagesAreExported() {
        $writer = $this->logger();

        $writer->info(['a' => 1]);
        $this->assertSame(var_export(['a' => 1], true), $this->lastRecord()['message']);

        $writer->info(new LogLoggerTestArrayable());
        $this->assertSame(var_export(['serialized' => 'data'], true), $this->lastRecord()['message']);

        $writer->info(new LogLoggerTestJsonable());
        $this->assertSame('{"json":true}', $this->lastRecord()['message']);
    }

    public function testUnknownMethodsAreForwardedToTheUnderlyingLogger() {
        $writer = $this->logger();
        $this->assertSame('test', $writer->getName());
        $this->assertInstanceOf(Monolog::class, $writer->getLogger());
    }

    public function testEventDispatcherCanBeSetLater() {
        $writer = $this->logger();
        $this->assertNull($writer->getEventDispatcher());

        $events = new CEvent_Dispatcher();
        $writer->setEventDispatcher($events);
        $this->assertSame($events, $writer->getEventDispatcher());
    }
}

class LogLoggerTestArrayable implements CInterface_Arrayable {
    public function toArray() {
        return ['serialized' => 'data'];
    }
}

class LogLoggerTestJsonable implements CInterface_Jsonable {
    public function toJson($options = 0) {
        return '{"json":true}';
    }
}
