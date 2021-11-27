<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPUnit\Framework;

use DOMAttr;
use Countable;
use DOMElement;
use ArrayAccess;
use DOMDocument;
use const PHP_EOL;
use function count;
use function assert;
use function is_int;
use function strpos;
use function explode;
use function implode;
use function is_bool;
use function sprintf;
use PHPUnit\Util\Xml;
use function is_array;
use PHPUnit\Util\Type;
use function is_object;
use function is_string;
use function preg_match;
use function preg_split;
use function array_shift;
use function is_iterable;
use function class_exists;
use function array_unshift;
use function func_get_args;
use function debug_backtrace;
use function interface_exists;
use function file_get_contents;
use const DEBUG_BACKTRACE_IGNORE_ARGS;
use PHPUnit\Util\Xml\Loader as XmlLoader;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\IsAnything;
use PHPUnit\Framework\Constraint\Math\IsNan;
use PHPUnit\Framework\Constraint\IsIdentical;
use PHPUnit\Framework\Constraint\JsonMatches;
use PHPUnit\Framework\Constraint\Type\IsNull;
use PHPUnit\Framework\Constraint\Type\IsType;
use PHPUnit\Framework\Constraint\Math\IsFinite;
use PHPUnit\Framework\Constraint\String\IsJson;
use PHPUnit\Framework\Constraint\Boolean\IsTrue;
use PHPUnit\Framework\Constraint\Boolean\IsFalse;
use PHPUnit\Framework\Constraint\Math\IsInfinite;
use PHPUnit\Framework\Exception\SkippedTestError;
use PHPUnit\Framework\Constraint\Equality\IsEqual;
use PHPUnit\Framework\Constraint\Cardinality\Count;
use PHPUnit\Framework\Constraint\Type\IsInstanceOf;
use PHPUnit\Framework\Constraint\Operator\LogicalOr;
use PHPUnit\Framework\Exception\IncompleteTestError;
use PHPUnit\Framework\Constraint\Cardinality\IsEmpty;
use PHPUnit\Framework\Constraint\Object\ObjectEquals;
use PHPUnit\Framework\Constraint\Operator\LogicalAnd;
use PHPUnit\Framework\Constraint\Operator\LogicalNot;
use PHPUnit\Framework\Constraint\Operator\LogicalXor;
use PHPUnit\Framework\Exception\AssertionFailedError;
use PHPUnit\Framework\Constraint\Cardinality\LessThan;
use PHPUnit\Framework\Constraint\Cardinality\SameSize;
use PHPUnit\Framework\Exception\SyntheticSkippedError;
use PHPUnit\Framework\Constraint\Filesystem\FileExists;
use PHPUnit\Framework\Constraint\FileSystem\IsReadable;
use PHPUnit\Framework\Constraint\FileSystem\IsWritable;
use PHPUnit\Framework\Constraint\String\StringContains;
use PHPUnit\Framework\Constraint\String\StringEndsWith;
use PHPUnit\Framework\Constraint\Cardinality\GreaterThan;
use PHPUnit\Framework\Constraint\String\StringStartsWith;
use PHPUnit\Framework\Constraint\Traversable\ArrayHasKey;
use PHPUnit\Framework\Exception\InvalidArgumentException;
use PHPUnit\Framework\Constraint\Object\ClassHasAttribute;
use PHPUnit\Framework\Constraint\String\RegularExpression;
use PHPUnit\Framework\Constraint\Equality\IsEqualWithDelta;
use PHPUnit\Framework\Constraint\Object\ObjectHasAttribute;
use PHPUnit\Framework\Constraint\Filesystem\DirectoryExists;
use PHPUnit\Framework\Constraint\Equality\IsEqualIgnoringCase;
use PHPUnit\Framework\Constraint\Equality\IsEqualCanonicalizing;
use PHPUnit\Framework\Constraint\Object\ClassHasStaticAttribute;
use PHPUnit\Framework\Constraint\Traversable\TraversableContainsOnly;
use PHPUnit\Framework\Constraint\Traversable\TraversableContainsEqual;
use PHPUnit\Framework\Constraint\String\StringMatchesFormatDescription;
use PHPUnit\Framework\Constraint\Traversable\TraversableContainsIdentical;

/**
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise for PHPUnit
 */
abstract class Assert {
    /**
     * @var int
     */
    private static $count = 0;

