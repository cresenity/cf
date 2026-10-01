<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Exception\AssertionFailedError;

class QueueFakeHelpersJob implements CQueue_ShouldQueueInterface {
    use CQueue_Trait_InteractsWithQueue;
    use CQueue_Trait_QueueableTrait;
    use CQueue_Trait_BatchableTrait;

    /** @var string */
    public $name;

    /** @var null|callable */
    public $behaviour;

    /**
     * @param string        $name
     * @param null|callable $behaviour
     */
    public function __construct($name = 'x', $behaviour = null) {
        $this->name = $name;
        $this->behaviour = $behaviour;
    }

    public function handle() {
        if ($this->behaviour) {
            return call_user_func($this->behaviour, $this);
        }
    }
}

class QueueFakeHelpersOtherJob extends QueueFakeHelpersJob {
}

class QueueFakeHelpersRealQueue {
    /** @var array */
    public $pushed = [];

    public function push($job, $data = '', $queue = null) {
        $this->pushed[] = $job;
    }
}

class QueueFakeHelpersDispatcher implements CQueue_QueueingDispatcherInterface {
    /** @var array */
    public $seen = [];

    public function dispatch($command) {
        $this->seen[] = get_class($command);
    }

    public function dispatchSync($command, $handler = null) {
        $this->seen[] = 'sync:' . get_class($command);
    }

    public function dispatchNow($command, $handler = null) {
    }

    public function hasCommandHandler($command) {
        return false;
    }

    public function getCommandHandler($command) {
        return false;
    }

    public function pipeThrough(array $pipes) {
        return $this;
    }

    public function map(array $map) {
        return $this;
    }

    public function dispatchToQueue($command) {
    }

    public function dispatchAfterResponse($command) {
    }

    public function findBatch(string $batchId) {
        return null;
    }

    public function batch($jobs) {
    }
}

/**
 * Alat bantu unit test job: FakeJob, withFakeQueueInteractions(), withFakeBatch(), serta tambahan QueueManagerFake dan BusFake.
 */
class QueueFakeHelpersTest extends TestCase {
    /**
     * @param callable $assertion
     * @param string   $needle
     *
     * @return void
     */
    protected function assertFails(callable $assertion, $needle = '') {
        try {
            $assertion();
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString($needle, $e->getMessage());

            return;
        }

        $this->fail('assertion seharusnya gagal');
    }

    // ---- FakeJob ----

    public function testFakeJobStartsCleanAndRecordsInteractions() {
        $job = new CQueue_Job_FakeJob();

        $this->assertFalse($job->isDeleted());
        $this->assertFalse($job->isReleased());
        $this->assertFalse($job->hasFailed());
        $this->assertSame(1, $job->attempts());
        $this->assertSame($job->getJobId(), $job->getJobId(), 'id stabil per instance');
        $this->assertSame('', $job->getRawBody());

        $job->release(45);
        $job->delete();
        $job->fail(new RuntimeException('gagal'));

        $this->assertTrue($job->isReleased());
        $this->assertSame(45, $job->releaseDelay);
        $this->assertTrue($job->isDeleted());
        $this->assertTrue($job->hasFailed());
        $this->assertSame('gagal', $job->failedWith->getMessage());
    }

    public function testFakeJobFailDoesNotRunJobFailedHookOrDispatchEvents() {
        $dispatched = [];
        CEvent::dispatcher()->listen(CQueue_Event_JobFailed::class, function () use (&$dispatched) {
            $dispatched[] = 'jobfailed';
        });

        (new CQueue_Job_FakeJob())->fail();

        $this->assertSame([], $dispatched);
        CEvent::dispatcher()->forget(CQueue_Event_JobFailed::class);
    }

    // ---- withFakeQueueInteractions ----

