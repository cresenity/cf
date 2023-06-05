<?php

class CModel_Casts_Json {
    /**
     * The custom JSON encoder.
     *
     * @var null|callable
     */
    protected static $encoder;

    /**
     * The custom JSON decode.
     *
     * @var null|callable
     */
    protected static $decoder;

    /**
     * Encode the given value.
     */
    public static function encode(mixed $value): mixed {
        return isset(static::$encoder) ? (static::$encoder)($value) : json_encode($value);
    }

    /**
     * Decode the given value.
     */
    public static function decode(mixed $value, ?bool $associative = true): mixed {
        return isset(static::$decoder)
                ? (static::$decoder)($value, $associative)
                : json_decode($value, $associative);
    }

    /**
     * Encode all values using the given callable.
     */
    public static function encodeUsing(?callable $encoder): void {
        static::$encoder = $encoder;
    }

    /**
     * Decode all values using the given callable.
     */
    public static function decodeUsing(?callable $decoder): void {
        static::$decoder = $decoder;
    }
}