    /**
     * Asserts that an array has a specified key.
     *
     * @param int|string        $key
     * @param array|ArrayAccess $array
     * @param mixed             $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertArrayHasKey($key, $array, $message = '') {
        if (!(is_int($key) || is_string($key))) {
            throw InvalidArgumentException::create(
                1,
                'integer or string'
            );
        }

        if (!(is_array($array) || $array instanceof ArrayAccess)) {
            throw InvalidArgumentException::create(
                2,
                'array or ArrayAccess'
            );
        }

        $constraint = new ArrayHasKey($key);

        static::assertThat($array, $constraint, $message);
    }

    /**
     * Asserts that an array does not have a specified key.
     *
     * @param int|string        $key
     * @param array|ArrayAccess $array
     * @param mixed             $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertArrayNotHasKey($key, $array, $message = '') {
        if (!(is_int($key) || is_string($key))) {
            throw InvalidArgumentException::create(
                1,
                'integer or string'
            );
        }

        if (!(is_array($array) || $array instanceof ArrayAccess)) {
            throw InvalidArgumentException::create(
                2,
                'array or ArrayAccess'
            );
        }

        $constraint = new LogicalNot(
            new ArrayHasKey($key)
        );

        static::assertThat($array, $constraint, $message);
    }

    /**
     * Asserts that a haystack contains a needle.
     *
     * @param mixed $needle
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertContains($needle, iterable $haystack, $message = '') {
        $constraint = new TraversableContainsIdentical($needle);

        static::assertThat($haystack, $constraint, $message);
    }

    public static function assertContainsEquals($needle, iterable $haystack, $message = '') {
        $constraint = new TraversableContainsEqual($needle);

        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * Asserts that a haystack does not contain a needle.
     *
     * @param mixed $needle
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertNotContains($needle, iterable $haystack, $message = '') {
        $constraint = new LogicalNot(
            new TraversableContainsIdentical($needle)
        );

        static::assertThat($haystack, $constraint, $message);
    }

    public static function assertNotContainsEquals($needle, iterable $haystack, $message = '') {
        $constraint = new LogicalNot(new TraversableContainsEqual($needle));

        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * Asserts that a haystack contains only values of a given type.
     *
     * @param mixed      $type
     * @param null|mixed $isNativeType
     * @param mixed      $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertContainsOnly($type, iterable $haystack, $isNativeType = null, $message = '') {
        if ($isNativeType === null) {
            $isNativeType = Type::isType($type);
        }

        static::assertThat(
            $haystack,
            new TraversableContainsOnly(
                $type,
                $isNativeType
            ),
            $message
        );
    }

    /**
     * Asserts that a haystack contains only instances of a given class name.
     *
     * @param mixed $className
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertContainsOnlyInstancesOf($className, iterable $haystack, $message = '') {
        static::assertThat(
            $haystack,
            new TraversableContainsOnly(
                $className,
                false
            ),
            $message
        );
    }

    /**
     * Asserts that a haystack does not contain only values of a given type.
     *
     * @param mixed      $type
     * @param null|mixed $isNativeType
     * @param mixed      $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotContainsOnly($type, iterable $haystack, $isNativeType = null, $message = '') {
        if ($isNativeType === null) {
            $isNativeType = Type::isType($type);
        }

        static::assertThat(
            $haystack,
            new LogicalNot(
                new TraversableContainsOnly(
                    $type,
                    $isNativeType
                )
            ),
            $message
        );
    }

    /**
     * Asserts the number of elements of an array, Countable or Traversable.
     *
     * @param Countable|iterable $haystack
     * @param mixed              $expectedCount
     * @param mixed              $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertCount($expectedCount, $haystack, $message = '') {
        if (!$haystack instanceof Countable && !is_iterable($haystack)) {
            throw InvalidArgumentException::create(2, 'countable or iterable');
        }

        static::assertThat(
            $haystack,
            new Count($expectedCount),
            $message
        );
    }

    /**
     * Asserts the number of elements of an array, Countable or Traversable.
     *
     * @param Countable|iterable $haystack
     * @param mixed              $expectedCount
     * @param mixed              $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertNotCount($expectedCount, $haystack, $message = '') {
        if (!$haystack instanceof Countable && !is_iterable($haystack)) {
            throw InvalidArgumentException::create(2, 'countable or iterable');
        }

        $constraint = new LogicalNot(
            new Count($expectedCount)
        );

        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * Asserts that two variables are equal.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertEquals($expected, $actual, $message = '') {
        $constraint = new IsEqual($expected);

        static::assertThat($actual, $constraint, $message);
    }

    /**
     * Asserts that two variables are equal (canonicalizing).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertEqualsCanonicalizing($expected, $actual, $message = '') {
        $constraint = new IsEqualCanonicalizing($expected);

        static::assertThat($actual, $constraint, $message);
    }

    /**
     * Asserts that two variables are equal (ignoring case).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertEqualsIgnoringCase($expected, $actual, $message = '') {
        $constraint = new IsEqualIgnoringCase($expected);

        static::assertThat($actual, $constraint, $message);
    }

    /**
     * Asserts that two variables are equal (with delta).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $delta
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertEqualsWithDelta($expected, $actual, $delta, $message = '') {
        $constraint = new IsEqualWithDelta(
            $expected,
            $delta
        );

        static::assertThat($actual, $constraint, $message);
    }

    /**
     * Asserts that two variables are not equal.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotEquals($expected, $actual, $message = '') {
        $constraint = new LogicalNot(
            new IsEqual($expected)
        );

        static::assertThat($actual, $constraint, $message);
    }

    /**
     * Asserts that two variables are not equal (canonicalizing).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotEqualsCanonicalizing($expected, $actual, $message = '') {
        $constraint = new LogicalNot(
            new IsEqualCanonicalizing($expected)
        );

        static::assertThat($actual, $constraint, $message);
    }

    /**
     * Asserts that two variables are not equal (ignoring case).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotEqualsIgnoringCase($expected, $actual, $message = '') {
        $constraint = new LogicalNot(
            new IsEqualIgnoringCase($expected)
        );

        static::assertThat($actual, $constraint, $message);
    }

    /**
     * Asserts that two variables are not equal (with delta).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $delta
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotEqualsWithDelta($expected, $actual, $delta, $message = '') {
        $constraint = new LogicalNot(
            new IsEqualWithDelta(
                $expected,
                $delta
            )
        );

        static::assertThat($actual, $constraint, $message);
    }

    /**
     * @param mixed $method
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     */
    public static function assertObjectEquals(object $expected, object $actual, $method = 'equals', $message = '') {
        static::assertThat(
            $actual,
            static::objectEquals($expected, $method),
            $message
        );
    }

