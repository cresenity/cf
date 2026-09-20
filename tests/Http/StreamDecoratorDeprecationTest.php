<?php

use PHPUnit\Framework\TestCase;

/**
 * GuzzleHttp\Psr7\StreamDecoratorTrait, GuzzleHttp\Stream\StreamDecoratorTrait, and
 * RingCentral\Psr7\StreamDecoratorTrait (an abstract class despite the name) all wrote
 * into an undeclared `$stream` property - either directly in their own __construct()
 * (every eager decorator: LimitStream, CachingStream, MultipartStream, NoSeekStream,
 * DroppingStream, ...) or lazily inside __get() (LazyOpenStream, which opens the file
 * only on first read). Both paths deprecate as a dynamic property write on PHP 8.2+, and
 * since every one of Guzzle's real HTTP requests through system/libraries/CVendor flows
 * through PSR-7 streams, this fires far more than the $_mh/$_value cases in
 * GuzzleCurlMultiHandlerDeprecationTest. Fixed the same way: declare the property (in
 * the trait, or in the RingCentral abstract base class), and for the lazy case unset()
 * it right after construction so __get() still does the lazy init exactly once.
 *
 * LazyOpenStream::createStream() (both GuzzleHttp\Psr7 and RingCentral\Psr7) calls the
 * global stream_for()/try_fopen() helpers from their respective functions.php, which
 * nothing in the framework ever requires - a pre-existing, unrelated gap (this class is
 * not used anywhere in system/ today) that would fatal independently of this fix. Loaded
 * here only so the lazy-init path under test can actually run end to end.
 */
class StreamDecoratorDeprecationTest extends TestCase {
    public static function setUpBeforeClass(): void {
        require_once DOCROOT . 'system/vendor/GuzzleHttp/Psr7/functions_include.php';
        require_once DOCROOT . 'system/vendor/RingCentral/Psr7/functions_include.php';
    }

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

    public function testPsr7LazyOpenStreamDoesNotOpenTheFileUntilFirstReadAndCachesIt() {
        $path = tempnam(sys_get_temp_dir(), 'cf_lazy_psr7');
        file_put_contents($path, 'hello lazy psr7');

        try {
            $this->assertNoDeprecation(function () use ($path) {
                $stream = new GuzzleHttp\Psr7\LazyOpenStream($path, 'r');

                $read = Closure::bind(function () {
                    return isset($this->stream);
                }, $stream, GuzzleHttp\Psr7\LazyOpenStream::class);
                $this->assertFalse($read(), 'The file must not be opened by the constructor alone.');

                $this->assertSame('hello', $stream->read(5));

                $readAfter = Closure::bind(function () {
                    return [$this->stream, $this->stream];
                }, $stream, GuzzleHttp\Psr7\LazyOpenStream::class);
                list($first, $second) = $readAfter();
                $this->assertSame($first, $second, 'The lazily opened stream must be cached, not reopened.');

                $stream->close();
            });
        } finally {
            unlink($path);
        }
    }

    public function testStreamLazyOpenStreamDoesNotOpenTheFileUntilFirstRead() {
        $path = tempnam(sys_get_temp_dir(), 'cf_lazy_stream');
        file_put_contents($path, 'hello lazy stream');

        try {
            $this->assertNoDeprecation(function () use ($path) {
                $stream = new GuzzleHttp\Stream\LazyOpenStream($path, 'r');

                $read = Closure::bind(function () {
                    return isset($this->stream);
                }, $stream, GuzzleHttp\Stream\LazyOpenStream::class);
                $this->assertFalse($read());

                $this->assertSame('hello', $stream->read(5));
            });
        } finally {
            unlink($path);
        }
    }

    public function testRingCentralLazyOpenStreamDoesNotOpenTheFileUntilFirstRead() {
        $path = tempnam(sys_get_temp_dir(), 'cf_lazy_rc');
        file_put_contents($path, 'hello lazy ringcentral');

        try {
            $this->assertNoDeprecation(function () use ($path) {
                $stream = new RingCentral\Psr7\LazyOpenStream($path, 'r');

                $read = Closure::bind(function () {
                    return isset($this->stream);
                }, $stream, RingCentral\Psr7\LazyOpenStream::class);
                $this->assertFalse($read());

                $this->assertSame('hello', $stream->read(5));
            });
        } finally {
            unlink($path);
        }
    }

    public function testEagerPsr7DecoratorsConstructAndReadWithoutDeprecation() {
        $this->assertNoDeprecation(function () {
            $limit = new GuzzleHttp\Psr7\LimitStream(GuzzleHttp\Psr7\Utils::streamFor('0123456789'), 5, 0);
            $this->assertSame('01234', $limit->read(5));

            $caching = new GuzzleHttp\Psr7\CachingStream(GuzzleHttp\Psr7\Utils::streamFor('abcdef'));
            $this->assertSame('abc', $caching->read(3));

            $noSeek = new GuzzleHttp\Psr7\NoSeekStream(GuzzleHttp\Psr7\Utils::streamFor('xyz'));
            $this->assertSame('xyz', $noSeek->read(3));

            $multipart = new GuzzleHttp\Psr7\MultipartStream([
                ['name' => 'field', 'contents' => 'value'],
            ]);
            $this->assertGreaterThan(0, $multipart->getSize());
        });
    }

    public function testRingCentralEagerDecoratorConstructsAndReadsWithoutDeprecation() {
        $this->assertNoDeprecation(function () {
            // RingCentral\Psr7's own stream_for() global helper isn't autoloadable
            // outside its functions_include.php; GuzzleHttp\Psr7\Stream satisfies
            // the same Psr\Http\Message\StreamInterface LimitStream requires.
            $stream = new RingCentral\Psr7\LimitStream(GuzzleHttp\Psr7\Utils::streamFor('0123456789'), 5, 0);
            $this->assertSame('01234', $stream->read(5));
        });
    }
}