    public function testReleasedJobWithDelay() {
        $job = new QueueFakeHelpersJob('a', function (QueueFakeHelpersJob $self) {
            $self->release(30);
        });

        $job->withFakeQueueInteractions()->handle();

        $job->assertReleased()->assertReleased(30)->assertNotDeleted()->assertNotFailed();
        $this->assertFails(function () use ($job) {
            $job->assertReleased(60);
        }, 'released with delay of [30]');
        $this->assertFails(function () use ($job) {
            $job->assertNotReleased();
        }, 'released unexpectedly');
        $this->assertFails(function () use ($job) {
            $job->assertDeleted();
        }, 'was not deleted');
    }

    public function testDeletedJob() {
        $job = new QueueFakeHelpersJob('a', function (QueueFakeHelpersJob $self) {
            $self->delete();
        });

        $job->withFakeQueueInteractions()->handle();

        $job->assertDeleted()->assertNotReleased()->assertNotFailed();
        $this->assertFails(function () use ($job) {
            $job->assertNotDeleted();
        }, 'deleted unexpectedly');
    }

    public function testFailedJobAndFailedWith() {
        $job = new QueueFakeHelpersJob('a', function (QueueFakeHelpersJob $self) {
            $self->fail(new InvalidArgumentException('data salah'));
        });

        $job->withFakeQueueInteractions()->handle();

        $job->assertFailed()
            ->assertFailedWith(InvalidArgumentException::class)
            ->assertFailedWith(new InvalidArgumentException('data salah'));
        $this->assertFails(function () use ($job) {
            $job->assertNotFailed();
        }, 'failed unexpectedly');
        $this->assertFails(function () use ($job) {
            $job->assertFailedWith(new InvalidArgumentException('pesan lain'));
        });
        $this->assertFails(function () use ($job) {
            $job->assertFailedWith(RuntimeException::class);
        });
    }

    public function testUntouchedFakeJobAssertions() {
        $job = (new QueueFakeHelpersJob())->withFakeQueueInteractions();

        $job->assertNotDeleted()->assertNotReleased()->assertNotFailed();
        $this->assertSame(1, $job->attempts());
        $this->assertFails(function () use ($job) {
            $job->assertFailed();
        }, 'was not failed');
    }

    public function testAssertionsRequireTheFakeInteractions() {
        $job = new QueueFakeHelpersJob();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('withFakeQueueInteractions()');
        $job->assertDeleted();
    }

    // ---- withFakeBatch ----

    public function testWithFakeBatchReplacesTheBatchLookup() {
        $job = new QueueFakeHelpersJob();

        $this->assertNull($job->batch(), 'tanpa batch palsu dan tanpa batchId');
        $this->assertFalse((bool) $job->batching());

        list($same, $batch) = $job->withFakeBatch('b-1', 'Impor', 5, 5);

        $this->assertSame($job, $same);
        $this->assertInstanceOf(CTesting_Fake_Queue_BatchFake::class, $batch);
        $this->assertSame($batch, $job->batch());
        $this->assertSame('b-1', $job->batchId);
        $this->assertSame('Impor', $job->batch()->name);
        $this->assertTrue($job->batching());
    }

    public function testFakeBatchRecordsAddCancelAndDelete() {
        list($job, $batch) = (new QueueFakeHelpersJob())->withFakeBatch('b-2', 'x', 2, 2);

        $job->batch()->add([new QueueFakeHelpersOtherJob('anak1'), new QueueFakeHelpersOtherJob('anak2')]);
        $this->assertCount(2, $batch->added);
        $this->assertSame(4, $batch->totalJobs);
        $this->assertSame(4, $batch->pendingJobs);

        $this->assertFalse($batch->cancelled());
        $job->batch()->cancel();
        $this->assertTrue($batch->cancelled());
        $this->assertFalse($job->batching(), 'batch dibatalkan');

        $this->assertFalse($batch->deleted());
        $job->batch()->delete();
        $this->assertTrue($batch->deleted());
        $this->assertSame($batch, $batch->fresh());
    }