    /**
     * Asserts that a variable is empty.
     *
     * @psalm-assert empty $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertEmpty($actual, $message = '') {
        static::assertThat($actual, static::isEmpty(), $message);
    }

    /**
     * Asserts that a variable is not empty.
     *
     * @psalm-assert !empty $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotEmpty($actual, $message = '') {
        static::assertThat($actual, static::logicalNot(static::isEmpty()), $message);
    }

    /**
     * Asserts that a value is greater than another value.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertGreaterThan($expected, $actual, $message = '') {
        static::assertThat($actual, static::greaterThan($expected), $message);
    }

    /**
     * Asserts that a value is greater than or equal to another value.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertGreaterThanOrEqual($expected, $actual, $message = '') {
        static::assertThat(
            $actual,
            static::greaterThanOrEqual($expected),
            $message
        );
    }

    /**
     * Asserts that a value is smaller than another value.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertLessThan($expected, $actual, $message = '') {
        static::assertThat($actual, static::lessThan($expected), $message);
    }

    /**
     * Asserts that a value is smaller than or equal to another value.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertLessThanOrEqual($expected, $actual, $message = '') {
        static::assertThat($actual, static::lessThanOrEqual($expected), $message);
    }

    /**
     * Asserts that the contents of one file is equal to the contents of another
     * file.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileEquals($expected, $actual, $message = '') {
        static::assertFileExists($expected, $message);
        static::assertFileExists($actual, $message);

        $constraint = new IsEqual(file_get_contents($expected));

        static::assertThat(file_get_contents($actual), $constraint, $message);
    }

    /**
     * Asserts that the contents of one file is equal to the contents of another
     * file (canonicalizing).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileEqualsCanonicalizing($expected, $actual, $message = '') {
        static::assertFileExists($expected, $message);
        static::assertFileExists($actual, $message);

        $constraint = new IsEqualCanonicalizing(
            file_get_contents($expected)
        );

        static::assertThat(file_get_contents($actual), $constraint, $message);
    }

    /**
     * Asserts that the contents of one file is equal to the contents of another
     * file (ignoring case).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileEqualsIgnoringCase($expected, $actual, $message = '') {
        static::assertFileExists($expected, $message);
        static::assertFileExists($actual, $message);

        $constraint = new IsEqualIgnoringCase(file_get_contents($expected));

        static::assertThat(file_get_contents($actual), $constraint, $message);
    }

    /**
     * Asserts that the contents of one file is not equal to the contents of
     * another file.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileNotEquals($expected, $actual, $message = '') {
        static::assertFileExists($expected, $message);
        static::assertFileExists($actual, $message);

        $constraint = new LogicalNot(
            new IsEqual(file_get_contents($expected))
        );

        static::assertThat(file_get_contents($actual), $constraint, $message);
    }

    /**
     * Asserts that the contents of one file is not equal to the contents of another
     * file (canonicalizing).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileNotEqualsCanonicalizing($expected, $actual, $message = '') {
        static::assertFileExists($expected, $message);
        static::assertFileExists($actual, $message);

        $constraint = new LogicalNot(
            new IsEqualCanonicalizing(file_get_contents($expected))
        );

        static::assertThat(file_get_contents($actual), $constraint, $message);
    }

    /**
     * Asserts that the contents of one file is not equal to the contents of another
     * file (ignoring case).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileNotEqualsIgnoringCase($expected, $actual, $message = '') {
        static::assertFileExists($expected, $message);
        static::assertFileExists($actual, $message);

        $constraint = new LogicalNot(
            new IsEqualIgnoringCase(file_get_contents($expected))
        );

        static::assertThat(file_get_contents($actual), $constraint, $message);
    }

    /**
     * Asserts that the contents of a string is equal
     * to the contents of a file.
     *
     * @param mixed $expectedFile
     * @param mixed $actualString
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringEqualsFile($expectedFile, $actualString, $message = '') {
        static::assertFileExists($expectedFile, $message);

        $constraint = new IsEqual(file_get_contents($expectedFile));

        static::assertThat($actualString, $constraint, $message);
    }

    /**
     * Asserts that the contents of a string is equal
     * to the contents of a file (canonicalizing).
     *
     * @param mixed $expectedFile
     * @param mixed $actualString
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringEqualsFileCanonicalizing($expectedFile, $actualString, $message = '') {
        static::assertFileExists($expectedFile, $message);

        $constraint = new IsEqualCanonicalizing(file_get_contents($expectedFile));

        static::assertThat($actualString, $constraint, $message);
    }

    /**
     * Asserts that the contents of a string is equal
     * to the contents of a file (ignoring case).
     *
     * @param mixed $expectedFile
     * @param mixed $actualString
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringEqualsFileIgnoringCase($expectedFile, $actualString, $message = '') {
        static::assertFileExists($expectedFile, $message);

        $constraint = new IsEqualIgnoringCase(file_get_contents($expectedFile));

        static::assertThat($actualString, $constraint, $message);
    }

    /**
     * Asserts that the contents of a string is not equal
     * to the contents of a file.
     *
     * @param mixed $expectedFile
     * @param mixed $actualString
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringNotEqualsFile($expectedFile, $actualString, $message = '') {
        static::assertFileExists($expectedFile, $message);

        $constraint = new LogicalNot(
            new IsEqual(file_get_contents($expectedFile))
        );

        static::assertThat($actualString, $constraint, $message);
    }

    /**
     * Asserts that the contents of a string is not equal
     * to the contents of a file (canonicalizing).
     *
     * @param mixed $expectedFile
     * @param mixed $actualString
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringNotEqualsFileCanonicalizing($expectedFile, $actualString, $message = '') {
        static::assertFileExists($expectedFile, $message);

        $constraint = new LogicalNot(
            new IsEqualCanonicalizing(file_get_contents($expectedFile))
        );

        static::assertThat($actualString, $constraint, $message);
    }

    /**
     * Asserts that the contents of a string is not equal
     * to the contents of a file (ignoring case).
     *
     * @param mixed $expectedFile
     * @param mixed $actualString
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringNotEqualsFileIgnoringCase($expectedFile, $actualString, $message = '') {
        static::assertFileExists($expectedFile, $message);

        $constraint = new LogicalNot(
            new IsEqualIgnoringCase(file_get_contents($expectedFile))
        );

        static::assertThat($actualString, $constraint, $message);
    }

    /**
     * Asserts that a file/dir is readable.
     *
     * @param mixed $filename
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsReadable($filename, $message = '') {
        static::assertThat($filename, new IsReadable(), $message);
    }

    /**
     * Asserts that a file/dir exists and is not readable.
     *
     * @param mixed $filename
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotReadable($filename, $message = '') {
        static::assertThat($filename, new LogicalNot(new IsReadable()), $message);
    }

    /**
     * Asserts that a file/dir exists and is not readable.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4062
     *
     * @param mixed $filename
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotIsReadable($filename, $message = '') {
        self::createWarning('assertNotIsReadable() is deprecated and will be removed in PHPUnit 10. Refactor your code to use assertIsNotReadable() instead.');

        static::assertThat($filename, new LogicalNot(new IsReadable()), $message);
    }

    /**
     * Asserts that a file/dir exists and is writable.
     *
     * @param mixed $filename
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsWritable($filename, $message = '') {
        static::assertThat($filename, new IsWritable(), $message);
    }

    /**
     * Asserts that a file/dir exists and is not writable.
     *
     * @param mixed $filename
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotWritable($filename, $message = '') {
        static::assertThat($filename, new LogicalNot(new IsWritable()), $message);
    }

    /**
     * Asserts that a file/dir exists and is not writable.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4065
     *
     * @param mixed $filename
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotIsWritable($filename, $message = '') {
        self::createWarning('assertNotIsWritable() is deprecated and will be removed in PHPUnit 10. Refactor your code to use assertIsNotWritable() instead.');

        static::assertThat($filename, new LogicalNot(new IsWritable()), $message);
    }

    /**
     * Asserts that a directory exists.
     *
     * @param mixed $directory
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDirectoryExists($directory, $message = '') {
        static::assertThat($directory, new DirectoryExists(), $message);
    }

    /**
     * Asserts that a directory does not exist.
     *
     * @param mixed $directory
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDirectoryDoesNotExist($directory, $message = '') {
        static::assertThat($directory, new LogicalNot(new DirectoryExists()), $message);
    }

    /**
     * Asserts that a directory does not exist.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4068
     *
     * @param mixed $directory
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDirectoryNotExists($directory, $message = '') {
        self::createWarning('assertDirectoryNotExists() is deprecated and will be removed in PHPUnit 10. Refactor your code to use assertDirectoryDoesNotExist() instead.');

        static::assertThat($directory, new LogicalNot(new DirectoryExists()), $message);
    }

    /**
     * Asserts that a directory exists and is readable.
     *
     * @param mixed $directory
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDirectoryIsReadable($directory, $message = '') {
        self::assertDirectoryExists($directory, $message);
        self::assertIsReadable($directory, $message);
    }

    /**
     * Asserts that a directory exists and is not readable.
     *
     * @param mixed $directory
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDirectoryIsNotReadable($directory, $message = '') {
        self::assertDirectoryExists($directory, $message);
        self::assertIsNotReadable($directory, $message);
    }

    /**
     * Asserts that a directory exists and is not readable.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4071
     *
     * @param mixed $directory
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDirectoryNotIsReadable($directory, $message = '') {
        self::createWarning('assertDirectoryNotIsReadable() is deprecated and will be removed in PHPUnit 10. Refactor your code to use assertDirectoryIsNotReadable() instead.');

        self::assertDirectoryExists($directory, $message);
        self::assertIsNotReadable($directory, $message);
    }

    /**
     * Asserts that a directory exists and is writable.
     *
     * @param mixed $directory
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDirectoryIsWritable($directory, $message = '') {
        self::assertDirectoryExists($directory, $message);
        self::assertIsWritable($directory, $message);
    }

    /**
     * Asserts that a directory exists and is not writable.
     *
     * @param mixed $directory
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDirectoryIsNotWritable($directory, $message = '') {
        self::assertDirectoryExists($directory, $message);
        self::assertIsNotWritable($directory, $message);
    }

    /**
     * Asserts that a directory exists and is not writable.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4074
     *
     * @param mixed $directory
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDirectoryNotIsWritable($directory, $message = '') {
        self::createWarning('assertDirectoryNotIsWritable() is deprecated and will be removed in PHPUnit 10. Refactor your code to use assertDirectoryIsNotWritable() instead.');

        self::assertDirectoryExists($directory, $message);
        self::assertIsNotWritable($directory, $message);
    }

    /**
     * Asserts that a file exists.
     *
     * @param mixed $filename
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileExists($filename, $message = '') {
        static::assertThat($filename, new FileExists(), $message);
    }

    /**
     * Asserts that a file does not exist.
     *
     * @param mixed $filename
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileDoesNotExist($filename, $message = '') {
        static::assertThat($filename, new LogicalNot(new FileExists()), $message);
    }

    /**
     * Asserts that a file does not exist.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4077
     *
     * @param mixed $filename
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileNotExists($filename, $message = '') {
        self::createWarning('assertFileNotExists() is deprecated and will be removed in PHPUnit 10. Refactor your code to use assertFileDoesNotExist() instead.');

        static::assertThat($filename, new LogicalNot(new FileExists()), $message);
    }

    /**
     * Asserts that a file exists and is readable.
     *
     * @param mixed $file
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileIsReadable($file, $message = '') {
        self::assertFileExists($file, $message);
        self::assertIsReadable($file, $message);
    }

    /**
     * Asserts that a file exists and is not readable.
     *
     * @param mixed $file
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileIsNotReadable($file, $message = '') {
        self::assertFileExists($file, $message);
        self::assertIsNotReadable($file, $message);
    }

    /**
     * Asserts that a file exists and is not readable.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4080
     *
     * @param mixed $file
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileNotIsReadable($file, $message = '') {
        self::createWarning('assertFileNotIsReadable() is deprecated and will be removed in PHPUnit 10. Refactor your code to use assertFileIsNotReadable() instead.');

        self::assertFileExists($file, $message);
        self::assertIsNotReadable($file, $message);
    }

    /**
     * Asserts that a file exists and is writable.
     *
     * @param mixed $file
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileIsWritable($file, $message = '') {
        self::assertFileExists($file, $message);
        self::assertIsWritable($file, $message);
    }

    /**
     * Asserts that a file exists and is not writable.
     *
     * @param mixed $file
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileIsNotWritable($file, $message = '') {
        self::assertFileExists($file, $message);
        self::assertIsNotWritable($file, $message);
    }

    /**
     * Asserts that a file exists and is not writable.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4083
     *
     * @param mixed $file
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFileNotIsWritable($file, $message = '') {
        self::createWarning('assertFileNotIsWritable() is deprecated and will be removed in PHPUnit 10. Refactor your code to use assertFileIsNotWritable() instead.');

        self::assertFileExists($file, $message);
        self::assertIsNotWritable($file, $message);
    }

    /**
     * Asserts that a condition is true.
     *
     * @psalm-assert true $condition
     *
     * @param mixed $condition
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertTrue($condition, $message = '') {
        static::assertThat($condition, static::isTrue(), $message);
    }

    /**
     * Asserts that a condition is not true.
     *
     * @psalm-assert !true $condition
     *
     * @param mixed $condition
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotTrue($condition, $message = '') {
        static::assertThat($condition, static::logicalNot(static::isTrue()), $message);
    }

    /**
     * Asserts that a condition is false.
     *
     * @psalm-assert false $condition
     *
     * @param mixed $condition
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFalse($condition, $message = '') {
        static::assertThat($condition, static::isFalse(), $message);
    }

    /**
     * Asserts that a condition is not false.
     *
     * @psalm-assert !false $condition
     *
     * @param mixed $condition
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotFalse($condition, $message = '') {
        static::assertThat($condition, static::logicalNot(static::isFalse()), $message);
    }

    /**
     * Asserts that a variable is null.
     *
     * @psalm-assert null $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNull($actual, $message = '') {
        static::assertThat($actual, static::isNull(), $message);
    }

    /**
     * Asserts that a variable is not null.
     *
     * @psalm-assert !null $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotNull($actual, $message = '') {
        static::assertThat($actual, static::logicalNot(static::isNull()), $message);
    }

    /**
     * Asserts that a variable is finite.
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertFinite($actual, $message = '') {
        static::assertThat($actual, static::isFinite(), $message);
    }

    /**
     * Asserts that a variable is infinite.
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertInfinite($actual, $message = '') {
        static::assertThat($actual, static::isInfinite(), $message);
    }

    /**
     * Asserts that a variable is nan.
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNan($actual, $message = '') {
        static::assertThat($actual, static::isNan(), $message);
    }

    /**
     * Asserts that a class has a specified attribute.
     *
     * @param mixed $attributeName
     * @param mixed $className
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertClassHasAttribute($attributeName, $className, $message = '') {
        if (!self::isValidClassAttributeName($attributeName)) {
            throw InvalidArgumentException::create(1, 'valid attribute name');
        }

        if (!class_exists($className)) {
            throw InvalidArgumentException::create(2, 'class name');
        }

        static::assertThat($className, new ClassHasAttribute($attributeName), $message);
    }

    /**
     * Asserts that a class does not have a specified attribute.
     *
     * @param mixed $attributeName
     * @param mixed $className
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertClassNotHasAttribute($attributeName, $className, $message = '') {
        if (!self::isValidClassAttributeName($attributeName)) {
            throw InvalidArgumentException::create(1, 'valid attribute name');
        }

        if (!class_exists($className)) {
            throw InvalidArgumentException::create(2, 'class name');
        }

        static::assertThat(
            $className,
            new LogicalNot(
                new ClassHasAttribute($attributeName)
            ),
            $message
        );
    }

    /**
     * Asserts that a class has a specified static attribute.
     *
     * @param mixed $attributeName
     * @param mixed $className
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertClassHasStaticAttribute($attributeName, $className, $message = '') {
        if (!self::isValidClassAttributeName($attributeName)) {
            throw InvalidArgumentException::create(1, 'valid attribute name');
        }

        if (!class_exists($className)) {
            throw InvalidArgumentException::create(2, 'class name');
        }

        static::assertThat(
            $className,
            new ClassHasStaticAttribute($attributeName),
            $message
        );
    }

    /**
     * Asserts that a class does not have a specified static attribute.
     *
     * @param mixed $attributeName
     * @param mixed $className
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertClassNotHasStaticAttribute($attributeName, $className, $message = '') {
        if (!self::isValidClassAttributeName($attributeName)) {
            throw InvalidArgumentException::create(1, 'valid attribute name');
        }

        if (!class_exists($className)) {
            throw InvalidArgumentException::create(2, 'class name');
        }

        static::assertThat(
            $className,
            new LogicalNot(
                new ClassHasStaticAttribute($attributeName)
            ),
            $message
        );
    }

    /**
     * Asserts that an object has a specified attribute.
     *
     * @param object $object
     * @param mixed  $attributeName
     * @param mixed  $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertObjectHasAttribute($attributeName, $object, $message = '') {
        if (!self::isValidObjectAttributeName($attributeName)) {
            throw InvalidArgumentException::create(1, 'valid attribute name');
        }

        if (!is_object($object)) {
            throw InvalidArgumentException::create(2, 'object');
        }

        static::assertThat(
            $object,
            new ObjectHasAttribute($attributeName),
            $message
        );
    }

    /**
     * Asserts that an object does not have a specified attribute.
     *
     * @param object $object
     * @param mixed  $attributeName
     * @param mixed  $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertObjectNotHasAttribute($attributeName, $object, $message = '') {
        if (!self::isValidObjectAttributeName($attributeName)) {
            throw InvalidArgumentException::create(1, 'valid attribute name');
        }

        if (!is_object($object)) {
            throw InvalidArgumentException::create(2, 'object');
        }

        static::assertThat(
            $object,
            new LogicalNot(
                new ObjectHasAttribute($attributeName)
            ),
            $message
        );
    }

    /**
     * Asserts that two variables have the same type and value.
     * Used on objects, it asserts that two variables reference
     * the same object.
     *
     * @psalm-template ExpectedType
     * @psalm-param ExpectedType $expected
     * @psalm-assert =ExpectedType $actual
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertSame($expected, $actual, $message = '') {
        static::assertThat(
            $actual,
            new IsIdentical($expected),
            $message
        );
    }

    /**
     * Asserts that two variables do not have the same type and value.
     * Used on objects, it asserts that two variables do not reference
     * the same object.
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotSame($expected, $actual, $message = '') {
        if (is_bool($expected) && is_bool($actual)) {
            static::assertNotEquals($expected, $actual, $message);
        }

        static::assertThat(
            $actual,
            new LogicalNot(
                new IsIdentical($expected)
            ),
            $message
        );
    }

    /**
     * Asserts that a variable is of a given type.
     *
     * @psalm-template ExpectedType of object
     * @psalm-param class-string<ExpectedType> $expected
     * @psalm-assert ExpectedType $actual
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertInstanceOf($expected, $actual, $message = '') {
        if (!class_exists($expected) && !interface_exists($expected)) {
            throw InvalidArgumentException::create(1, 'class or interface name');
        }

        static::assertThat(
            $actual,
            new IsInstanceOf($expected),
            $message
        );
    }

    /**
     * Asserts that a variable is not of a given type.
     *
     * @psalm-template ExpectedType of object
     * @psalm-param class-string<ExpectedType> $expected
     * @psalm-assert !ExpectedType $actual
     *
     * @param mixed $expected
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertNotInstanceOf($expected, $actual, $message = '') {
        if (!class_exists($expected) && !interface_exists($expected)) {
            throw InvalidArgumentException::create(1, 'class or interface name');
        }

        static::assertThat(
            $actual,
            new LogicalNot(
                new IsInstanceOf($expected)
            ),
            $message
        );
    }

    /**
     * Asserts that a variable is of type array.
     *
     * @psalm-assert array $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsArray($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_ARRAY),
            $message
        );
    }

    /**
     * Asserts that a variable is of type bool.
     *
     * @psalm-assert bool $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsBool($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_BOOL),
            $message
        );
    }

    /**
     * Asserts that a variable is of type float.
     *
     * @psalm-assert float $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsFloat($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_FLOAT),
            $message
        );
    }

    /**
     * Asserts that a variable is of type int.
     *
     * @psalm-assert int $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsInt($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_INT),
            $message
        );
    }

    /**
     * Asserts that a variable is of type numeric.
     *
     * @psalm-assert numeric $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNumeric($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_NUMERIC),
            $message
        );
    }

    /**
     * Asserts that a variable is of type object.
     *
     * @psalm-assert object $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsObject($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_OBJECT),
            $message
        );
    }

    /**
     * Asserts that a variable is of type resource.
     *
     * @psalm-assert resource $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsResource($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_RESOURCE),
            $message
        );
    }

    /**
     * Asserts that a variable is of type resource and is closed.
     *
     * @psalm-assert resource $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsClosedResource($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_CLOSED_RESOURCE),
            $message
        );
    }

    /**
     * Asserts that a variable is of type string.
     *
     * @psalm-assert string $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsString($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_STRING),
            $message
        );
    }

    /**
     * Asserts that a variable is of type scalar.
     *
     * @psalm-assert scalar $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsScalar($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_SCALAR),
            $message
        );
    }

    /**
     * Asserts that a variable is of type callable.
     *
     * @psalm-assert callable $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsCallable($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_CALLABLE),
            $message
        );
    }

    /**
     * Asserts that a variable is of type iterable.
     *
     * @psalm-assert iterable $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsIterable($actual, $message = '') {
        static::assertThat(
            $actual,
            new IsType(IsType::TYPE_ITERABLE),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type array.
     *
     * @psalm-assert !array $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotArray($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_ARRAY)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type bool.
     *
     * @psalm-assert !bool $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotBool($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_BOOL)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type float.
     *
     * @psalm-assert !float $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotFloat($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_FLOAT)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type int.
     *
     * @psalm-assert !int $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotInt($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_INT)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type numeric.
     *
     * @psalm-assert !numeric $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotNumeric($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_NUMERIC)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type object.
     *
     * @psalm-assert !object $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotObject($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_OBJECT)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type resource.
     *
     * @psalm-assert !resource $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotResource($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_RESOURCE)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type resource.
     *
     * @psalm-assert !resource $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotClosedResource($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_CLOSED_RESOURCE)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type string.
     *
     * @psalm-assert !string $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotString($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_STRING)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type scalar.
     *
     * @psalm-assert !scalar $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotScalar($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_SCALAR)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type callable.
     *
     * @psalm-assert !callable $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotCallable($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_CALLABLE)),
            $message
        );
    }

    /**
     * Asserts that a variable is not of type iterable.
     *
     * @psalm-assert !iterable $actual
     *
     * @param mixed $actual
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertIsNotIterable($actual, $message = '') {
        static::assertThat(
            $actual,
            new LogicalNot(new IsType(IsType::TYPE_ITERABLE)),
            $message
        );
    }

    /**
     * Asserts that a string matches a given regular expression.
     *
     * @param mixed $pattern
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertMatchesRegularExpression($pattern, $string, $message = '') {
        static::assertThat($string, new RegularExpression($pattern), $message);
    }

    /**
     * Asserts that a string matches a given regular expression.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4086
     *
     * @param mixed $pattern
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertRegExp($pattern, $string, $message = '') {
        self::createWarning('assertRegExp() is deprecated and will be removed in PHPUnit 10. Refactor your code to use assertMatchesRegularExpression() instead.');

        static::assertThat($string, new RegularExpression($pattern), $message);
    }

    /**
     * Asserts that a string does not match a given regular expression.
     *
     * @param mixed $pattern
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertDoesNotMatchRegularExpression($pattern, $string, $message = '') {
        static::assertThat(
            $string,
            new LogicalNot(
                new RegularExpression($pattern)
            ),
            $message
        );
    }

    /**
     * Asserts that a string does not match a given regular expression.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4089
     *
     * @param mixed $pattern
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertNotRegExp($pattern, $string, $message = '') {
        self::createWarning('assertNotRegExp() is deprecated and will be removed in PHPUnit 10. Refactor your code to use assertDoesNotMatchRegularExpression() instead.');

        static::assertThat(
            $string,
            new LogicalNot(
                new RegularExpression($pattern)
            ),
            $message
        );
    }

    /**
     * Assert that the size of two arrays (or `Countable` or `Traversable` objects)
     * is the same.
     *
     * @param Countable|iterable $expected
     * @param Countable|iterable $actual
     * @param mixed              $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertSameSize($expected, $actual, $message = '') {
        if (!$expected instanceof Countable && !is_iterable($expected)) {
            throw InvalidArgumentException::create(1, 'countable or iterable');
        }

        if (!$actual instanceof Countable && !is_iterable($actual)) {
            throw InvalidArgumentException::create(2, 'countable or iterable');
        }

        static::assertThat(
            $actual,
            new SameSize($expected),
            $message
        );
    }

    /**
     * Assert that the size of two arrays (or `Countable` or `Traversable` objects)
     * is not the same.
     *
     * @param Countable|iterable $expected
     * @param Countable|iterable $actual
     * @param mixed              $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertNotSameSize($expected, $actual, $message = '') {
        if (!$expected instanceof Countable && !is_iterable($expected)) {
            throw InvalidArgumentException::create(1, 'countable or iterable');
        }

        if (!$actual instanceof Countable && !is_iterable($actual)) {
            throw InvalidArgumentException::create(2, 'countable or iterable');
        }

        static::assertThat(
            $actual,
            new LogicalNot(
                new SameSize($expected)
            ),
            $message
        );
    }

    /**
     * Asserts that a string matches a given format string.
     *
     * @param mixed $format
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringMatchesFormat($format, $string, $message = '') {
        static::assertThat($string, new StringMatchesFormatDescription($format), $message);
    }

    /**
     * Asserts that a string does not match a given format string.
     *
     * @param mixed $format
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringNotMatchesFormat($format, $string, $message = '') {
        static::assertThat(
            $string,
            new LogicalNot(
                new StringMatchesFormatDescription($format)
            ),
            $message
        );
    }

    /**
     * Asserts that a string matches a given format file.
     *
     * @param mixed $formatFile
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringMatchesFormatFile($formatFile, $string, $message = '') {
        static::assertFileExists($formatFile, $message);

        static::assertThat(
            $string,
            new StringMatchesFormatDescription(
                file_get_contents($formatFile)
            ),
            $message
        );
    }

    /**
     * Asserts that a string does not match a given format string.
     *
     * @param mixed $formatFile
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringNotMatchesFormatFile($formatFile, $string, $message = '') {
        static::assertFileExists($formatFile, $message);

        static::assertThat(
            $string,
            new LogicalNot(
                new StringMatchesFormatDescription(
                    file_get_contents($formatFile)
                )
            ),
            $message
        );
    }

    /**
     * Asserts that a string starts with a given prefix.
     *
     * @param mixed $prefix
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringStartsWith($prefix, $string, $message = '') {
        static::assertThat($string, new StringStartsWith($prefix), $message);
    }

    /**
     * Asserts that a string starts not with a given prefix.
     *
     * @param string $prefix
     * @param string $string
     * @param mixed  $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringStartsNotWith($prefix, $string, $message = '') {
        static::assertThat(
            $string,
            new LogicalNot(
                new StringStartsWith($prefix)
            ),
            $message
        );
    }

    /**
     * @param mixed $needle
     * @param mixed $haystack
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringContainsString($needle, $haystack, $message = '') {
        $constraint = new StringContains($needle, false);

        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param mixed $needle
     * @param mixed $haystack
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringContainsStringIgnoringCase($needle, $haystack, $message = '') {
        $constraint = new StringContains($needle, true);

        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param mixed $needle
     * @param mixed $haystack
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringNotContainsString($needle, $haystack, $message = '') {
        $constraint = new LogicalNot(new StringContains($needle));

        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param mixed $needle
     * @param mixed $haystack
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringNotContainsStringIgnoringCase($needle, $haystack, $message = '') {
        $constraint = new LogicalNot(new StringContains($needle, true));

        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * Asserts that a string ends with a given suffix.
     *
     * @param mixed $suffix
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringEndsWith($suffix, $string, $message = '') {
        static::assertThat($string, new StringEndsWith($suffix), $message);
    }

    /**
     * Asserts that a string ends not with a given suffix.
     *
     * @param mixed $suffix
     * @param mixed $string
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertStringEndsNotWith($suffix, $string, $message = '') {
        static::assertThat(
            $string,
            new LogicalNot(
                new StringEndsWith($suffix)
            ),
            $message
        );
    }

    /**
     * Asserts that two XML files are equal.
     *
     * @param mixed $expectedFile
     * @param mixed $actualFile
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    public static function assertXmlFileEqualsXmlFile($expectedFile, $actualFile, $message = '') {
        $expected = (new XmlLoader())->loadFile($expectedFile);
        $actual = (new XmlLoader())->loadFile($actualFile);

        static::assertEquals($expected, $actual, $message);
    }

    /**
     * Asserts that two XML files are not equal.
     *
     * @param mixed $expectedFile
     * @param mixed $actualFile
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws \PHPUnit\Util\Exception
     */
    public static function assertXmlFileNotEqualsXmlFile($expectedFile, $actualFile, $message = '') {
        $expected = (new XmlLoader())->loadFile($expectedFile);
        $actual = (new XmlLoader())->loadFile($actualFile);

        static::assertNotEquals($expected, $actual, $message);
    }

