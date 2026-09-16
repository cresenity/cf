<?php

/**
 * NumberConverterInterface implementation for cstr::orderedUuid() that needs neither the gmp
 * extension nor Moontoast\Math\BigNumber (which itself needs bcmath) - unlike Ramsey's own
 * DegradedNumberConverter, which unconditionally throws UnsatisfiedDependencyException.
 *
 * CombGenerator::generate() (the only caller reached by cstr::orderedUuid()) only ever passes
 * it the COMB timestamp - a ~15-digit decimal number, safely inside a native 64-bit PHP int -
 * so plain dechex()/hexdec() on that int is exact, no arbitrary-precision math needed. A value
 * that doesn't fit a native int (not something orderedUuid() itself ever produces) still throws
 * the same exception Ramsey's DegradedNumberConverter would have, so behavior for that case is
 * unchanged from before this fallback existed.
 *
 * #-13845
 */
class CUuid_NativeNumberConverter implements Ramsey\Uuid\Converter\NumberConverterInterface {
    /**
     * @param string $hex
     *
     * @return string
     */
    public function fromHex($hex) {
        $number = hexdec($hex);

        // hexdec() silently returns a float instead of int once the value overflows
        // PHP_INT_MAX on a 64-bit build - reject that instead of returning a lossy value.
        if (!is_int($number)) {
            throw $this->unsatisfiedDependencyException(__METHOD__);
        }

        return (string) $number;
    }

    /**
     * @param string|int $integer
     *
     * @return string
     */
    public function toHex($integer) {
        $number = (string) $integer;
        $asInt = (int) $number;

        // Round-trips back to the same decimal string only if $number was exactly
        // representable as a native int - anything else (32-bit builds included, where
        // PHP_INT_SIZE < 8) is rejected rather than silently truncated.
        if ((string) $asInt !== $number) {
            throw $this->unsatisfiedDependencyException(__METHOD__);
        }

        return dechex($asInt);
    }

    /**
     * @param string $method
     *
     * @return Ramsey\Uuid\Exception\UnsatisfiedDependencyException
     */
    protected function unsatisfiedDependencyException($method) {
        return new Ramsey\Uuid\Exception\UnsatisfiedDependencyException(
            'Cannot call ' . $method . ' on a number too large for a native PHP int; '
            . 'install the gmp extension or moontoast/math (bcmath) for arbitrary-precision support.'
        );
    }
}
