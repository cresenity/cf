<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class QueueFailedJobCommandsTestJob {
    public function retryUntil() {
        return 1999999999;
    }
}

/**
 * queue:retry, retry-batch, forget, flush, prune-failed di atas failer database SQLite.
 */
class QueueFailedJobCommandsTest extends TestCase {
    const TABLE = 'queue_failed';

    /**
     * @var array payload yang di-pushRaw, [payload, queue]
     */
    public $pushed = [];

    /**
     * @var CDatabase_Connection_Pdo_SqliteConnection
     */
    protected $connection;

    /**
     * @var CQueue_FailedJob_DatabaseFailedJob
     */
    protected $failer;

    private $original = [];

    protected function setUp(): void {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite tidak tersedia pada PHP CLI yang aktif.');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection = new CDatabase_Connection_Pdo_SqliteConnection($pdo, '', '', ['driver' => 'sqlite']);
        $this->connection->setEventDispatcher(new CEvent_Dispatcher());
        $this->connection->getSchemaBuilder()->create(static::TABLE, function (CDatabase_Schema_Blueprint $table) {
            $table->increments(static::TABLE . '_id');
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->dateTime('failed_at');
        });
        $this->failer = new CQueue_FailedJob_DatabaseFailedJob($this->connection, static::TABLE);

        $test = $this;
        $queue = new class($test) extends CQueue_Queue_NullQueue {
            private $test;

            public function __construct($test) {
                $this->test = $test;
            }

            public function pushRaw($payload, $queue = null, array $options = []) {
                $this->test->pushed[] = [json_decode($payload, true), $queue];
            }
        };
        $this->swap(CQueue::class, 'failer', $this->failer);
        $this->swap(CQueue::class, 'batchRepository', null);
        $connections = new ReflectionProperty(CQueue_Manager::class, 'connections');
        $connections->setAccessible(true);
        $all = $connections->getValue(CQueue::queuer());
        $this->original['connections'] = $all;
        $all['cf-test-retry'] = $queue;
        $connections->setValue(CQueue::queuer(), $all);
    }

    protected function tearDown(): void {
        foreach (['failer', 'batchRepository'] as $property) {
            if (array_key_exists($property, $this->original)) {
                $reflection = new ReflectionProperty(CQueue::class, $property);
                $reflection->setAccessible(true);
                $reflection->setValue(null, $this->original[$property]);
            }
        }
        if (isset($this->original['connections'])) {
            $connections = new ReflectionProperty(CQueue_Manager::class, 'connections');
            $connections->setAccessible(true);
            $connections->setValue(CQueue::queuer(), $this->original['connections']);
        }
    }

    private function swap($class, $property, $value) {
        $reflection = new ReflectionProperty($class, $property);
        $reflection->setAccessible(true);
        if (!array_key_exists($property, $this->original)) {
            $this->original[$property] = $reflection->getValue();
        }
        $reflection->setValue(null, $value);
    }

    private function logFailure($queue = 'default', $uuid = null, $failedAt = null) {
        $payload = json_encode([
            'uuid' => $uuid ?: uniqid('uuid-'),
            'job' => 'CQueue_CallQueuedHandler@call',
            'attempts' => 3,
            'data' => ['command' => serialize(new QueueFailedJobCommandsTestJob())],
        ]);
        $id = $this->failer->log('cf-test-retry', $queue, $payload, new RuntimeException('meledak'));
        if ($failedAt) {
            $this->connection->table(static::TABLE)->where(static::TABLE . '_id', $id)->update(['failed_at' => $failedAt]);
        }

        return $id;
    }

    /**
     * @return array [exit code, output]
     */
    private function runCommand(CConsole_Command $command, array $input = []) {
        $output = new BufferedOutput();
        $code = $command->run(new ArrayInput($input), $output);

        return [$code, $output->fetch()];
    }

    public function testRetryPushesTheJobBackWithAttemptsResetAndRetryUntilRefreshed() {
        $id = $this->logFailure('emails');

        list(, $output) = $this->runCommand(new CConsole_Command_Queue_RetryCommand(), ['id' => [(string) $id]]);

        $this->assertCount(1, $this->pushed);
        list($payload, $queue) = $this->pushed[0];
        $this->assertSame('emails', $queue);
        $this->assertSame(0, $payload['attempts']);
        $this->assertSame(1999999999, $payload['retryUntil']);
        $this->assertNull($this->failer->find($id));
        $this->assertStringContainsString("Failed job [{$id}] pushed back", $output);
    }

