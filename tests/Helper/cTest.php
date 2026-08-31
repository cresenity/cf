<?php
use Mockery as m;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

// @codingStandardsIgnoreStart
class cTest extends TestCase {
    // @codingStandardsIgnoreEnd

    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testE() {
        $str = 'A \'quote\' is <b>bold</b>';
        $this->assertSame('A &#039;quote&#039; is &lt;b&gt;bold&lt;/b&gt;', c::e($str));
        $html = m::mock(CBase_HtmlString::class);
        $html->shouldReceive('toHtml')->andReturn($str);
        $this->assertEquals($str, c::e($html));
    }

    /**
     * Blade's {{ }} compiles to c::e(), so an array-typed value here (e.g. a
     * scanner sending "?field[]=x") used to crash htmlspecialchars() with a
     * TypeError on every view that echoed it.
     *
     * @return void
     */
    public function testEDoesNotCrashOnArrayInput() {
        $this->assertSame('', c::e(['a', 'b']));
        $this->assertSame('', c::e([]));
    }

    public function testClassBasename() {
        $this->assertSame('Baz', c::classBasename('Foo\Bar\Baz'));
        $this->assertSame('Baz', c::classBasename('Baz'));
    }

    public function testValue() {
        $this->assertSame('foo', c::value('foo'));
        $this->assertSame('foo', c::value(function () {
            return 'foo';
        }));
        $this->assertSame('foo', c::value(function ($arg) {
            return $arg;
        }, 'foo'));
    }

    /**
     * Regression test: c::value() used to only unwrap-and-invoke
     * \Opis\Closure\SerializableClosure, not CFunction_SerializableClosure
     * (the framework's own, now-preferred wrapper) - a CFunction_SerializableClosure
     * passed to c::value() silently came back unchanged (the wrapper object
     * itself, never invoked) since it isn't a \Closure instance either.
     */
    public function testValueInvokesBothSerializableClosureWrapperTypes() {
        $this->assertSame('foo', c::value(new CFunction_SerializableClosure(function () {
            return 'foo';
        })));
        $this->assertSame('foo', c::value(new CFunction_SerializableClosure(function ($arg) {
            return $arg;
        }), 'foo'));

        $this->assertSame('foo', c::value(new \Opis\Closure\SerializableClosure(function () {
            return 'foo';
        })));
    }

    public function testObjectGet() {
        $class = new stdClass();
        $class->name = new stdClass();
        $class->name->first = 'Taylor';

        $this->assertSame('Taylor', c::objectGet($class, 'name.first'));
    }

    public function testDataGet() {
        $object = (object) ['users' => ['name' => ['Taylor', 'Otwell']]];
        $array = [(object) ['users' => [(object) ['name' => 'Taylor']]]];
        $dottedArray = ['users' => ['first.name' => 'Taylor', 'middle.name' => null]];
        $arrayAccess = new SupportTestArrayAccess(['price' => 56, 'user' => new SupportTestArrayAccess(['name' => 'John']), 'email' => null]);

        $this->assertSame('Taylor', c::get($object, 'users.name.0'));
        $this->assertSame('Taylor', c::get($array, '0.users.0.name'));
        $this->assertNull(c::get($array, '0.users.3'));
        $this->assertSame('Not found', c::get($array, '0.users.3', 'Not found'));
        $this->assertSame('Not found', c::get($array, '0.users.3', function () {
            return 'Not found';
        }));
        $this->assertSame('Taylor', c::get($dottedArray, ['users', 'first.name']));
        $this->assertNull(c::get($dottedArray, ['users', 'middle.name']));
        $this->assertSame('Not found', c::get($dottedArray, ['users', 'last.name'], 'Not found'));
        $this->assertEquals(56, c::get($arrayAccess, 'price'));
        $this->assertSame('John', c::get($arrayAccess, 'user.name'));
        $this->assertSame('void', c::get($arrayAccess, 'foo', 'void'));
        $this->assertSame('void', c::get($arrayAccess, 'user.foo', 'void'));
        $this->assertNull(c::get($arrayAccess, 'foo'));
        $this->assertNull(c::get($arrayAccess, 'user.foo'));
        $this->assertNull(c::get($arrayAccess, 'email', 'Not found'));
    }

