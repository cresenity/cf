<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__) . '/Support/DaemonFixture.php';

/**
 * CDaemon_Plugin_AutoScalePool has no Laravel counterpart -- written against
 * CF's own behaviour. Real process forking is exercised only through a test
 * double that records what it would have done instead of calling pcntl_fork,
 * so these tests run in a normal PHPUnit process without actually forking.
 */
class AutoScalePoolTest extends TestCase {
    protected function tearDown() {
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * @return DaemonTestService
     */
    protected function makeService() {
        return new DaemonTestService('DaemonTestService', [
            'pidFile' => sys_get_temp_dir() . '/cf-autoscalepool-test-' . getmypid() . '.pid',
        ]);
    }

    /**
     * clamp() is pure -- no process, no cache, no service state -- so the
     * min/max guarantee is checked directly first.
     */
    public function testClampAlwaysBindsIntoMinMaxRegardlessOfWhatDecideReturned() {
        $pool = new CDaemon_Plugin_AutoScalePool($this->makeService());
        $pool->configure(['min' => 2, 'max' => 9, 'work' => function () {}]);

        $this->assertSame(2, $pool->clamp(0), 'decide() asking for fewer than min must not shrink below min');
        $this->assertSame(2, $pool->clamp(-5), 'a negative/buggy decide() result must not go negative');
        $this->assertSame(9, $pool->clamp(999), 'decide() asking for more than max must not exceed max');
        $this->assertSame(5, $pool->clamp(5), 'a value already inside the range passes through unchanged');
    }

    public function testConfigureRejectsMissingWork() {
        $pool = new CDaemon_Plugin_AutoScalePool($this->makeService());

        $this->expectException(Exception::class);
        $pool->configure(['min' => 1, 'max' => 1]);
    }

    public function testConfigureRejectsNonCallableDecide() {
        $pool = new CDaemon_Plugin_AutoScalePool($this->makeService());

        $this->expectException(Exception::class);
        $pool->configure(['work' => function () {}, 'decide' => 'not a function']);
    }

    /**
     * configure() must register tick() on ON_IDLE with the `every` value as
     * the throttle -- that's the only thing standing between `decide` (which
     * may hit an external API, e.g. a SQS GetQueueAttributes call) and being
     * invoked on every single loop tick instead of every `every` seconds.
     */
    public function testConfigureRegistersTickOnIdleWithEveryAsThrottle() {
        $service = $this->makeService();
        $pool = new CDaemon_Plugin_AutoScalePool($service);
        $pool->configure(['min' => 1, 'max' => 1, 'every' => 45, 'work' => function () {}]);

        $callbacksProperty = new ReflectionProperty(CDaemon_ServiceAbstract::class, 'callbacks');
        $callbacksProperty->setAccessible(true);
        $callbacks = $callbacksProperty->getValue($service);

        $this->assertArrayHasKey(CDaemon_ServiceAbstract::ON_IDLE, $callbacks);
        $registered = end($callbacks[CDaemon_ServiceAbstract::ON_IDLE]);
        $this->assertSame(45, $registered['throttle']);
        $this->assertSame([$pool, 'tick'], $registered['callback']);
    }

    /**
     * tick() must ask decide() for a target, clamp it, then act on the
     * CLAMPED value -- not the raw one. The fork/stop side is intercepted by
     * a test double (see RecordingAutoScalePool below) instead of forking a
     * real process, so this stays a fast, deterministic unit test.
     */
    public function testTickClampsDecideResultBeforeScaling() {
        $pool = new RecordingAutoScalePool($this->makeService());
        $pool->configure([
            'group' => 'broadcast',
            'min' => 2,
            'max' => 9,
            'every' => 60,
            'decide' => function ($current) {
                return 999; // a careless/buggy callback
            },
            'work' => function () {},
        ]);

        $pool->fakeCurrentCount = 2;
        $pool->tick();

        $this->assertSame([9], $pool->scaleToCalls, 'the pool must never be asked to scale past max, even if decide() returns something absurd');
    }

    public function testTickScalesDownWhenDecideReturnsFewerThanCurrent() {
        $pool = new RecordingAutoScalePool($this->makeService());
        $pool->configure([
            'group' => 'broadcast',
            'min' => 2,
            'max' => 9,
            'decide' => function ($current) {
                return 0; // backlog is empty
            },
            'work' => function () {},
        ]);

        $pool->fakeCurrentCount = 6;
        $pool->tick();

        $this->assertSame([2], $pool->scaleToCalls, 'decide() asking for 0 must still be clamped up to min before scaling down to it');
    }

    public function testTickWithoutDecideIsANoOp() {
        $pool = new RecordingAutoScalePool($this->makeService());
        $pool->configure(['group' => 'broadcast', 'min' => 2, 'max' => 9, 'work' => function () {}]);

        $pool->fakeCurrentCount = 4;
        $pool->tick();

        $this->assertSame([4], $pool->scaleToCalls, 'with no decide callback, the target defaults to the current count (no-op scale)');
    }

    /**
     * overlapping=false must serialize work() across the whole pool via the
     * same cache-backed mutex ->withoutOverlapping() already uses for cron --
     * exercised directly on runOneUnitOfWork() rather than by actually
     * forking two processes.
     */
    public function testOverlappingFalseLetsOnlyOneUnitOfWorkRunAtATime() {
        $service = $this->makeService();
        $workRanCount = 0;

        $poolA = new CDaemon_Plugin_AutoScalePool($service);
        $poolA->configure([
            'group' => 'migration',
            'min' => 1,
            'max' => 1,
            'overlapping' => false,
            'mutexStore' => 'array',
            'work' => function () use (&$workRanCount) {
                $workRanCount++;
            },
        ]);

        // Simulates a second sibling worker process trying to run while the
        // first one (poolA) is still "inside" its own work() -- same group,
        // same mutex key, as would happen if both were forked from the same
        // configure() call.
        $poolB = new CDaemon_Plugin_AutoScalePool($service);
        $poolB->configure([
            'group' => 'migration',
            'min' => 1,
            'max' => 1,
            'overlapping' => false,
            'mutexStore' => 'array',
            'work' => function () use (&$workRanCount) {
                $workRanCount++;
            },
        ]);

        $heldMutex = new ReflectionMethod(CDaemon_Plugin_AutoScalePool::class, 'acquireMutex');
        $heldMutex->setAccessible(true);
        $this->assertTrue($heldMutex->invoke($poolA), 'poolA takes the lock first');

        $ran = $poolB->runOneUnitOfWork();

        $this->assertFalse($ran, 'poolB must back off -- the mutex is already held');
        $this->assertSame(0, $workRanCount, 'work() must not have run for poolB while the mutex was held');

        $releaseMutex = new ReflectionMethod(CDaemon_Plugin_AutoScalePool::class, 'releaseMutex');
        $releaseMutex->setAccessible(true);
        $releaseMutex->invoke($poolA);

        $this->assertTrue($poolB->runOneUnitOfWork(), 'once released, poolB can acquire it and run');
        $this->assertSame(1, $workRanCount);
    }

    /**
     * overlapping=true (the default, and the point of the pool for a plain
     * SQS consumer) must never touch the mutex at all -- two units of work
     * back to back both just run.
     */
    public function testOverlappingTrueNeverContendsOnTheMutex() {
        $pool = new CDaemon_Plugin_AutoScalePool($this->makeService());
        $workRanCount = 0;
        $pool->configure([
            'group' => 'broadcast',
            'min' => 1,
            'max' => 1,
            'work' => function () use (&$workRanCount) {
                $workRanCount++;
            },
        ]);

        $this->assertTrue($pool->runOneUnitOfWork());
        $this->assertTrue($pool->runOneUnitOfWork());
        $this->assertSame(2, $workRanCount);
    }
}

/**
 * Intercepts scaleTo() (the only method that would otherwise fork/kill real
 * OS processes) so tick()'s decide -> clamp -> scale pipeline can be
 * unit-tested without touching pcntl at all.
 */
class RecordingAutoScalePool extends CDaemon_Plugin_AutoScalePool {
    /**
     * @var int
     */
    public $fakeCurrentCount = 0;

    /**
     * @var int[]
     */
    public $scaleToCalls = [];

    public function count() {
        return $this->fakeCurrentCount;
    }

    protected function scaleTo($target) {
        $this->scaleToCalls[] = $target;
    }

    /**
     * The real ProcessManager plugin is only registered by CDaemon_ServiceAbstract::init(),
     * which these tests never run (they construct the service directly) -- tick() calls
     * reap() unconditionally, so it needs a harmless stand-in here.
     *
     * @return object
     */
    protected function processManager() {
        return new class() {
            public function reap() {
            }
        };
    }
}
