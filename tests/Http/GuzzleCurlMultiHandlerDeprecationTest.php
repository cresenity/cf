<?php

use PHPUnit\Framework\TestCase;

/**
 * Several GuzzleHttp/Ring vendor classes used to assign straight into an
 * undeclared property inside __get() (`$_mh` on both
 * GuzzleHttp\Handler\CurlMultiHandler and GuzzleHttp\Ring\Client\CurlMultiHandler,
 * `$_value` on GuzzleHttp\Ring\Future\MagicFutureTrait/FutureArray), which
 * PHP 8.2+ deprecates as a dynamic property write. `$_mh` fires on every
 * outbound HTTP call through Guzzle, so it was the noisiest deprecation in
 * the framework. Fixed by declaring the property and unset()-ing it right
 * after construction, so __get() still does the lazy init exactly once -
 * this asserts each class stays lazy-init-once and free of the deprecation,
 * using Closure::bind() to read the property from the class's own scope the
 * same way its internals do (reading it from outside the class always goes
 * through __get(), so that wouldn't prove anything).
 */
class GuzzleCurlMultiHandlerDeprecationTest extends TestCase {
    private function assertNoDeprecation(callable $fn) {
        $captured = [];
        set_error_handler(function ($errno, $errstr) use (&$captured) {
            $captured[] = $errstr;

            return true;
        });

        try {
            $fn();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $captured, 'Expected no warnings/deprecations, got: ' . implode('; ', $captured));
    }

    public function testHandlerCurlMultiHandlerLazyInitsWithoutDeprecation() {
        $this->assertNoDeprecation(function () {
            $handler = new GuzzleHttp\Handler\CurlMultiHandler(['handle_factory' => new stdClass()]);
            $read = Closure::bind(function () {
                return [$this->_mh, $this->_mh];
            }, $handler, GuzzleHttp\Handler\CurlMultiHandler::class);
            list($first, $second) = $read();

            $this->assertSame($first, $second);
        });
    }

    public function testRingCurlMultiHandlerLazyInitsWithoutDeprecation() {
        $this->assertNoDeprecation(function () {
            $handler = new GuzzleHttp\Ring\Client\CurlMultiHandler(['handle_factory' => function () {}]);
            $read = Closure::bind(function () {
                return [$this->_mh, $this->_mh];
            }, $handler, GuzzleHttp\Ring\Client\CurlMultiHandler::class);
            list($first, $second) = $read();

            $this->assertSame($first, $second);
        });
    }

    public function testRingCurlMultiHandlerHonorsAnExplicitlyProvidedHandleWithoutDeprecation() {
        $existing = curl_multi_init();

        $this->assertNoDeprecation(function () use ($existing) {
            $handler = new GuzzleHttp\Ring\Client\CurlMultiHandler(['mh' => $existing, 'handle_factory' => function () {}]);
            $read = Closure::bind(function () {
                return $this->_mh;
            }, $handler, GuzzleHttp\Ring\Client\CurlMultiHandler::class);

            $this->assertSame($existing, $read());
        });
    }

    /**
     * Same dynamic-property pattern as $_mh above, in
     * GuzzleHttp\Ring\Future\MagicFutureTrait's $_value (used by FutureArray,
     * the ArrayAccess wrapper every Ring HTTP response future is). Fixed the
     * same way: declare the property (in the trait), unset() it right after
     * construction so __get() still lazily resolves the promise exactly once.
     */
    public function testFutureArrayLazyInitsWithoutDeprecation() {
        // FutureArray's ArrayAccess/Countable/IteratorAggregate methods carry their own,
        // unrelated #[ReturnTypeWillChange] deprecation on this PHP version, emitted once
        // when the class is first linked - trigger that outside assertNoDeprecation so it
        // isn't mistaken for a regression of the dynamic-property fix under test.
        class_exists(GuzzleHttp\Ring\Future\FutureArray::class);

        $this->assertNoDeprecation(function () {
            $resolve = null;
            $promise = new React\Promise\Promise(function ($r) use (&$resolve) {
                $resolve = $r;
            });
            $resolve(['foo' => 'bar']);

            $future = new GuzzleHttp\Ring\Future\FutureArray($promise, function () {});

            // Read via Closure::bind rather than ArrayAccess, for the same reason.
            $read = Closure::bind(function () {
                return [$this->_value, $this->_value];
            }, $future, GuzzleHttp\Ring\Future\FutureArray::class);
            list($first, $second) = $read();

            $this->assertSame(['foo' => 'bar'], $first);
            $this->assertSame($first, $second);
        });
    }

    /**
     * ArrayAccess writes on a FutureArray only work once $_value has been
     * materialized by a prior read - true before this fix too (an
     * offsetSet() before any read already silently no-ops, PHP's own
     * "indirect modification of overloaded property" behavior for a
     * property whose __get() doesn't return by reference; unrelated to the
     * dynamic-property fix here and unchanged by it). This pins the actual,
     * working usage: read (materializes the future), then write.
     */
    public function testFutureArraySupportsWritesAfterAPriorRead() {
        $resolve = null;
        $promise = new React\Promise\Promise(function ($r) use (&$resolve) {
            $resolve = $r;
        });
        $resolve(['foo' => 'bar']);

        $future = new GuzzleHttp\Ring\Future\FutureArray($promise, function () {});
        $this->assertSame('bar', $future['foo']);

        $future['baz'] = 'qux';

        $this->assertSame('qux', $future['baz']);
        $this->assertCount(2, $future);
    }
}