    public function testDataGetWithNestedArrays() {
        $array = [
            ['name' => 'taylor', 'email' => 'cresenity@gmail.com'],
            ['name' => 'abigail'],
            ['name' => 'dayle'],
        ];

        $this->assertEquals(['taylor', 'abigail', 'dayle'], c::get($array, '*.name'));
        $this->assertEquals(['cresenity@gmail.com', null, null], c::get($array, '*.email', 'irrelevant'));

        $array = [
            'users' => [
                ['first' => 'taylor', 'last' => 'otwell', 'email' => 'cresenity@gmail.com'],
                ['first' => 'abigail', 'last' => 'otwell'],
                ['first' => 'dayle', 'last' => 'rees'],
            ],
            'posts' => null,
        ];

        $this->assertEquals(['taylor', 'abigail', 'dayle'], c::get($array, 'users.*.first'));
        $this->assertEquals(['cresenity@gmail.com', null, null], c::get($array, 'users.*.email', 'irrelevant'));
        $this->assertSame('not found', c::get($array, 'posts.*.date', 'not found'));
        $this->assertNull(c::get($array, 'posts.*.date'));
    }

    public function testDataGetWithDoubleNestedArraysCollapsesResult() {
        $array = [
            'posts' => [
                [
                    'comments' => [
                        ['author' => 'taylor', 'likes' => 4],
                        ['author' => 'abigail', 'likes' => 3],
                    ],
                ],
                [
                    'comments' => [
                        ['author' => 'abigail', 'likes' => 2],
                        ['author' => 'dayle'],
                    ],
                ],
                [
                    'comments' => [
                        ['author' => 'dayle'],
                        ['author' => 'taylor', 'likes' => 1],
                    ],
                ],
            ],
        ];

        $this->assertEquals(['taylor', 'abigail', 'abigail', 'dayle', 'dayle', 'taylor'], c::get($array, 'posts.*.comments.*.author'));
        $this->assertEquals([4, 3, 2, null, null, 1], c::get($array, 'posts.*.comments.*.likes'));
        $this->assertEquals([], c::get($array, 'posts.*.users.*.name', 'irrelevant'));
        $this->assertEquals([], c::get($array, 'posts.*.users.*.name'));
    }

    public function testDataFill() {
        $data = ['foo' => 'bar'];

        $this->assertEquals(['foo' => 'bar', 'baz' => 'boom'], c::fill($data, 'baz', 'boom'));
        $this->assertEquals(['foo' => 'bar', 'baz' => 'boom'], c::fill($data, 'baz', 'noop'));
        $this->assertEquals(['foo' => [], 'baz' => 'boom'], c::fill($data, 'foo.*', 'noop'));
        $this->assertEquals(
            ['foo' => ['bar' => 'kaboom'], 'baz' => 'boom'],
            c::fill($data, 'foo.bar', 'kaboom')
        );
    }