    /**
     * Asserts that two XML documents are equal.
     *
     * @param DOMDocument|string $actualXml
     * @param mixed              $expectedFile
     * @param mixed              $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws \PHPUnit\Util\Xml\Exception
     */
    public static function assertXmlStringEqualsXmlFile($expectedFile, $actualXml, $message = '') {
        if (!is_string($actualXml)) {
            self::createWarning('Passing an argument of type DOMDocument for the $actualXml parameter is deprecated. Support for this will be removed in PHPUnit 10.');

            $actual = $actualXml;
        } else {
            $actual = (new XmlLoader())->load($actualXml);
        }

        $expected = (new XmlLoader())->loadFile($expectedFile);

        static::assertEquals($expected, $actual, $message);
    }

    /**
     * Asserts that two XML documents are not equal.
     *
     * @param DOMDocument|string $actualXml
     * @param mixed              $expectedFile
     * @param mixed              $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws \PHPUnit\Util\Xml\Exception
     */
    public static function assertXmlStringNotEqualsXmlFile($expectedFile, $actualXml, $message = '') {
        if (!is_string($actualXml)) {
            self::createWarning('Passing an argument of type DOMDocument for the $actualXml parameter is deprecated. Support for this will be removed in PHPUnit 10.');

            $actual = $actualXml;
        } else {
            $actual = (new XmlLoader())->load($actualXml);
        }

        $expected = (new XmlLoader())->loadFile($expectedFile);

        static::assertNotEquals($expected, $actual, $message);
    }

