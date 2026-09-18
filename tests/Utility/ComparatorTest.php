<?php

use PHPUnit\Framework\TestCase;

/**
 * CComparator (port sebastian/comparator + sebastian/diff): pemilihan engine per tipe oleh Factory,
 * semantik assertEquals (delta, canonicalize, ignoreCase), ComparisonFailureException dengan diff,
 * comparator kustom, dan CComparator_Differ (unified diff, diffToArray).
 */
class ComparatorTest extends TestCase {
    /**
     * @var CComparator_Factory
     */
    protected $factory;

    protected function setUp(): void {
        $this->factory = new CComparator_Factory();
    }

    /**
     * @return array
     */
    public function engineProvider() {
        return [
            'scalar' => ['a', 'b', CComparator_Engine_ScalarComparator::class],
            'numeric' => [1, '1', CComparator_Engine_NumericComparator::class],
            'double' => [1.5, 1.5, CComparator_Engine_DoubleComparator::class],
            'array' => [[1], [2], CComparator_Engine_ArrayComparator::class],
            'object' => [new stdClass(), new stdClass(), CComparator_Engine_ObjectComparator::class],
            'datetime' => [new DateTime(), new DateTimeImmutable(), CComparator_Engine_DateTimeComparator::class],
            'exception' => [new RuntimeException('a'), new RuntimeException('a'), CComparator_Engine_ExceptionComparator::class],
            'spl' => [new SplObjectStorage(), new SplObjectStorage(), CComparator_Engine_SplObjectStorageComparator::class],
            'type mismatch' => [1, 'a', CComparator_Engine_ScalarComparator::class],
            'type mismatch array' => [[1], 1, CComparator_Engine_TypeComparator::class],
        ];
    }

    /**
     * @dataProvider engineProvider
     *
     * @param mixed  $expected
     * @param mixed  $actual
     * @param string $class
     */
    public function testFactoryPicksTheEngineForTheTypes($expected, $actual, $class) {
        $this->assertInstanceOf($class, $this->factory->getComparatorFor($expected, $actual));
    }

    public function testDomNodeAndResourceEngines() {
        $a = new DOMDocument();
        $a->loadXML('<a/>');
        $this->assertInstanceOf(CComparator_Engine_DOMNodeComparator::class, $this->factory->getComparatorFor($a, $a));
        $resource = fopen('php://memory', 'r');
        $this->assertInstanceOf(CComparator_Engine_ResourceComparator::class, $this->factory->getComparatorFor($resource, $resource));
        fclose($resource);
    }

    public function testScalarEqualityWithIgnoreCaseAndTypeJuggling() {
        $comparator = new CComparator_Engine_ScalarComparator();
        $comparator->setFactory($this->factory);

        $comparator->assertEquals('abc', 'abc');
        $comparator->assertEquals('ABC', 'abc', 0.0, false, true);
        $comparator->assertEquals('1', 1, 0.0, false, false);
        $comparator->assertEquals(true, 1);
        $this->expectException(CComparator_Exception_ComparisonFailureException::class);
        $comparator->assertEquals('ABC', 'abc');
    }

    public function testNumericEqualityWithDelta() {
        $comparator = new CComparator_Engine_DoubleComparator();
        $comparator->setFactory($this->factory);

        $comparator->assertEquals(1.0, 1.05, 0.1);
        $comparator->assertEquals(1, 1.0000000001, 0.000001);
        $comparator->assertEquals(0.1 + 0.2, 0.3, PHP_FLOAT_EPSILON, false, false);
        try {
            $comparator->assertEquals(1.0, 1.5, 0.1);
            $this->fail('harus melempar');
        } catch (CComparator_Exception_ComparisonFailureException $e) {
            $this->assertEquals(1.0, $e->getExpected());
            $this->assertEquals(1.5, $e->getActual());
        }
    }