    public function testFakeBatchDoesNotLeakToOtherJobs() {
        list($job) = (new QueueFakeHelpersJob('a'))->withFakeBatch('b-3');
        $other = new QueueFakeHelpersJob('b');

        $this->assertNotNull($job->batch());
        $this->assertNull($other->batch());
    }

    public function testFakeBatchIsNotPartOfTheSerializedJob() {
        $plain = serialize(new QueueFakeHelpersJob('a'));
        $job = new QueueFakeHelpersJob('a');
        $job->withFakeBatch('b-4');
        $job->batchId = null;

        $this->assertSame($plain, serialize($job), 'withFakeBatch tidak menambah properti yang diserialisasi');
    }

    // ---- QueueManagerFake ----

    public function testQueueFakeAssertCountAndPushedOnce() {
        $fake = new CTesting_Fake_Queue_QueueManagerFake();
        $fake->push(new QueueFakeHelpersJob('a'));
        $fake->push(new QueueFakeHelpersOtherJob('b'));
        $fake->push(new QueueFakeHelpersOtherJob('c'));

        $fake->assertCount(3);
        $fake->assertPushedOnce(QueueFakeHelpersJob::class);
        $fake->assertPushedOnce(QueueFakeHelpersOtherJob::class, function ($job) {
            return $job->name === 'b';
        });
        $fake->assertPushedOnce(function (QueueFakeHelpersOtherJob $job) {
            return $job->name === 'c';
        });
        $this->assertFails(function () use ($fake) {
            $fake->assertCount(1);
        }, 'Expected 1 jobs to be pushed, but found 3');
        $this->assertFails(function () use ($fake) {
            $fake->assertPushedOnce(QueueFakeHelpersOtherJob::class);
        }, '2 times instead of 1');
    }

    public function testQueueFakeClosurePushed() {
        $fake = new CTesting_Fake_Queue_QueueManagerFake();
        $fake->assertClosureNotPushed();

        $fake->push(function () {
        });

        $fake->assertClosurePushed();
        $fake->assertCount(1);
        $this->assertFails(function () use ($fake) {
            $fake->assertClosureNotPushed();
        });
        $this->assertFails(function () use ($fake) {
            $fake->assertClosurePushed(function () {
                return false;
            });
        });
    }

    public function testQueueFakeExceptPassesThroughToTheRealQueue() {
        $real = new QueueFakeHelpersRealQueue();
        $fake = (new CTesting_Fake_Queue_QueueManagerFake())->except(QueueFakeHelpersOtherJob::class)->passThroughTo($real);

        $fake->push(new QueueFakeHelpersJob('dicatat'));
        $fake->push(new QueueFakeHelpersOtherJob('diteruskan'));

        $fake->assertPushed(QueueFakeHelpersJob::class);
        $fake->assertNotPushed(QueueFakeHelpersOtherJob::class);
        $this->assertCount(1, $real->pushed);
        $this->assertSame('diteruskan', $real->pushed[0]->name);
    }

    public function testQueueFakeExceptWithClosure() {
        $real = new QueueFakeHelpersRealQueue();
        $fake = (new CTesting_Fake_Queue_QueueManagerFake())->except(function ($job) {
            return is_object($job) && $job->name === 'nyata';
        })->passThroughTo($real);

        $fake->push(new QueueFakeHelpersJob('nyata'));
        $fake->push(new QueueFakeHelpersJob('palsu'));

        $this->assertCount(1, $real->pushed);
        $fake->assertCount(1);
    }

    public function testQueueFakeSerializeAndRestoreRecordsACopy() {
        $job = new QueueFakeHelpersJob('asli');
        $plain = new CTesting_Fake_Queue_QueueManagerFake();
        $plain->push($job);
        $this->assertSame($job, $plain->pushed(QueueFakeHelpersJob::class)->first(), 'bawaan mencatat instance yang sama');

        $restored = (new CTesting_Fake_Queue_QueueManagerFake())->serializeAndRestore();
        $restored->push($job);
        $copy = $restored->pushed(QueueFakeHelpersJob::class)->first();

        $this->assertNotSame($job, $copy);
        $this->assertSame('asli', $copy->name);
    }

