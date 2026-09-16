<?php

use PHPUnit\Framework\TestCase;

/**
 * CUuid_NativeNumberConverter exists so cstr::orderedUuid() doesn't fatal on a server with
 * neither the gmp extension nor moontoast/math (bcmath) installed - #-13845. Its actual input
 * in that code path (CombGenerator's COMB timestamp) is always a ~15-digit decimal string, well
 * inside a native 64-bit int, so these tests focus on that value shows exact results and that a
 * value genuinely too large for a native int still fails loudly instead of returning something
 * silently wrong.
 */
class NativeNumberConverterTest extends TestCase {
    protected function setUp(): void {
        if (PHP_INT_SIZE < 8) {
            $this->markTestSkipped('Requires a 64-bit PHP build.');
        }
    }

    public function testToHexConvertsARealisticCombTimestamp() {
        $converter = new CUuid_NativeNumberConverter();

        // Shaped like CombGenerator::timestamp(): 10-digit seconds + 5-digit microsecond
        // fraction, exactly what orderedUuid() actually passes through this converter.
        $this->assertSame(dechex(169481234512345), $converter->toHex('169481234512345'));
    }

    public function testFromHexIsTheInverseOfToHex() {
        $converter = new CUuid_NativeNumberConverter();

        $hex = $converter->toHex('169481234512345');
        $this->assertSame('169481234512345', $converter->fromHex($hex));
    }

    public function testToHexThrowsForAValueTooLargeForANativeInt() {
        $converter = new CUuid_NativeNumberConverter();
        $this->expectException(Ramsey\Uuid\Exception\UnsatisfiedDependencyException::class);

        // 25 digits, far past PHP_INT_MAX (~19 digits) on any 64-bit build.
        $converter->toHex('1234567890123456789012345');
    }

    public function testFromHexThrowsForAValueTooLargeForANativeInt() {
        $converter = new CUuid_NativeNumberConverter();
        $this->expectException(Ramsey\Uuid\Exception\UnsatisfiedDependencyException::class);

        // hexdec() silently returns a float once this overflows PHP_INT_MAX - must be rejected,
        // not converted into a wrong (rounded) decimal string.
        $converter->fromHex('ffffffffffffffffff');
    }

    /**
     * Wires CUuid_NativeNumberConverter into the exact same CombGenerator +
     * TimestampFirstCombCodec pipeline cstr::orderedUuid() uses, bypassing UuidFactory's
     * gmp/bcmath auto-detection so this runs the fallback path even on a machine (like this
     * one) that has bcmath installed - proving the converter is compatible with Ramsey's real
     * consumer classes, not just correct in isolation.
     */
    public function testProducesAValidUuidThroughTheRealCombPipeline() {
        $factory = new Ramsey\Uuid\UuidFactory();

        $factory->setRandomGenerator(new Ramsey\Uuid\Generator\CombGenerator(
            $factory->getRandomGenerator(),
            new CUuid_NativeNumberConverter()
        ));
        $factory->setCodec(new Ramsey\Uuid\Codec\TimestampFirstCombCodec(
            $factory->getUuidBuilder()
        ));

        $uuid = (string) $factory->uuid4();

        $this->assertTrue(cstr::isUuid($uuid));
    }
}
