<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Exception\AssertionFailedError;

class UjiFake_Job implements CQueue_ShouldQueueInterface {
    use CQueue_Trait_QueueableTrait;

    /** @var string */
    public $name;

    /**
     * @param string $name
     */
    public function __construct($name = 'x') {
        $this->name = $name;
    }

    public function handle() {
    }
}

class UjiFake_OtherJob extends UjiFake_Job {
}

/**
 * Constraint CTesting (SeeInOrder, ArraySubset, CTesting_Assert) dan fake bus/queue.
 */
class TestingConstraintsAndFakesTest extends TestCase {
    protected function assertFails(callable $assertion, $needle = '') {
        try {
            $assertion();
        } catch (AssertionFailedError $e) {
            if ($needle !== '') {
                $this->assertStringContainsString($needle, $e->getMessage());
            }

            return;
        }
        $this->fail('asersi seharusnya gagal');
    }

    public function testSeeInOrderConstraint() {
        $constraint = new CTesting_Constraint_SeeInOrder('satu dua tiga dua empat');
        $this->assertTrue($constraint->matches(['satu', 'dua', 'empat']));
        $this->assertTrue($constraint->matches(['dua', 'dua']), 'kemunculan berikutnya dicari setelah yang sebelumnya');
        $this->assertFalse($constraint->matches(['empat', 'satu']));
        $this->assertFalse($constraint->matches(['dua', 'dua', 'dua']));
        $this->assertStringContainsString('empat', $constraint->failureDescription(['empat', 'satu']));
        $this->assertSame(CTesting_Constraint_SeeInOrder::class, $constraint->toString());
    }

    public function testArraySubsetConstraintAndAssert() {
        CTesting_Assert::assertArraySubset(['a' => 1, 'c' => ['d' => 4]], ['a' => 1, 'b' => 2, 'c' => ['d' => 4, 'e' => 5]]);
        CTesting_Assert::assertArraySubset(['a' => '1'], ['a' => 1]);
        $this->assertFails(function () {
            CTesting_Assert::assertArraySubset(['a' => '1'], ['a' => 1], true);
        });
        $this->assertFails(function () {
            CTesting_Assert::assertArraySubset(['z' => 1], ['a' => 1]);
        });
        $constraint = new CTesting_Constraint_ArraySubset(['a' => 1]);
        $this->assertTrue($constraint->evaluate(['a' => 1, 'b' => 2], '', true));
        $this->assertFalse($constraint->evaluate(['b' => 2], '', true));
        $this->assertStringContainsString('has the subset', $constraint->toString());
        $this->expectException(PHPUnit\Framework\Exception\InvalidArgumentException::class);
        CTesting_Assert::assertArraySubset('bukan array', ['a' => 1]);
    }

    public function testCompatAssertHelpers() {
        CTesting_Assert::assertFileDoesNotExist('/tmp/uji-tidak-ada-' . uniqid());
        CTesting_Assert::assertDirectoryDoesNotExist('/tmp/uji-dir-tidak-ada-' . uniqid());
        CTesting_Assert::assertMatchesRegularExpression('/^a\d+$/', 'a12');
        $this->assertFails(function () {
            CTesting_Assert::assertFileDoesNotExist(__FILE__);
        });
    }

    public function testBusFakeRecordsDispatches() {
        $fake = new CTesting_Fake_Base_BusFake(CQueue::dispatcher());
        $fake->dispatch(new UjiFake_Job('a'));
        $fake->dispatch(new UjiFake_Job('b'));
        $fake->dispatchSync(new UjiFake_OtherJob('s'));
        $fake->dispatchAfterResponse(new UjiFake_OtherJob('r'));

        $fake->assertDispatched(UjiFake_Job::class);
        $fake->assertDispatchedTimes(UjiFake_Job::class, 2);
        $fake->assertDispatched(UjiFake_Job::class, function (UjiFake_Job $job) {
            return $job->name === 'b';
        });
        $fake->assertNotDispatched(UjiFake_Job::class, function (UjiFake_Job $job) {
            return $job->name === 'z';
        });
        $fake->assertDispatchedSync(UjiFake_OtherJob::class);
        $fake->assertNotDispatchedSync(UjiFake_Job::class);
        $fake->assertDispatchedAfterResponse(UjiFake_OtherJob::class);
        $fake->assertDispatched(function (UjiFake_Job $job) {
            return $job->name === 'a';
        }, null);
        $this->assertTrue($fake->hasDispatched(UjiFake_Job::class));
        $this->assertSame(2, $fake->dispatched(UjiFake_Job::class)->count());
        $this->assertFails(function () use ($fake) {
            $fake->assertNothingDispatched();
        });
        $this->assertFails(function () use ($fake) {
            $fake->assertDispatchedTimes(UjiFake_Job::class, 3);
        }, '2 times');
        (new CTesting_Fake_Base_BusFake(CQueue::dispatcher()))->assertNothingDispatched();
    }