    // ---- BusFake ----

    public function testBusFakeDispatchedOnce() {
        $fake = new CTesting_Fake_Base_BusFake(CQueue::dispatcher());
        $fake->dispatch(new QueueFakeHelpersJob('a'));
        $fake->dispatch(new QueueFakeHelpersOtherJob('b'));
        $fake->dispatch(new QueueFakeHelpersOtherJob('c'));

        $fake->assertDispatchedOnce(QueueFakeHelpersJob::class);
        $fake->assertDispatchedOnce(QueueFakeHelpersOtherJob::class, function ($job) {
            return $job->name === 'c';
        });
        $fake->assertDispatchedOnce(function (QueueFakeHelpersOtherJob $job) {
            return $job->name === 'b';
        });
        $this->assertFails(function () use ($fake) {
            $fake->assertDispatchedOnce(QueueFakeHelpersOtherJob::class);
        }, '2 times instead of 1');
        $this->assertFails(function () use ($fake) {
            $fake->assertDispatchedOnce('JobYangTidakAda');
        }, '0 times instead of 1');
    }

    public function testBusFakeExceptDispatchesForRealAndSerializeAndRestore() {
        $dispatcher = new QueueFakeHelpersDispatcher();
        $fake = (new CTesting_Fake_Base_BusFake($dispatcher))->except(QueueFakeHelpersOtherJob::class)->serializeAndRestore();
        $original = new QueueFakeHelpersJob('asli');

        $fake->dispatch($original);
        $fake->dispatch(new QueueFakeHelpersOtherJob('nyata'));

        $this->assertSame([QueueFakeHelpersOtherJob::class], $dispatcher->seen, 'yang di except() dikirim ke dispatcher asli');
        $fake->assertNotDispatched(QueueFakeHelpersOtherJob::class);
        $fake->assertDispatchedOnce(QueueFakeHelpersJob::class);
        $this->assertNotSame($original, $fake->dispatched(QueueFakeHelpersJob::class)->first(), 'salinan hasil serialize/unserialize');
    }

    public function testBusFakeExceptWithClosure() {
        $dispatcher = new QueueFakeHelpersDispatcher();
        $fake = (new CTesting_Fake_Base_BusFake($dispatcher))->except(function ($command) {
            return $command->name === 'nyata';
        });

        $fake->dispatch(new QueueFakeHelpersJob('nyata'));
        $fake->dispatch(new QueueFakeHelpersJob('palsu'));

        $this->assertSame([QueueFakeHelpersJob::class], $dispatcher->seen);
        $fake->assertDispatchedOnce(QueueFakeHelpersJob::class);
    }

    public function testBusFakeBatchAssertions() {
        $fake = new CTesting_Fake_Base_BusFake(CQueue::dispatcher());
        $fake->assertNothingBatched();
        $fake->assertBatchCount(0);

        $fake->batch([new QueueFakeHelpersJob('a'), new QueueFakeHelpersOtherJob('b')])->name('Uji')->dispatch();

        $fake->assertBatchCount(1);
        $this->assertFails(function () use ($fake) {
            $fake->assertBatchCount(2);
        }, 'Expected 2 batches to be dispatched, but found 1 instead.');
        $this->assertFails(function () use ($fake) {
            $fake->assertNothingBatched();
        }, QueueFakeHelpersOtherJob::class);
    }

    public function testBusFakeNothingChained() {
        $fake = new CTesting_Fake_Base_BusFake(CQueue::dispatcher());
        $fake->dispatch(new QueueFakeHelpersJob('polos'));
        $fake->assertNothingChained();

        $job = new QueueFakeHelpersJob('induk');
        $job->chain([new QueueFakeHelpersOtherJob('anak')]);
        $fake->dispatch($job);

        $this->assertFails(function () use ($fake) {
            $fake->assertNothingChained();
        }, QueueFakeHelpersJob::class);
    }
}