    /**
     * Asserts that two XML documents are equal.
     *
     * @param DOMDocument|string $expectedXml
     * @param DOMDocument|string $actualXml
     * @param mixed              $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws \PHPUnit\Util\Xml\Exception
     */
    public static function assertXmlStringEqualsXmlString($expectedXml, $actualXml, $message = '') {
        if (!is_string($expectedXml)) {
            self::createWarning('Passing an argument of type DOMDocument for the $expectedXml parameter is deprecated. Support for this will be removed in PHPUnit 10.');

            $expected = $expectedXml;
        } else {
            $expected = (new XmlLoader())->load($expectedXml);
        }

        if (!is_string($actualXml)) {
            self::createWarning('Passing an argument of type DOMDocument for the $actualXml parameter is deprecated. Support for this will be removed in PHPUnit 10.');

            $actual = $actualXml;
        } else {
            $actual = (new XmlLoader())->load($actualXml);
        }

        static::assertEquals($expected, $actual, $message);
    }

    /**
     * Asserts that two XML documents are not equal.
     *
     * @param DOMDocument|string $expectedXml
     * @param DOMDocument|string $actualXml
     * @param mixed              $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws \PHPUnit\Util\Xml\Exception
     */
    public static function assertXmlStringNotEqualsXmlString($expectedXml, $actualXml, $message = '') {
        if (!is_string($expectedXml)) {
            self::createWarning('Passing an argument of type DOMDocument for the $expectedXml parameter is deprecated. Support for this will be removed in PHPUnit 10.');

            $expected = $expectedXml;
        } else {
            $expected = (new XmlLoader())->load($expectedXml);
        }

        if (!is_string($actualXml)) {
            self::createWarning('Passing an argument of type DOMDocument for the $actualXml parameter is deprecated. Support for this will be removed in PHPUnit 10.');

            $actual = $actualXml;
        } else {
            $actual = (new XmlLoader())->load($actualXml);
        }

        static::assertNotEquals($expected, $actual, $message);
    }