    public function testRetryAllByQueueAndByRange() {
        $a = $this->logFailure('a');
        $b = $this->logFailure('b');
        $c = $this->logFailure('b');

        $this->runCommand(new CConsole_Command_Queue_RetryCommand(), ['--queue' => 'b']);
        $this->assertSame(['b', 'b'], array_column($this->pushed, 1));
        $this->assertSame([$a], array_map('intval', $this->failer->ids()));

        $this->pushed = [];
        $d = $this->logFailure('c');
        $this->runCommand(new CConsole_Command_Queue_RetryCommand(), ['--range' => [$a . '-' . $d]]);
        $this->assertCount(2, $this->pushed);
        $this->assertSame(0, $this->failer->count());

        $this->pushed = [];
        $this->logFailure();
        $this->logFailure();
        $this->runCommand(new CConsole_Command_Queue_RetryCommand(), ['id' => ['all']]);
        $this->assertCount(2, $this->pushed);
    }

    public function testRetryReportsAMissingJob() {
        list(, $output) = $this->runCommand(new CConsole_Command_Queue_RetryCommand(), ['id' => ['999']]);

        $this->assertSame([], $this->pushed);
        $this->assertStringContainsString('Unable to find failed job with ID [999].', $output);
    }

    public function testRetryBatchResolvesTheFailedJobUuids() {
        $this->logFailure('default', 'uuid-satu');
        $this->logFailure('default', 'uuid-lain');
        $this->swap(CQueue::class, 'batchRepository', new class() {
            public function find($id) {
                return $id === 'batch-1' ? (object) ['failedJobIds' => ['uuid-satu', 'uuid-hilang']] : null;
            }
        });

        list($code, $output) = $this->runCommand(new CConsole_Command_Queue_RetryBatchCommand(), ['id' => ['batch-1']]);

        $this->assertSame(0, $code);
        $this->assertCount(1, $this->pushed);
        $this->assertSame('uuid-satu', $this->pushed[0][0]['uuid']);
        $this->assertSame(1, $this->failer->count());
        $this->assertStringContainsString('Unable to find failed job with UUID [uuid-hilang].', $output);

        list($code, $output) = $this->runCommand(new CConsole_Command_Queue_RetryBatchCommand(), ['id' => ['batch-x']]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Unable to find a batch with ID [batch-x].', $output);
    }

    public function testForget() {
        $id = $this->logFailure();

        $this->assertSame(0, $this->runCommand(new CConsole_Command_Queue_ForgetFailedCommand(), ['id' => (string) $id])[0]);
        $this->assertNull($this->failer->find($id));
        $this->assertSame(1, $this->runCommand(new CConsole_Command_Queue_ForgetFailedCommand(), ['id' => (string) $id])[0]);
    }

    public function testFlushKeepsRecentFailuresWhenHoursIsGiven() {
        $old = $this->logFailure('default', null, (string) c::now()->subHours(50));
        $recent = $this->logFailure();

        $this->runCommand(new CConsole_Command_Queue_FlushFailedCommand(), ['--hours' => '48']);
        $this->assertNull($this->failer->find($old));
        $this->assertNotNull($this->failer->find($recent));

        $this->runCommand(new CConsole_Command_Queue_FlushFailedCommand());
        $this->assertSame(0, $this->failer->count());
    }

    public function testPruneFailed() {
        $this->logFailure('default', null, (string) c::now()->subHours(30));
        $this->logFailure('default', null, (string) c::now()->subHours(30));
        $recent = $this->logFailure();

        list($code, $output) = $this->runCommand(new CConsole_Command_Queue_PruneFailedJobsCommand());

        $this->assertSame(0, $code);
        $this->assertStringContainsString('2 entries deleted.', $output);
        $this->assertSame([$recent], array_map('intval', $this->failer->ids()));
    }

    public function testCountByConnectionAndQueue() {
        $this->logFailure('a');
        $this->logFailure('b');

        $this->assertSame(2, $this->failer->count());
        $this->assertSame(1, $this->failer->count('cf-test-retry', 'a'));
        $this->assertSame(0, $this->failer->count('lain'));
    }
}
