<?php

use PHPUnit\Framework\TestCase;

class QueueMiddlewareUpstreamTestJob {
    public $released = [];

    public $deleted = false;

    public $failed = null;

    public $batch = null;

    public function release($delay = 0) {
        $this->released[] = $delay;
    }

    public function delete() {
        $this->deleted = true;
    }

    public function fail($exception = null) {
        $this->failed = $exception;
    }

    public function batch() {
        return $this->batch;
    }
}

class QueueMiddlewareUpstreamTestLimiter {
    public $decaysAt;

    public $hits = 0;

    public $cleared = false;

    private $max;

    public function __construct($max) {
        $this->max = $max;
        $this->decaysAt = time() + 60;
    }

    public function tooManyAttempts() {
        return $this->hits >= $this->max;
    }

    public function acquire() {
        if ($this->hits >= $this->max) {
            return false;
        }
        $this->hits++;

        return true;
    }

    public function clear() {
        $this->cleared = true;
        $this->hits = 0;
    }
}

/**
 * Middleware job hasil port upstream 13.x: Skip, Release, SkipIfBatchCancelled, FailOnException,
 * ThrottlesExceptions(+WithRedis), RateLimitedWithRedis.
 */
class QueueMiddlewareUpstreamTest extends TestCase {
    private function run_($middleware, $job, &$ran, $throw = null) {
        $ran = 0;

        return $middleware->handle($job, function ($job) use (&$ran, $throw) {
            $ran++;
            if ($throw) {
                throw $throw;
            }

            return 'selesai';
        });
    }

    public function testSkip() {
        $job = new QueueMiddlewareUpstreamTestJob();
        $this->assertFalse($this->run_(CQueue_Middleware_Skip::when(true), $job, $ran));
        $this->assertSame(0, $ran);
        $this->assertSame('selesai', $this->run_(CQueue_Middleware_Skip::when(function () {
            return false;
        }), $job, $ran));
        $this->assertSame(1, $ran);
        $this->assertFalse($this->run_(CQueue_Middleware_Skip::unless(false), $job, $ran));
    }

    public function testRelease() {
        $job = new QueueMiddlewareUpstreamTestJob();
        $this->run_(CQueue_Middleware_Release::when(true, 30), $job, $ran);
        $this->assertSame(0, $ran);
        $this->assertSame([30], $job->released);

        $this->run_(CQueue_Middleware_Release::unless(true), $job, $ran);
        $this->assertSame(1, $ran);
    }

    public function testSkipIfBatchCancelled() {
        $job = new QueueMiddlewareUpstreamTestJob();
        $this->run_(new CQueue_Middleware_SkipIfBatchCancelled(), $job, $ran);
        $this->assertSame(1, $ran, 'job tanpa batch tetap jalan');

        $job->batch = new class() {
            public $isCancelled = true;

            public function cancelled() {
                return $this->isCancelled;
            }
        };
        $this->run_(new CQueue_Middleware_SkipIfBatchCancelled(), $job, $ran);
        $this->assertSame(0, $ran);

        $job->batch->isCancelled = false;
        $this->run_(new CQueue_Middleware_SkipIfBatchCancelled(), $job, $ran);
        $this->assertSame(1, $ran);
    }

    public function testFailOnExceptionFailsMatchingExceptionsAndRethrows() {
        $job = new QueueMiddlewareUpstreamTestJob();
        $e = new InvalidArgumentException('data salah');

        try {
            $this->run_(new CQueue_Middleware_FailOnException([InvalidArgumentException::class]), $job, $ran, $e);
            $this->fail('exception harus dilempar ulang');
        } catch (InvalidArgumentException $caught) {
            $this->assertSame($e, $job->failed);
        }

        $job = new QueueMiddlewareUpstreamTestJob();

        try {
            $this->run_(new CQueue_Middleware_FailOnException([InvalidArgumentException::class]), $job, $ran, new RuntimeException('lain'));
        } catch (RuntimeException $caught) {
            $this->assertNull($job->failed);
        }
    }