    /**
     * Asserts that a hierarchy of DOMElements matches.
     *
     * @codeCoverageIgnore
     *
     * @deprecated https://github.com/sebastianbergmann/phpunit/issues/4091
     *
     * @param mixed $checkAttributes
     * @param mixed $message
     *
     * @throws AssertionFailedError
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertEqualXMLStructure(DOMElement $expectedElement, DOMElement $actualElement, $checkAttributes = false, $message = '') {
        self::createWarning('assertEqualXMLStructure() is deprecated and will be removed in PHPUnit 10.');

        $expectedElement = Xml::import($expectedElement);
        $actualElement = Xml::import($actualElement);

        static::assertSame(
            $expectedElement->tagName,
            $actualElement->tagName,
            $message
        );

        if ($checkAttributes) {
            static::assertSame(
                $expectedElement->attributes->length,
                $actualElement->attributes->length,
                sprintf(
                    '%s%sNumber of attributes on node "%s" does not match',
                    $message,
                    !empty($message) ? "\n" : '',
                    $expectedElement->tagName
                )
            );

            for ($i = 0; $i < $expectedElement->attributes->length; $i++) {
                $expectedAttribute = $expectedElement->attributes->item($i);
                $actualAttribute = $actualElement->attributes->getNamedItem($expectedAttribute->name);

                assert($expectedAttribute instanceof DOMAttr);

                if (!$actualAttribute) {
                    static::fail(
                        sprintf(
                            '%s%sCould not find attribute "%s" on node "%s"',
                            $message,
                            !empty($message) ? "\n" : '',
                            $expectedAttribute->name,
                            $expectedElement->tagName
                        )
                    );
                }
            }
        }

        Xml::removeCharacterDataNodes($expectedElement);
        Xml::removeCharacterDataNodes($actualElement);

        static::assertSame(
            $expectedElement->childNodes->length,
            $actualElement->childNodes->length,
            sprintf(
                '%s%sNumber of child nodes of "%s" differs',
                $message,
                !empty($message) ? "\n" : '',
                $expectedElement->tagName
            )
        );

        for ($i = 0; $i < $expectedElement->childNodes->length; $i++) {
            static::assertEqualXMLStructure(
                $expectedElement->childNodes->item($i),
                $actualElement->childNodes->item($i),
                $checkAttributes,
                $message
            );
        }
    }

    /**
     * Evaluates a PHPUnit\Framework\Constraint matcher object.
     *
     * @param mixed $value
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertThat($value, Constraint $constraint, $message = '') {
        self::$count += count($constraint);

        $constraint->evaluate($value, $message);
    }

    /**
     * Asserts that a string is a valid JSON string.
     *
     * @param mixed $actualJson
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertJson($actualJson, $message = '') {
        static::assertThat($actualJson, static::isJson(), $message);
    }

    /**
     * Asserts that two given JSON encoded objects or arrays are equal.
     *
     * @param mixed $expectedJson
     * @param mixed $actualJson
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertJsonStringEqualsJsonString($expectedJson, $actualJson, $message = '') {
        static::assertJson($expectedJson, $message);
        static::assertJson($actualJson, $message);

        static::assertThat($actualJson, new JsonMatches($expectedJson), $message);
    }

    /**
     * Asserts that two given JSON encoded objects or arrays are not equal.
     *
     * @param string $expectedJson
     * @param string $actualJson
     * @param mixed  $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertJsonStringNotEqualsJsonString($expectedJson, $actualJson, $message = '') {
        static::assertJson($expectedJson, $message);
        static::assertJson($actualJson, $message);

        static::assertThat(
            $actualJson,
            new LogicalNot(
                new JsonMatches($expectedJson)
            ),
            $message
        );
    }

    /**
     * Asserts that the generated JSON encoded object and the content of the given file are equal.
     *
     * @param mixed $expectedFile
     * @param mixed $actualJson
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertJsonStringEqualsJsonFile($expectedFile, $actualJson, $message = '') {
        static::assertFileExists($expectedFile, $message);
        $expectedJson = file_get_contents($expectedFile);

        static::assertJson($expectedJson, $message);
        static::assertJson($actualJson, $message);

        static::assertThat($actualJson, new JsonMatches($expectedJson), $message);
    }

    /**
     * Asserts that the generated JSON encoded object and the content of the given file are not equal.
     *
     * @param mixed $expectedFile
     * @param mixed $actualJson
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertJsonStringNotEqualsJsonFile($expectedFile, $actualJson, $message = '') {
        static::assertFileExists($expectedFile, $message);
        $expectedJson = file_get_contents($expectedFile);

        static::assertJson($expectedJson, $message);
        static::assertJson($actualJson, $message);

        static::assertThat(
            $actualJson,
            new LogicalNot(
                new JsonMatches($expectedJson)
            ),
            $message
        );
    }

    /**
     * Asserts that two JSON files are equal.
     *
     * @param mixed $expectedFile
     * @param mixed $actualFile
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertJsonFileEqualsJsonFile($expectedFile, $actualFile, $message = '') {
        static::assertFileExists($expectedFile, $message);
        static::assertFileExists($actualFile, $message);

        $actualJson = file_get_contents($actualFile);
        $expectedJson = file_get_contents($expectedFile);

        static::assertJson($expectedJson, $message);
        static::assertJson($actualJson, $message);

        $constraintExpected = new JsonMatches(
            $expectedJson
        );

        $constraintActual = new JsonMatches($actualJson);

        static::assertThat($expectedJson, $constraintActual, $message);
        static::assertThat($actualJson, $constraintExpected, $message);
    }

    /**
     * Asserts that two JSON files are not equal.
     *
     * @param mixed $expectedFile
     * @param mixed $actualFile
     * @param mixed $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public static function assertJsonFileNotEqualsJsonFile($expectedFile, $actualFile, $message = '') {
        static::assertFileExists($expectedFile, $message);
        static::assertFileExists($actualFile, $message);

        $actualJson = file_get_contents($actualFile);
        $expectedJson = file_get_contents($expectedFile);

        static::assertJson($expectedJson, $message);
        static::assertJson($actualJson, $message);

        $constraintExpected = new JsonMatches(
            $expectedJson
        );

        $constraintActual = new JsonMatches($actualJson);

        static::assertThat($expectedJson, new LogicalNot($constraintActual), $message);
        static::assertThat($actualJson, new LogicalNot($constraintExpected), $message);
    }

    /**
     * @throws Exception
     */
    public static function logicalAnd() {
        $constraints = func_get_args();

        $constraint = new LogicalAnd();
        $constraint->setConstraints($constraints);

        return $constraint;
    }

