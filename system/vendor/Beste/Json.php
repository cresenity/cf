<?php

declare(strict_types=1);

namespace Beste;

use Throwable;
use SplFileInfo;
use JsonException;
use SplFileObject;
use UnexpectedValueException;

final class Json {
    private const ENCODE_DEFAULT = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private const ENCODE_PRETTY = self::ENCODE_DEFAULT | JSON_PRETTY_PRINT;

    private const DECODE_DEFAULT = JSON_BIGINT_AS_STRING;

    /**
     * @throws UnexpectedValueException
     *
     * @return ($forceArray is true ? array<mixed> : mixed)
     */
    public static function decode(string $json, ?bool $forceArray = null) {
        $forceArray ??= false;
        $flags = $forceArray ? JSON_OBJECT_AS_ARRAY : 0;

        try {
            return json_decode($json, $forceArray, 512, $flags | self::DECODE_DEFAULT | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException($e->getMessage());
        }
    }

    /**
     * @param non-empty-string $path
     *
     * @throws UnexpectedValueException
     *
     * @return ($forceArray is true ? array<mixed> : mixed)
     */
    public static function decodeFile(string $path, ?bool $forceArray = null) {
        if (!is_readable($path)) {
            throw new UnexpectedValueException("The file at '$path' is not readable");
        }

        if (is_dir($path)) {
            throw new UnexpectedValueException("'$path' points to a directory");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new UnexpectedValueException("The file at '$path' is not readable");
        }

        if ($contents === '') {
            throw new UnexpectedValueException("The file at '$path' is empty");
        }

        return self::decode($contents, $forceArray);
    }

    /**
     * @param mixed    $data
     * @param null|int $options
     *
     * @throws UnexpectedValueException
     */
    public static function encode($data, ?int $options = null): string {
        $options ??= 0;

        try {
            return json_encode($data, $options | self::ENCODE_DEFAULT | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new UnexpectedValueException($e->getMessage());
        }
    }

    /**
     * @param mixed    $value
     * @param null|int $options
     *
     * @throws UnexpectedValueException
     *
     * @return string
     */
    public static function pretty($value, ?int $options = null): string {
        $options ??= 0;

        return self::encode($value, $options | self::ENCODE_PRETTY);
    }
}