    public function testThrottlesExceptionsReleasesAndThenStopsRunningTheJob() {
        $key = uniqid('throttle', true);
        $job = new QueueMiddlewareUpstreamTestJob();
        $middleware = function () use ($key) {
            return (new CQueue_Middleware_ThrottlesExceptions(2, 60))->by($key)->backoff(1);
        };

        $this->run_($middleware(), $job, $ran, new RuntimeException('api down'));
        $this->run_($middleware(), $job, $ran, new RuntimeException('api down'));
        $this->assertSame([60, 60], $job->released);

        //batas tercapai: job tidak dijalankan, langsung dilepas sampai jendela lewat
        $this->run_($middleware(), $job, $ran);
        $this->assertSame(0, $ran);
        $this->assertCount(3, $job->released);
        $this->assertGreaterThan(3, $job->released[2]);

        CCache::rateLimiter()->clear('cresenity_throttles_exceptions:' . $key);
    }

    public function testThrottlesExceptionsDeleteWhenFailWhenAndWhen() {
        $job = new QueueMiddlewareUpstreamTestJob();
        $this->run_((new CQueue_Middleware_ThrottlesExceptions())->by(uniqid('t', true))->deleteWhen(InvalidArgumentException::class), $job, $ran, new InvalidArgumentException());
        $this->assertTrue($job->deleted);

        $job = new QueueMiddlewareUpstreamTestJob();
        $e = new LogicException();
        $this->run_((new CQueue_Middleware_ThrottlesExceptions())->by(uniqid('t', true))->failWhen(function ($e) {
            return $e instanceof LogicException;
        }), $job, $ran, $e);
        $this->assertSame($e, $job->failed);

        $this->expectException(DomainException::class);
        $this->run_((new CQueue_Middleware_ThrottlesExceptions())->by(uniqid('t', true))->when(function ($e) {
            return !$e instanceof DomainException;
        }), new QueueMiddlewareUpstreamTestJob(), $ran, new DomainException());
    }

    public function testThrottlesExceptionsKeyDefaultsToTheJobClass() {
        $method = new ReflectionMethod(CQueue_Middleware_ThrottlesExceptions::class, 'getKey');
        $method->setAccessible(true);

        $this->assertSame('cresenity_throttles_exceptions:' . md5(QueueMiddlewareUpstreamTestJob::class), $method->invoke(new CQueue_Middleware_ThrottlesExceptions(), new QueueMiddlewareUpstreamTestJob()));
        $this->assertSame('p:k', $method->invoke((new CQueue_Middleware_ThrottlesExceptions())->withPrefix('p:')->by('k'), new QueueMiddlewareUpstreamTestJob()));
    }

    public function testThrottlesExceptionsWithRedis() {
        $limiter = new QueueMiddlewareUpstreamTestLimiter(1);
        $middleware = new class($limiter) extends CQueue_Middleware_ThrottlesExceptionsWithRedis {
            private $fake;

            public function __construct($fake) {
                parent::__construct(1, 60);
                $this->fake = $fake;
            }

            protected function durationLimiter($key) {
                return $this->fake;
            }
        };
        $job = new QueueMiddlewareUpstreamTestJob();

        $this->run_($middleware, $job, $ran);
        $this->assertTrue($limiter->cleared);

        $this->run_($middleware, $job, $ran, new RuntimeException());
        $this->assertSame(1, $limiter->hits);
        $this->run_($middleware, $job, $ran);
        $this->assertSame(0, $ran);
        $this->assertSame(0, $job->released[0]);
        $this->assertGreaterThanOrEqual(55, $job->released[1]);
        $this->assertLessThanOrEqual(60, $job->released[1]);
    }

    public function testRateLimitedWithRedisReleasesWhenTheLimitIsReached() {
        $name = uniqid('redis-limit', true);
        CCache::rateLimiter()->aliasFor($name, function () {
            return CCache_RateLimiting_Limit::perMinute(1);
        });
        $limiter = new QueueMiddlewareUpstreamTestLimiter(1);
        $middleware = new class($name, $limiter) extends CQueue_Middleware_RateLimitedWithRedis {
            private $fake;

            public function __construct($name, $fake) {
                parent::__construct($name);
                $this->fake = $fake;
            }

            protected function durationLimiter($key, $maxAttempts, $decaySeconds) {
                return $this->fake;
            }
        };
        $job = new QueueMiddlewareUpstreamTestJob();

        $this->run_($middleware, $job, $ran);
        $this->assertSame(1, $ran);
        $this->run_($middleware, $job, $ran);
        $this->assertSame(0, $ran);
        $this->assertCount(1, $job->released);
        $this->assertGreaterThan(0, $job->released[0]);

        $this->assertContains('connectionName', $middleware->__sleep());
    }
}