    public static function logicalOr() {
        $constraints = func_get_args();

        $constraint = new LogicalOr();
        $constraint->setConstraints($constraints);

        return $constraint;
    }

    public static function logicalNot(Constraint $constraint) {
        return new LogicalNot($constraint);
    }

    public static function logicalXor() {
        $constraints = func_get_args();

        $constraint = new LogicalXor();
        $constraint->setConstraints($constraints);

        return $constraint;
    }

    public static function anything() {
        return new IsAnything();
    }

    public static function isTrue() {
        return new IsTrue();
    }

    public static function callback(callable $callback) {
        return new Callback($callback);
    }

    public static function isFalse() {
        return new IsFalse();
    }

    public static function isJson() {
        return new IsJson();
    }

    public static function isNull() {
        return new IsNull();
    }

    public static function isFinite() {
        return new IsFinite();
    }

    public static function isInfinite() {
        return new IsInfinite();
    }

    public static function isNan() {
        return new IsNan();
    }

    public static function containsEqual($value) {
        return new TraversableContainsEqual($value);
    }

    public static function containsIdentical($value) {
        return new TraversableContainsIdentical($value);
    }

    public static function containsOnly($type) {
        return new TraversableContainsOnly($type);
    }

    public static function containsOnlyInstancesOf($className) {
        return new TraversableContainsOnly($className, false);
    }

