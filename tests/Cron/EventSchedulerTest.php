<?php
use Mockery as m;
use PHPUnit\Framework\TestCase;

class EventSchedulerTest extends TestCase {
    /**
     * @var \CCron_Schedule
     */
    private $schedule;

    protected function setUp() {
        parent::setUp();

        $container = CContainer::getInstance();

        $container->instance(CCron_Contract_EventMutexInterface::class, m::mock(CCron_CacheEventMutex::class));

        $container->instance(CCron_Contract_SchedulingMutexInterface::class, m::mock(CCron_CacheSchedulingMutex::class));

        $container->instance(CCron_Schedule::class, $this->schedule = new CCron_Schedule(m::mock(CCron_Contract_EventMutexInterface::class)));
    }

    protected function tearDown() {
        m::close();
    }

    public function testMutexCanReceiveCustomStore() {
        CCron::schedule();
        CContainer::getInstance()->make(CCron_Contract_EventMutexInterface::class)->shouldReceive('useStore')->once()->with('test');
        CContainer::getInstance()->make(CCron_Contract_SchedulingMutexInterface::class)->shouldReceive('useStore')->once()->with('test');

        $this->schedule->useCache('test');
    }

    public function testExecCreatesNewCommand() {
        $escape = '\\' === DIRECTORY_SEPARATOR ? '"' : '\'';
        $escapeReal = '\\' === DIRECTORY_SEPARATOR ? '\\"' : '"';

        $schedule = $this->schedule;
        $schedule->exec('path/to/command');
        $schedule->exec('path/to/command -f --foo="bar"');
        $schedule->exec('path/to/command', ['-f']);
        $schedule->exec('path/to/command', ['--foo' => 'bar']);
        $schedule->exec('path/to/command', ['-f', '--foo' => 'bar']);
        $schedule->exec('path/to/command', ['--title' => 'A "real" test']);
        $schedule->exec('path/to/command', [['one', 'two']]);
        $schedule->exec('path/to/command', ['-1 minute']);
        $schedule->exec('path/to/command', ['foo' => ['bar', 'baz']]);
        $schedule->exec('path/to/command', ['--foo' => ['bar', 'baz']]);
        $schedule->exec('path/to/command', ['-F' => ['bar', 'baz']]);

        $events = $schedule->events();
        $this->assertSame('path/to/command', $events[0]->command);
        $this->assertSame('path/to/command -f --foo="bar"', $events[1]->command);
        $this->assertSame('path/to/command -f', $events[2]->command);
        $this->assertSame("path/to/command --foo={$escape}bar{$escape}", $events[3]->command);
        $this->assertSame("path/to/command -f --foo={$escape}bar{$escape}", $events[4]->command);
        $this->assertSame("path/to/command --title={$escape}A {$escapeReal}real{$escapeReal} test{$escape}", $events[5]->command);
        $this->assertSame("path/to/command {$escape}one{$escape} {$escape}two{$escape}", $events[6]->command);
        $this->assertSame("path/to/command {$escape}-1 minute{$escape}", $events[7]->command);
        $this->assertSame("path/to/command {$escape}bar{$escape} {$escape}baz{$escape}", $events[8]->command);
        $this->assertSame("path/to/command --foo={$escape}bar{$escape} --foo={$escape}baz{$escape}", $events[9]->command);
        $this->assertSame("path/to/command -F {$escape}bar{$escape} -F {$escape}baz{$escape}", $events[10]->command);
    }

    public function testExecCreatesNewCommandWithTimezone() {
        $schedule = new CCron_Schedule('UTC');
        $schedule->exec('path/to/command');
        $events = $schedule->events();
        $this->assertSame('UTC', $events[0]->timezone);

        $schedule = new CCron_Schedule('Asia/Tokyo');
        $schedule->exec('path/to/command');
        $events = $schedule->events();
        $this->assertSame('Asia/Tokyo', $events[0]->timezone);
    }

    public function testCommandCreatesNewArtisanCommand() {
        $escape = '\\' === DIRECTORY_SEPARATOR ? '"' : '\'';

        $schedule = $this->schedule;
        $schedule->command('queue:listen');
        $schedule->command('queue:listen --tries=3');
        $schedule->command('queue:listen', ['--tries' => 3]);

        $events = $schedule->events();
        $binary = $escape . PHP_BINARY . $escape;
        $artisan = $escape . 'artisan' . $escape;
        $this->assertEquals($binary . ' ' . $artisan . ' queue:listen', $events[0]->command);
        $this->assertEquals($binary . ' ' . $artisan . ' queue:listen --tries=3', $events[1]->command);
        $this->assertEquals($binary . ' ' . $artisan . ' queue:listen --tries=3', $events[2]->command);
    }

    public function testCreateNewArtisanCommandUsingCommandClass() {
        $escape = '\\' === DIRECTORY_SEPARATOR ? '"' : '\'';

        $schedule = $this->schedule;
        $schedule->command(ConsoleCommandStub::class, ['--force']);

        $events = $schedule->events();
        $binary = $escape . PHP_BINARY . $escape;
        $artisan = $escape . 'cf' . $escape;
        $this->assertEquals($binary . ' ' . $artisan . ' foo:bar --force', $events[0]->command);
    }

    public function testCallCreatesNewJobWithTimezone() {
        $schedule = new CCron_Schedule('UTC');
        $schedule->call('path/to/command');
        $events = $schedule->events();
        $this->assertSame('UTC', $events[0]->timezone);

        $schedule = new CCron_Schedule('Asia/Tokyo');
        $schedule->call('path/to/command');
        $events = $schedule->events();
        $this->assertSame('Asia/Tokyo', $events[0]->timezone);
    }
}
// @codingStandardsIgnoreStart
class FooClassStub {
    protected $schedule;

    public function __construct(CCron_Schedule $schedule) {
        $this->schedule = $schedule;
    }
}

class ConsoleCommandStub extends CConsole_Command {
    protected $signature = 'foo:bar';

    protected $foo;

    public function __construct(FooClassStub $foo) {
        parent::__construct();

        $this->foo = $foo;
    }
}