    public function testBusFakeOnlyFakesListedJobsAndPassesTheRestThrough() {
        $seen = [];
        $dispatcher = new class($seen) implements CQueue_QueueingDispatcherInterface {
            public $seen;

            public function __construct(&$seen) {
                $this->seen = &$seen;
            }

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
        };
        $fake = new CTesting_Fake_Base_BusFake($dispatcher, [UjiFake_OtherJob::class]);
        $fake->dispatch(new UjiFake_Job('nyata'));
        $fake->dispatch(new UjiFake_OtherJob('palsu'));
        $this->assertSame([UjiFake_Job::class], $seen, 'yang tidak di-fake diteruskan ke dispatcher asli');
        $fake->assertDispatched(UjiFake_OtherJob::class);
        $fake->assertNotDispatched(UjiFake_Job::class);
    }

    public function testBusFakeChainAssertions() {
        $fake = new CTesting_Fake_Base_BusFake(CQueue::dispatcher());
        $job = new UjiFake_Job('induk');
        $job->chain([new UjiFake_OtherJob('anak')]);
        $fake->dispatch($job);
        $fake->assertDispatched(UjiFake_Job::class);
        $fake->assertChained([UjiFake_Job::class, UjiFake_OtherJob::class]);
        $this->assertFails(function () use ($fake) {
            $fake->assertDispatchedWithoutChain(UjiFake_Job::class);
        });
    }

    public function testQueueManagerFakeRecordsPushes() {
        $fake = new CTesting_Fake_Queue_QueueManagerFake();
        $fake->push(new UjiFake_Job('a'));
        $fake->pushOn('berat', new UjiFake_Job('b'));
        $fake->later(60, new UjiFake_OtherJob('c'));

        $fake->assertPushed(UjiFake_Job::class, 2);
        $fake->assertPushedOn('berat', UjiFake_Job::class);
        $fake->assertPushed(UjiFake_OtherJob::class, function ($job, $queue) {
            return $job->name === 'c';
        });
        $fake->assertNotPushed(UjiFake_OtherJob::class, function ($job) {
            return $job->name === 'z';
        });
        $this->assertSame(2, $fake->size(), 'size() tanpa nama = job pada queue default (null) saja');
        $this->assertSame(1, $fake->size('berat'));
        $this->assertTrue($fake->hasPushed(UjiFake_Job::class));
        $this->assertSame(2, $fake->pushed(UjiFake_Job::class)->count());
        $this->assertFails(function () use ($fake) {
            $fake->assertNothingPushed();
        });
        $this->assertFails(function () use ($fake) {
            $fake->assertPushedOn('ringan', UjiFake_Job::class);
        });
        (new CTesting_Fake_Queue_QueueManagerFake())->assertNothingPushed();
    }

    public function testQueueManagerFakeChainAssertions() {
        $fake = new CTesting_Fake_Queue_QueueManagerFake();
        $job = new UjiFake_Job('induk');
        $job->chain([new UjiFake_OtherJob('anak')]);
        $fake->push($job);
        $fake->assertPushedWithChain(UjiFake_Job::class, [UjiFake_OtherJob::class]);
        $fake->assertPushedWithChain(UjiFake_Job::class, [new UjiFake_OtherJob('anak')]);
        $this->assertFails(function () use ($fake) {
            $fake->assertPushedWithoutChain(UjiFake_Job::class);
        });
        $fake->push(new UjiFake_Job('sendiri'));
        $fake->assertPushedWithoutChain(UjiFake_Job::class);
    }
}