    /**
     * @param int|string $key
     */
    public static function arrayHasKey($key) {
        return new ArrayHasKey($key);
    }

    public static function equalTo($value) {
        return new IsEqual($value, 0.0, false, false);
    }

    public static function equalToCanonicalizing($value) {
        return new IsEqualCanonicalizing($value);
    }

    public static function equalToIgnoringCase($value) {
        return new IsEqualIgnoringCase($value);
    }

    public static function equalToWithDelta($value, $delta) {
        return new IsEqualWithDelta($value, $delta);
    }

    public static function isEmpty() {
        return new IsEmpty();
    }

    public static function isWritable() {
        return new IsWritable();
    }

    public static function isReadable() {
        return new IsReadable();
    }

    public static function directoryExists() {
        return new DirectoryExists();
    }

    public static function fileExists() {
        return new FileExists();
    }

    public static function greaterThan($value) {
        return new GreaterThan($value);
    }

    public static function greaterThanOrEqual($value) {
        return static::logicalOr(
            new IsEqual($value),
            new GreaterThan($value)
        );
    }

    public static function classHasAttribute($attributeName) {
        return new ClassHasAttribute($attributeName);
    }

    public static function classHasStaticAttribute($attributeName) {
        return new ClassHasStaticAttribute($attributeName);
    }

    public static function objectHasAttribute($attributeName) {
        return new ObjectHasAttribute($attributeName);
    }

    public static function identicalTo($value) {
        return new IsIdentical($value);
    }

    public static function isInstanceOf($className) {
        return new IsInstanceOf($className);
    }

    public static function isType($type) {
        return new IsType($type);
    }

    public static function lessThan($value) {
        return new LessThan($value);
    }

    public static function lessThanOrEqual($value) {
        return static::logicalOr(
            new IsEqual($value),
            new LessThan($value)
        );
    }

    public static function matchesRegularExpression($pattern) {
        return new RegularExpression($pattern);
    }

    public static function matches($string) {
        return new StringMatchesFormatDescription($string);
    }

    public static function stringStartsWith($prefix) {
        return new StringStartsWith($prefix);
    }

    public static function stringContains($string, $case = true) {
        return new StringContains($string, $case);
    }

    public static function stringEndsWith($suffix) {
        return new StringEndsWith($suffix);
    }

    public static function countOf($count) {
        return new Count($count);
    }

    public static function objectEquals(object $object, $method = 'equals') {
        return new ObjectEquals($object, $method);
    }

    /**
     * Fails a test with the given message.
     *
     * @psalm-return never-return
     *
     * @param mixed $message
     *
     * @throws AssertionFailedError
     */
    public static function fail($message = '') {
        self::$count++;

        throw new AssertionFailedError($message);
    }

    /**
     * Mark the test as incomplete.
     *
     * @psalm-return never-return
     *
     * @param mixed $message
     *
     * @throws IncompleteTestError
     */
    public static function markTestIncomplete($message = '') {
        throw new IncompleteTestError($message);
    }

    /**
     * Mark the test as skipped.
     *
     * @psalm-return never-return
     *
     * @param mixed $message
     *
     * @throws SkippedTestError
     * @throws SyntheticSkippedError
     */
    public static function markTestSkipped($message = '') {
        if ($hint = self::detectLocationHint($message)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            array_unshift($trace, $hint);

            throw new SyntheticSkippedError($hint['message'], 0, $hint['file'], (int) $hint['line'], $trace);
        }

        throw new SkippedTestError($message);
    }

    /**
     * Return the current assertion count.
     */
    public static function getCount() {
        return self::$count;
    }

    /**
     * Reset the assertion counter.
     */
    public static function resetCount() {
        self::$count = 0;
    }

    private static function detectLocationHint($message) {
        $hint = null;
        $lines = preg_split('/\r\n|\r|\n/', $message);

        while (strpos($lines[0], '__OFFSET') !== false) {
            $offset = explode('=', array_shift($lines));

            if ($offset[0] === '__OFFSET_FILE') {
                $hint['file'] = $offset[1];
            }

            if ($offset[0] === '__OFFSET_LINE') {
                $hint['line'] = $offset[1];
            }
        }

        if ($hint) {
            $hint['message'] = implode(PHP_EOL, $lines);
        }

        return $hint;
    }

    private static function isValidObjectAttributeName($attributeName) {
        return (bool) preg_match('/[^\x00-\x1f\x7f-\x9f]+/', $attributeName);
    }

    private static function isValidClassAttributeName($attributeName) {
        return (bool) preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $attributeName);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param mixed $warning
     */
    private static function createWarning($warning) {
        foreach (debug_backtrace() as $step) {
            if (isset($step['object']) && $step['object'] instanceof TestCase) {
                assert($step['object'] instanceof TestCase);

                $step['object']->addWarning($warning);

                break;
            }
        }
    }
}