    public function testDataFillWithStar() {
        $data = ['foo' => 'bar'];

        $this->assertEquals(
            ['foo' => []],
            c::fill($data, 'foo.*.bar', 'noop')
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => [['baz' => 'original'], []]],
            c::fill($data, 'bar', [['baz' => 'original'], []])
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => [['baz' => 'original'], ['baz' => 'boom']]],
            c::fill($data, 'bar.*.baz', 'boom')
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => [['baz' => 'original'], ['baz' => 'boom']]],
            c::fill($data, 'bar.*', 'noop')
        );
    }

    public function testDataFillWithDoubleStar() {
        $data = [
            'posts' => [
                (object) [
                    'comments' => [
                        (object) ['name' => 'First'],
                        (object) [],
                    ],
                ],
                (object) [
                    'comments' => [
                        (object) [],
                        (object) ['name' => 'Second'],
                    ],
                ],
            ],
        ];

        c::fill($data, 'posts.*.comments.*.name', 'Filled');

        $this->assertEquals([
            'posts' => [
                (object) [
                    'comments' => [
                        (object) ['name' => 'First'],
                        (object) ['name' => 'Filled'],
                    ],
                ],
                (object) [
                    'comments' => [
                        (object) ['name' => 'Filled'],
                        (object) ['name' => 'Second'],
                    ],
                ],
            ],
        ], $data);
    }

    public function testDataSet() {
        $data = ['foo' => 'bar'];

        $this->assertEquals(
            ['foo' => 'bar', 'baz' => 'boom'],
            c::set($data, 'baz', 'boom')
        );

        $this->assertEquals(
            ['foo' => 'bar', 'baz' => 'kaboom'],
            c::set($data, 'baz', 'kaboom')
        );

        $this->assertEquals(
            ['foo' => [], 'baz' => 'kaboom'],
            c::set($data, 'foo.*', 'noop')
        );

        $this->assertEquals(
            ['foo' => ['bar' => 'boom'], 'baz' => 'kaboom'],
            c::set($data, 'foo.bar', 'boom')
        );

        $this->assertEquals(
            ['foo' => ['bar' => 'boom'], 'baz' => ['bar' => 'boom']],
            c::set($data, 'baz.bar', 'boom')
        );

        $this->assertEquals(
            ['foo' => ['bar' => 'boom'], 'baz' => ['bar' => ['boom' => ['kaboom' => 'boom']]]],
            c::set($data, 'baz.bar.boom.kaboom', 'boom')
        );
    }

    public function testDataSetWithStar() {
        $data = ['foo' => 'bar'];

        $this->assertEquals(
            ['foo' => []],
            c::set($data, 'foo.*.bar', 'noop')
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => [['baz' => 'original'], []]],
            c::set($data, 'bar', [['baz' => 'original'], []])
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => [['baz' => 'boom'], ['baz' => 'boom']]],
            c::set($data, 'bar.*.baz', 'boom')
        );

        $this->assertEquals(
            ['foo' => [], 'bar' => ['overwritten', 'overwritten']],
            c::set($data, 'bar.*', 'overwritten')
        );
    }

    public function testDataSetWithDoubleStar() {
        $data = [
            'posts' => [
                (object) [
                    'comments' => [
                        (object) ['name' => 'First'],
                        (object) [],
                    ],
                ],
                (object) [
                    'comments' => [
                        (object) [],
                        (object) ['name' => 'Second'],
                    ],
                ],
            ],
        ];

        c::set($data, 'posts.*.comments.*.name', 'Filled');

        $this->assertEquals([
            'posts' => [
                (object) [
                    'comments' => [
                        (object) ['name' => 'Filled'],
                        (object) ['name' => 'Filled'],
                    ],
                ],
                (object) [
                    'comments' => [
                        (object) ['name' => 'Filled'],
                        (object) ['name' => 'Filled'],
                    ],
                ],
            ],
        ], $data);
    }

    public function testHead() {
        $array = ['a', 'b', 'c'];
        $this->assertSame('a', c::head($array));
    }

    public function testLast() {
        $array = ['a', 'b', 'c'];
        $this->assertSame('c', c::last($array));
    }

    public function testClassUsesRecursiveShouldReturnTraitsOnParentClasses() {
        $this->assertSame(
            [
                SupportTestTraitTwo::class => SupportTestTraitTwo::class,
                SupportTestTraitOne::class => SupportTestTraitOne::class,
            ],
            c::classUsesRecursive(SupportTestClassTwo::class)
        );
    }

    public function testClassUsesRecursiveAcceptsObject() {
        $this->assertSame(
            [
                SupportTestTraitTwo::class => SupportTestTraitTwo::class,
                SupportTestTraitOne::class => SupportTestTraitOne::class,
            ],
            c::classUsesRecursive(new SupportTestClassTwo())
        );
    }

    public function testClassUsesRecursiveReturnParentTraitsFirst() {
        $this->assertSame(
            [
                SupportTestTraitTwo::class => SupportTestTraitTwo::class,
                SupportTestTraitOne::class => SupportTestTraitOne::class,
                SupportTestTraitThree::class => SupportTestTraitThree::class,
            ],
            c::classUsesRecursive(SupportTestClassThree::class)
        );
    }

    public function testTap() {
        $object = (object) ['id' => 1];
        $this->assertEquals(2, c::tap($object, function ($object) {
            $object->id = 2;
        })->id);

        $mock = m::mock();
        $mock->shouldReceive('foo')->once()->andReturn('bar');
        $this->assertEquals($mock, c::tap($mock)->foo());
    }

    public function testThrow() {
        $this->expectException(LogicException::class);

        c::throwIf(true, new LogicException());
    }

    public function testThrowDefaultException() {
        $this->expectException(RuntimeException::class);

        c::throwIf(true);
    }

    public function testThrowExceptionWithMessage() {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('test');

        c::throwIf(true, 'test');
    }

    public function testThrowExceptionAsStringWithMessage() {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('test');

        c::throwIf(true, LogicException::class, 'test');
    }

    public function testThrowUnless() {
        $this->expectException(LogicException::class);

        c::throwUnless(false, new LogicException());
    }

    public function testThrowUnlessDefaultException() {
        $this->expectException(RuntimeException::class);

        c::throwUnless(false);
    }

    public function testThrowUnlessExceptionWithMessage() {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('test');

        c::throwUnless(false, 'test');
    }

    public function testThrowUnlessExceptionAsStringWithMessage() {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('test');

        c::throwUnless(false, LogicException::class, 'test');
    }

    public function testThrowReturnIfNotThrown() {
        $this->assertSame('foo', c::throwUnless('foo', new RuntimeException()));
    }

    public function testThrowWithString() {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test Message');

        c::throwIf(true, RuntimeException::class, 'Test Message');
    }

    public function testOptional() {
        $this->assertNull(c::optional(null)->something());

        $this->assertEquals(10, c::optional(new class() {
            public function something() {
                return 10;
            }
        })->something());
    }

    public function testOptionalWithCallback() {
        $this->assertNull(c::optional(null, function () {
            throw new RuntimeException(
                'The optional callback should not be called for null'
            );
        }));

        $this->assertEquals(10, c::optional(5, function ($number) {
            return $number * 2;
        }));
    }

    public function testOptionalWithArray() {
        $this->assertSame('here', c::optional(['present' => 'here'])['present']);
        $this->assertNull(c::optional(null)['missing']);
        $this->assertNull(c::optional(['present' => 'here'])->missing);
    }

    public function testOptionalReturnsObjectPropertyOrNull() {
        $this->assertSame('bar', c::optional((object) ['foo' => 'bar'])->foo);
        $this->assertNull(c::optional(['foo' => 'bar'])->foo);
        $this->assertNull(c::optional((object) ['foo' => 'bar'])->bar);
    }

    public function testOptionalDeterminesWhetherKeyIsSet() {
        $this->assertTrue(isset(c::optional(['foo' => 'bar'])['foo']));
        $this->assertFalse(isset(c::optional(['foo' => 'bar'])['bar']));
        $this->assertFalse(isset(c::optional()['bar']));
    }

    public function testOptionalAllowsToSetKey() {
        $optional = c::optional([]);
        $optional['foo'] = 'bar';
        $this->assertSame('bar', $optional['foo']);

        $optional = c::optional(null);
        $optional['foo'] = 'bar';
        $this->assertFalse(isset($optional['foo']));
    }

    public function testOptionalAllowToUnsetKey() {
        $optional = c::optional(['foo' => 'bar']);
        $this->assertTrue(isset($optional['foo']));
        unset($optional['foo']);
        $this->assertFalse(isset($optional['foo']));

        $optional = c::optional((object) ['foo' => 'bar']);
        $this->assertFalse(isset($optional['foo']));
        $optional['foo'] = 'bar';
        $this->assertFalse(isset($optional['foo']));
    }

    public function testOptionalIsMacroable() {
        COptional::macro('present', function () {
            if (is_object($this->value)) {
                return $this->value->present();
            }

            return new COptional(null);
        });

        $this->assertNull(c::optional(null)->present()->something());

        $this->assertSame('$10.00', c::optional(new class() {
            public function present() {
                return new class() {
                    public function something() {
                        return '$10.00';
                    }
                };
            }
        })->present()->something());
    }

    public function testRetry() {
        $startTime = microtime(true);

        $attempts = c::retry(2, function ($attempts) {
            if ($attempts > 1) {
                return $attempts;
            }

            throw new RuntimeException();
        }, 100);

        // Make sure we made two attempts
        $this->assertEquals(2, $attempts);

        // Make sure we waited 100ms for the first attempt
        $this->assertEqualsWithDelta(0.1, microtime(true) - $startTime, 0.02);
    }

    public function testRetryWithPassingSleepCallback() {
        $startTime = microtime(true);

        $attempts = c::retry(3, function ($attempts) {
            if ($attempts > 2) {
                return $attempts;
            }

            throw new RuntimeException();
        }, function ($attempt) {
            return $attempt * 100;
        });

        // Make sure we made three attempts
        $this->assertEquals(3, $attempts);

        // Make sure we waited 300ms for the first two attempts
        $this->assertEqualsWithDelta(0.3, microtime(true) - $startTime, 0.02);
    }

    public function testRetryWithPassingWhenCallback() {
        $startTime = microtime(true);

        $attempts = c::retry(2, function ($attempts) {
            if ($attempts > 1) {
                return $attempts;
            }

            throw new RuntimeException();
        }, 100, function ($ex) {
            return true;
        });

        // Make sure we made two attempts
        $this->assertEquals(2, $attempts);

        // Make sure we waited 100ms for the first attempt
        $this->assertEqualsWithDelta(0.1, microtime(true) - $startTime, 0.02);
    }

    public function testRetryWithFailingWhenCallback() {
        $this->expectException(RuntimeException::class);

        c::retry(2, function ($attempts) {
            if ($attempts > 1) {
                return $attempts;
            }

            throw new RuntimeException();
        }, 100, function ($ex) {
            return false;
        });
    }

    public function testTransform() {
        $this->assertEquals(10, c::transform(5, function ($value) {
            return $value * 2;
        }));

        $this->assertNull(c::transform(null, function () {
            return 10;
        }));
    }

    public function testTransformDefaultWhenBlank() {
        $this->assertSame('baz', c::transform(null, function () {
            return 'bar';
        }, 'baz'));

        $this->assertSame('baz', c::transform('', function () {
            return 'bar';
        }, function () {
            return 'baz';
        }));
    }

    public function testWith() {
        $this->assertEquals(10, c::with(10));

        $this->assertEquals(10, c::with(5, function ($five) {
            return $five + 5;
        }));
    }

    public function testEnv() {
        $_SERVER['foo'] = 'bar';

        $this->assertSame('bar', c::env('foo'));
        $this->assertSame('bar', CEnv::get('foo'));
    }

    public function testEnvTrue() {
        $_SERVER['foo'] = 'true';
        $this->assertTrue(c::env('foo'));

        $_SERVER['foo'] = '(true)';
        $this->assertTrue(c::env('foo'));
    }

    public function testEnvFalse() {
        $_SERVER['foo'] = 'false';
        $this->assertFalse(c::env('foo'));

        $_SERVER['foo'] = '(false)';
        $this->assertFalse(c::env('foo'));
    }

    public function testEnvEmpty() {
        $_SERVER['foo'] = '';
        $this->assertSame('', c::env('foo'));

        $_SERVER['foo'] = 'empty';
        $this->assertSame('', c::env('foo'));

        $_SERVER['foo'] = '(empty)';
        $this->assertSame('', c::env('foo'));
    }

    public function testEnvNull() {
        $_SERVER['foo'] = 'null';
        $this->assertNull(c::env('foo'));

        $_SERVER['foo'] = '(null)';
        $this->assertNull(c::env('foo'));
    }

    public function testEnvDefault() {
        $_SERVER['foo'] = 'bar';
        $this->assertSame('bar', c::env('foo', 'default'));

        $_SERVER['foo'] = '';
        $this->assertSame('', c::env('foo', 'default'));

        unset($_SERVER['foo']);
        $this->assertSame('default', c::env('foo', 'default'));

        $_SERVER['foo'] = null;
        $this->assertSame('default', c::env('foo', 'default'));
    }

    public function testEnvEscapedString() {
        $_SERVER['foo'] = '"null"';
        $this->assertSame('null', c::env('foo'));

        $_SERVER['foo'] = "'null'";
        $this->assertSame('null', c::env('foo'));

        $_SERVER['foo'] = 'x"null"x'; // this should not be unquoted
        $this->assertSame('x"null"x', c::env('foo'));
    }

    public function testGetFromSERVERFirst() {
        $_ENV['foo'] = 'From $_ENV';
        $_SERVER['foo'] = 'From $_SERVER';
        $this->assertSame('From $_SERVER', c::env('foo'));
    }

    public function providesPregReplaceArrayData() {
        $pointerArray = ['Crese', 'Nity'];

        next($pointerArray);

        return [
            ['/:[a-z_]+/', ['8:30', '9:00'], 'The event will take place between :start and :end', 'The event will take place between 8:30 and 9:00'],
            ['/%s/', ['Crese'], 'Hi, %s', 'Hi, Crese'],
            ['/%s/', ['Crese', 'Nity'], 'Hi, %s %s', 'Hi, Crese Nity'],
            ['/%s/', [], 'Hi, %s %s', 'Hi,  '],
            ['/%s/', ['a', 'b', 'c'], 'Hi', 'Hi'],
            ['//', [], '', ''],
            ['/%s/', ['a'], '', ''],
            // The internal pointer of this array is not at the beginning
            ['/%s/', $pointerArray, 'Hi, %s %s', 'Hi, Crese Nity'],
        ];
    }

    /**
     * @param mixed $pattern
     * @param mixed $replacements
     * @param mixed $subject
     * @param mixed $expectedOutput
     * @dataProvider providesPregReplaceArrayData
     */
    public function testPregReplaceArray($pattern, $replacements, $subject, $expectedOutput) {
        $this->assertSame(
            $expectedOutput,
            c::pregReplaceArray($pattern, $replacements, $subject)
        );
    }
}

// @codingStandardsIgnoreStart

trait SupportTestTraitOne {
}

trait SupportTestTraitTwo {
    use SupportTestTraitOne;
}

class SupportTestClassOne {
    use SupportTestTraitTwo;
}

class SupportTestClassTwo extends SupportTestClassOne {
}

trait SupportTestTraitThree {
}

class SupportTestClassThree extends SupportTestClassTwo {
    use SupportTestTraitThree;
}

class SupportTestArrayAccess implements ArrayAccess {
    protected $attributes = [];

    public function __construct($attributes = []) {
        $this->attributes = $attributes;
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        return array_key_exists($offset, $this->attributes);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->attributes[$offset];
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        $this->attributes[$offset] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) {
        unset($this->attributes[$offset]);
    }
}