    public function testArrayEqualityRecursesAndCanonicalizesWhenAsked() {
        $comparator = new CComparator_Engine_ArrayComparator();
        $comparator->setFactory($this->factory);

        $comparator->assertEquals(['a' => 1, 'b' => [1, 2]], ['a' => 1, 'b' => [1, 2]]);
        $comparator->assertEquals(['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1], 0.0, false, false);
        $comparator->assertEquals([3, 1, 2], [1, 2, 3], 0.0, true, false);
        try {
            $comparator->assertEquals([3, 1, 2], [1, 2, 3]);
            $this->fail('tanpa canonicalize urutan berpengaruh');
        } catch (CComparator_Exception_ComparisonFailureException $e) {
            $this->assertStringContainsString('Failed asserting that two arrays are equal', $e->getMessage());
            $this->assertStringContainsString('-    0 => 3', $e->getDiff());
        }
    }

    public function testArrayFailureListsTheMissingAndExtraKeys() {
        $comparator = new CComparator_Engine_ArrayComparator();
        $comparator->setFactory($this->factory);

        try {
            $comparator->assertEquals(['a' => 1, 'c' => 3], ['a' => 1, 'b' => 2]);
            $this->fail('harus melempar');
        } catch (CComparator_Exception_ComparisonFailureException $e) {
            $diff = $e->getDiff();
            $this->assertStringContainsString("-    'c' => 3", $diff);
            $this->assertStringContainsString("+    'b' => 2", $diff);
        }
    }

    public function testObjectEqualityComparesPropertiesNotIdentity() {
        $comparator = new CComparator_Engine_ObjectComparator();
        $comparator->setFactory($this->factory);
        $a = new stdClass();
        $a->x = 1;
        $b = new stdClass();
        $b->x = 1;
        $c = new stdClass();
        $c->x = 2;

        $comparator->assertEquals($a, $b);
        $this->expectException(CComparator_Exception_ComparisonFailureException::class);
        $comparator->assertEquals($a, $c);
    }

    public function testDateTimeEqualityHonoursDeltaAndTimezones() {
        $comparator = new CComparator_Engine_DateTimeComparator();
        $comparator->setFactory($this->factory);

        $comparator->assertEquals(new DateTime('2026-01-01 10:00:00', new DateTimeZone('UTC')), new DateTime('2026-01-01 17:00:00', new DateTimeZone('Asia/Jakarta')), 0.0, false, false);
        $comparator->assertEquals(new DateTime('2026-01-01 10:00:00'), new DateTime('2026-01-01 10:00:05'), 10);
        $this->expectException(CComparator_Exception_ComparisonFailureException::class);
        $comparator->assertEquals(new DateTime('2026-01-01 10:00:00'), new DateTime('2026-01-01 10:00:05'));
    }

    public function testCustomComparatorTakesPriorityAndCanBeUnregistered() {
        $custom = new class() extends CComparator_AbstractEngine {
            public function accepts($expected, $actual) {
                return is_string($expected) && is_string($actual);
            }

            public function assertEquals($expected, $actual, $delta = 0.0, $canonicalize = false, $ignoreCase = false) {
                if (trim($expected) !== trim($actual)) {
                    throw new CComparator_Exception_ComparisonFailureException($expected, $actual, $expected, $actual, false, 'beda setelah trim');
                }
            }
        };
        $this->factory->register($custom);

        $this->assertSame($custom, $this->factory->getComparatorFor('a', 'b'));
        $this->factory->getComparatorFor(' a ', 'a')->assertEquals(' a ', 'a');
        $this->factory->unregister($custom);
        $this->assertInstanceOf(CComparator_Engine_ScalarComparator::class, $this->factory->getComparatorFor('a', 'b'));
    }

    public function testComparisonFailureExposesBothSides() {
        $e = new CComparator_Exception_ComparisonFailureException('harap', 'nyata', "'harap'", "'nyata'", false, 'pesan');

        $this->assertSame('harap', $e->getExpected());
        $this->assertSame('nyata', $e->getActual());
        $this->assertSame("'harap'", $e->getExpectedAsString());
        $this->assertStringContainsString("-'harap'", $e->getDiff());
        $this->assertStringContainsString("+'nyata'", $e->getDiff());
        $this->assertStringStartsWith('pesan', $e->toString());
    }

    public function testDifferProducesUnifiedDiffAndArrayForm() {
        $differ = new CComparator_Differ();

        $diff = $differ->diff("a\nb\nc\n", "a\nx\nc\n");
        $this->assertStringContainsString("-b\n", $diff);
        $this->assertStringContainsString("+x\n", $diff);
        $this->assertStringContainsString(" a\n", $diff);

        $array = $differ->diffToArray(['a', 'b'], ['a', 'c']);
        $this->assertSame(['a', 0], $array[0], '0 = sama');
        $this->assertSame(['b', 2], $array[1], '2 = dihapus');
        $this->assertSame(['c', 1], $array[2], '1 = ditambah');
        $this->assertSame("--- Original\n+++ New\n", $differ->diff('sama', 'sama'), 'identik → hanya header (perilaku sebastian/diff)');
    }
}
