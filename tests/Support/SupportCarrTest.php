<?php

use PHPUnit\Framework\TestCase;

/**
 * Helper array `carr` - padanan suite hulu untuk Arr, dibatasi pada method yang memang ada
 * di CF (36 dari 59). Method yang perilakunya sengaja berbeda dari hulu diberi catatan di
 * test-nya, bukan diselaraskan diam-diam.
 */
class SupportCarrTest extends TestCase {
    public function testAccessible() {
        $this->assertTrue(carr::accessible([]));
        $this->assertTrue(carr::accessible([1, 2]));
        $this->assertTrue(carr::accessible(['a' => 1, 'b' => 2]));
        $this->assertTrue(carr::accessible(new CCollection()));

        $this->assertFalse(carr::accessible(null));
        $this->assertFalse(carr::accessible('abc'));
        $this->assertFalse(carr::accessible(new stdClass()));
        $this->assertFalse(carr::accessible((object) ['a' => 1, 'b' => 2]));
        $this->assertFalse(carr::accessible(123));
        $this->assertFalse(carr::accessible(12.34));
        $this->assertFalse(carr::accessible(true));
        $this->assertFalse(carr::accessible(new DateTime()));
        $this->assertFalse(carr::accessible(function () {
            return null;
        }));
    }

    public function testAdd() {
        $array = carr::add(['name' => 'Desk'], 'price', 100);
        $this->assertEquals(['name' => 'Desk', 'price' => 100], $array);

        $this->assertEquals(['surname' => 'Mövsümov'], carr::add([], 'surname', 'Mövsümov'));
        $this->assertEquals(['developer' => ['name' => 'Ferid']], carr::add([], 'developer.name', 'Ferid'));
        $this->assertEquals([1 => 'hAz'], carr::add([], 1, 'hAz'));

        //kunci yang sudah ada tidak ditimpa
        $this->assertEquals(['type' => 'Table'], carr::add(['type' => 'Table'], 'type', 'Chair'));
        $this->assertEquals(['category' => ['type' => 'Table']], carr::add(['category' => ['type' => 'Table']], 'category.type', 'Chair'));
    }

    public function testCollapse() {
        $this->assertEquals(['foo', 'bar', 'baz'], carr::collapse([['foo', 'bar'], ['baz']]));
        $this->assertEquals([1, 2, 3, 'foo', 'bar'], carr::collapse([[1], [2], [3], ['foo', 'bar']]));
        $this->assertSame([], carr::collapse([[], [], []]));
        $this->assertEquals([1, 2, 'foo', 'bar'], carr::collapse([[], [1, 2], [], ['foo', 'bar']]));

        $collection = c::collect(['baz', 'boom']);
        $this->assertEquals([1, 2, 3, 'foo', 'bar', 'baz', 'boom'], carr::collapse([[1], [2], [3], ['foo', 'bar'], $collection]));
    }

    public function testCrossJoin() {
        $this->assertSame([[1, 'a'], [1, 'b'], [1, 'c']], carr::crossJoin([1], ['a', 'b', 'c']));
        $this->assertSame([[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']], carr::crossJoin([1, 2], ['a', 'b']));
        $this->assertSame(
            [[1, 'a'], [1, 'b'], [1, 'c'], [2, 'a'], [2, 'b'], [2, 'c']],
            carr::crossJoin([1, 2], ['a', 'b', 'c'])
        );
        $this->assertSame(
            [
                [1, 'a', 'I'], [1, 'a', 'II'], [1, 'a', 'III'],
                [1, 'b', 'I'], [1, 'b', 'II'], [1, 'b', 'III'],
                [2, 'a', 'I'], [2, 'a', 'II'], [2, 'a', 'III'],
                [2, 'b', 'I'], [2, 'b', 'II'], [2, 'b', 'III'],
            ],
            carr::crossJoin([1, 2], ['a', 'b'], ['I', 'II', 'III'])
        );

        $this->assertEmpty(carr::crossJoin([], ['a', 'b'], ['I', 'II', 'III']));
        $this->assertEmpty(carr::crossJoin([1, 2], [], ['I', 'II', 'III']));
        $this->assertEmpty(carr::crossJoin([1, 2], ['a', 'b'], []));
        $this->assertEmpty(carr::crossJoin([], [], []));
        $this->assertEmpty(carr::crossJoin([]));
        $this->assertSame([[]], carr::crossJoin());
    }

    public function testDivide() {
        list($keys, $values) = carr::divide([]);
        $this->assertSame([], $keys);
        $this->assertSame([], $values);

        list($keys, $values) = carr::divide(['name' => 'Desk']);
        $this->assertEquals(['name'], $keys);
        $this->assertEquals(['Desk'], $values);

        list($keys, $values) = carr::divide(['name' => 'Desk', 'price' => 100, 'available' => true]);
        $this->assertEquals(['name', 'price', 'available'], $keys);
        $this->assertEquals(['Desk', 100, true], $values);

        list($keys, $values) = carr::divide([0 => 'first', 1 => 'second']);
        $this->assertEquals([0, 1], $keys);
        $this->assertEquals(['first', 'second'], $values);
    }

    public function testDot() {
        $this->assertSame(['foo.bar' => 'baz'], carr::dot(['foo' => ['bar' => 'baz']]));
        $this->assertSame([10 => 100], carr::dot([10 => 100]));
        $this->assertSame(['foo.10' => 100], carr::dot(['foo' => [10 => 100]]));
        $this->assertSame([], carr::dot([]));
        $this->assertSame(['foo' => []], carr::dot(['foo' => []]));
        $this->assertSame(['foo.bar' => []], carr::dot(['foo' => ['bar' => []]]));
        $this->assertSame(['name' => 'taylor', 'languages.php' => true], carr::dot(['name' => 'taylor', 'languages' => ['php' => true]]));
        $this->assertSame([
            'user.name' => 'Taylor',
            'user.age' => 25,
            'user.languages.0' => 'PHP',
            'user.languages.1' => 'C#',
        ], carr::dot(['user' => ['name' => 'Taylor', 'age' => 25, 'languages' => ['PHP', 'C#']]]));
        $this->assertSame([
            'foo',
            'foo.bar' => 'baz',
            'foo.baz.a' => 'b',
        ], carr::dot(['foo', 'foo' => ['bar' => 'baz', 'baz' => ['a' => 'b']]]));
        $this->assertSame([
            'foo' => 'bar',
            'empty_array' => [],
            'user.name' => 'Taylor',
            'key' => 'value',
        ], carr::dot(['foo' => 'bar', 'empty_array' => [], 'user' => ['name' => 'Taylor'], 'key' => 'value']));
    }

    public function testDotWithPrefix() {
        $this->assertSame(['prefix.user.name' => 'Taylor'], carr::dot(['user' => ['name' => 'Taylor']], 'prefix.'));
    }

    public function testUndot() {
        $this->assertEquals(
            ['user' => ['name' => 'Taylor', 'age' => 25, 'languages' => ['PHP', 'C#']]],
            carr::undot(['user.name' => 'Taylor', 'user.age' => 25, 'user.languages.0' => 'PHP', 'user.languages.1' => 'C#'])
        );
        $this->assertEquals(
            ['pagination' => ['previous' => '<<', 'next' => '>>']],
            carr::undot(['pagination.previous' => '<<', 'pagination.next' => '>>'])
        );
        $this->assertEquals(
            ['foo', 'foo' => ['bar' => 'baz', 'baz' => ['a' => 'b']]],
            carr::undot(['foo', 'foo.bar' => 'baz', 'foo.baz' => ['a' => 'b']])
        );
    }

    public function testExcept() {
        $array = ['name' => 'taylor', 'age' => 26];
        $this->assertEquals(['age' => 26], carr::except($array, ['name']));
        $this->assertEquals(['age' => 26], carr::except($array, 'name'));

        $array = ['name' => 'taylor', 'framework' => ['language' => 'PHP', 'name' => 'CF']];
        $this->assertEquals(['name' => 'taylor'], carr::except($array, 'framework'));
        $this->assertEquals(['name' => 'taylor', 'framework' => ['name' => 'CF']], carr::except($array, 'framework.language'));
        $this->assertEquals(['framework' => ['language' => 'PHP']], carr::except($array, ['name', 'framework.name']));

        $array = [1 => 'hAz', 2 => [5 => 'foo', 12 => 'baz']];
        $this->assertEquals([1 => 'hAz'], carr::except($array, 2));
    }

    public function testExists() {
        $this->assertTrue(carr::exists([1], 0));
        $this->assertTrue(carr::exists([null], 0));
        $this->assertTrue(carr::exists(['a' => 1], 'a'));
        $this->assertTrue(carr::exists(['a' => null], 'a'));
        $this->assertTrue(carr::exists(new CCollection(['a' => null]), 'a'));

        $this->assertFalse(carr::exists([1], 1));
        $this->assertFalse(carr::exists([null], 1));
        $this->assertFalse(carr::exists(['a' => 1], 0));
        $this->assertFalse(carr::exists(new CCollection(['a' => null]), 'b'));
    }

    public function testWhereNotNull() {
        $this->assertEquals([0, false, '', []], array_values(carr::whereNotNull([null, 0, false, '', null, []])));
        $this->assertEquals([1, 2, 3], array_values(carr::whereNotNull([1, 2, 3])));
        $this->assertSame([], array_values(carr::whereNotNull([null, null, null])));
        $this->assertEquals(['a', 'b', 'c'], array_values(carr::whereNotNull(['a', null, 'b', null, 'c'])));
    }

    public function testFirst() {
        $array = [100, 200, 300];

        $this->assertNull(carr::first([], null));
        $this->assertSame('foo', carr::first([], null, 'foo'));
        $this->assertSame('bar', carr::first([], null, function () {
            return 'bar';
        }));

        $this->assertEquals(100, carr::first($array));
        $this->assertEquals(200, carr::first($array, function ($value) {
            return $value >= 150;
        }));

        $this->assertNull(carr::first($array, function ($value) {
            return $value > 300;
        }));
        $this->assertSame('bar', carr::first($array, function ($value) {
            return $value > 300;
        }, 'bar'));
        $this->assertSame('baz', carr::first($array, function ($value) {
            return $value > 300;
        }, function () {
            return 'baz';
        }));
        $this->assertEquals(100, carr::first($array, function ($value, $key) {
            return $key < 2;
        }));
    }

    public function testFirstWorksWithArrayObject() {
        $result = carr::first(new ArrayObject([0, 10, 20]), function ($value) {
            return $value === 0;
        });

        $this->assertSame(0, $result);
    }

    public function testLast() {
        $array = [100, 200, 300];

        $this->assertNull(carr::last([], null));
        $this->assertSame('foo', carr::last([], null, 'foo'));
        $this->assertSame('bar', carr::last([], null, function () {
            return 'bar';
        }));

        $this->assertEquals(300, carr::last($array));
        $this->assertEquals(200, carr::last($array, function ($value) {
            return $value < 250;
        }));

        $this->assertNull(carr::last($array, function ($value) {
            return $value > 300;
        }));
        $this->assertSame('bar', carr::last($array, function ($value) {
            return $value > 300;
        }, 'bar'));
        $this->assertSame('baz', carr::last($array, function ($value) {
            return $value > 300;
        }, function () {
            return 'baz';
        }));
        $this->assertEquals(200, carr::last($array, function ($value, $key) {
            return $key < 2;
        }));
    }

    public function testFlatten() {
        $this->assertEquals(['#foo', '#bar', '#baz'], carr::flatten(['#foo', '#bar', '#baz']));
        $this->assertEquals(['#foo', '#bar', '#baz'], carr::flatten([['#foo', '#bar'], '#baz']));
        $this->assertEquals(['#foo', null, '#baz', null], carr::flatten([['#foo', null], '#baz', null]));
        $this->assertEquals(['#foo', '#bar', '#baz'], carr::flatten([['#foo', '#bar'], ['#baz']]));
        $this->assertEquals(['#foo', '#bar', '#baz'], carr::flatten([['#foo', ['#bar']], ['#baz']]));
        $this->assertEquals(['#foo', '#bar', '#baz'], carr::flatten([new CCollection(['#foo', '#bar']), ['#baz']]));
        $this->assertEquals(['#foo', '#bar', '#baz'], carr::flatten([new CCollection(['#foo', ['#bar']]), ['#baz']]));
        $this->assertEquals(['#foo', '#bar', '#baz'], carr::flatten([['#foo', new CCollection(['#bar'])], ['#baz']]));
        $this->assertEquals(['#foo', '#bar', '#zap', '#baz'], carr::flatten([['#foo', new CCollection(['#bar', ['#zap']])], ['#baz']]));
    }

    public function testFlattenWithDepth() {
        $array = [['#foo', ['#bar', ['#baz']]], '#zap'];
        $this->assertEquals(['#foo', '#bar', '#baz', '#zap'], carr::flatten($array));
        $this->assertEquals(['#foo', ['#bar', ['#baz']], '#zap'], carr::flatten($array, 1));
        $this->assertEquals(['#foo', '#bar', ['#baz'], '#zap'], carr::flatten($array, 2));
    }

    public function testGet() {
        $array = ['products.desk' => ['price' => 100]];
        $this->assertEquals(['price' => 100], carr::get($array, 'products.desk'));

        $array = ['products' => ['desk' => ['price' => 100]]];
        $this->assertEquals(['price' => 100], carr::get($array, 'products.desk'));

        $array = ['foo' => null, 'bar' => ['baz' => null]];
        $this->assertNull(carr::get($array, 'foo', 'default'));
        $this->assertNull(carr::get($array, 'bar.baz', 'default'));

        $arrayAccessObject = new ArrayObject(['products' => ['desk' => ['price' => 100]]]);
        $this->assertEquals(['price' => 100], carr::get($arrayAccessObject, 'products.desk'));

        $arrayAccessChild = new ArrayObject(['products' => ['desk' => ['price' => 100]]]);
        $this->assertEquals(['price' => 100], carr::get(['child' => $arrayAccessChild], 'child.products.desk'));

        $arrayAccessParent = new ArrayObject(['child' => $arrayAccessChild]);
        $this->assertEquals(['price' => 100], carr::get(['parent' => $arrayAccessParent], 'parent.child.products.desk'));
        $this->assertNull(carr::get(['parent' => $arrayAccessParent], 'parent.child.desk'));

        $arrayAccessObject = new ArrayObject(['products' => ['desk' => null]]);
        $this->assertNull(carr::get(['parent' => $arrayAccessObject], 'parent.products.desk.price'));

        $array = new ArrayObject(['foo' => null, 'bar' => new ArrayObject(['baz' => null])]);
        $this->assertNull(carr::get($array, 'foo', 'default'));
        $this->assertNull(carr::get($array, 'bar.baz', 'default'));

        $array = ['foo', 'bar'];
        $this->assertEquals($array, carr::get($array, null));

        $this->assertSame('default', carr::get(null, 'foo', 'default'));
        $this->assertSame('default', carr::get(false, 'foo', 'default'));
        $this->assertSame('default', carr::get(null, null, 'default'));

        $this->assertEmpty(carr::get([], null));
        $this->assertEmpty(carr::get([], null, 'default'));

        $array = ['products' => [['name' => 'desk'], ['name' => 'chair']]];
        $this->assertSame('desk', carr::get($array, 'products.0.name'));
        $this->assertSame('chair', carr::get($array, 'products.1.name'));

        $array = ['names' => ['developer' => 'taylor']];
        $this->assertSame('dayle', carr::get($array, 'names.otherDeveloper', 'dayle'));
        $this->assertSame('dayle', carr::get($array, 'names.otherDeveloper', function () {
            return 'dayle';
        }));
    }

    public function testHas() {
        $array = ['products.desk' => ['price' => 100]];
        $this->assertTrue(carr::has($array, 'products.desk'));

        $array = ['products' => ['desk' => ['price' => 100]]];
        $this->assertTrue(carr::has($array, 'products.desk'));
        $this->assertTrue(carr::has($array, 'products.desk.price'));
        $this->assertFalse(carr::has($array, 'products.foo'));
        $this->assertFalse(carr::has($array, 'products.desk.foo'));

        $array = ['foo' => null, 'bar' => ['baz' => null]];
        $this->assertTrue(carr::has($array, 'foo'));
        $this->assertTrue(carr::has($array, 'bar.baz'));

        $array = new ArrayObject(['foo' => 10, 'bar' => new ArrayObject(['baz' => 10])]);
        $this->assertTrue(carr::has($array, 'foo'));
        $this->assertTrue(carr::has($array, 'bar'));
        $this->assertTrue(carr::has($array, 'bar.baz'));
        $this->assertFalse(carr::has($array, 'xxx'));
        $this->assertFalse(carr::has($array, 'xxx.yyy'));
        $this->assertFalse(carr::has($array, 'foo.xxx'));
        $this->assertFalse(carr::has($array, 'bar.xxx'));

        $this->assertFalse(carr::has(['foo', 'bar'], null));
        $this->assertFalse(carr::has(null, 'foo'));
        $this->assertFalse(carr::has(false, 'foo'));
        $this->assertFalse(carr::has(null, null));
        $this->assertFalse(carr::has([], null));

        $array = ['products' => ['desk' => ['price' => 100]]];
        $this->assertTrue(carr::has($array, ['products.desk']));
        $this->assertTrue(carr::has($array, ['products.desk', 'products.desk.price']));
        $this->assertTrue(carr::has($array, ['products', 'products']));
        $this->assertFalse(carr::has($array, ['foo']));
        $this->assertFalse(carr::has($array, []));
        $this->assertFalse(carr::has($array, ['products.desk', 'products.price']));

        $array = ['products' => [['name' => 'desk']]];
        $this->assertTrue(carr::has($array, 'products.0.name'));
        $this->assertFalse(carr::has($array, 'products.0.price'));

        $this->assertFalse(carr::has([], [null]));
        $this->assertFalse(carr::has(null, [null]));
    }

    public function testHasAny() {
        $array = ['name' => 'Taylor', 'age' => '', 'city' => null];
        $this->assertTrue(carr::hasAny($array, 'name'));
        $this->assertTrue(carr::hasAny($array, 'age'));
        $this->assertTrue(carr::hasAny($array, 'city'));
        $this->assertFalse(carr::hasAny($array, 'foo'));
        $this->assertTrue(carr::hasAny($array, ['name', 'email']));

        $array = ['name' => 'Taylor', 'email' => 'foo'];
        $this->assertFalse(carr::hasAny($array, ['surname', 'password']));

        $array = ['foo' => ['bar' => null, 'baz' => '']];
        $this->assertTrue(carr::hasAny($array, 'foo.bar'));
        $this->assertTrue(carr::hasAny($array, 'foo.baz'));
        $this->assertFalse(carr::hasAny($array, 'foo.bax'));
        $this->assertTrue(carr::hasAny($array, ['foo.bax', 'foo.baz']));
    }

    public function testSome() {
        $isString = function ($value, $key) {
            return is_string($value);
        };
        $this->assertFalse(carr::some([1, 2], $isString));
        $this->assertTrue(carr::some(['foo', 2], $isString));
        $this->assertTrue(carr::some(['foo', 'bar'], $isString));
    }

    public function testIsAssoc() {
        $this->assertTrue(carr::isAssoc(['a' => 'a', 0 => 'b']));
        $this->assertTrue(carr::isAssoc([1 => 'a', 0 => 'b']));
        $this->assertTrue(carr::isAssoc([1 => 'a', 2 => 'b']));
        $this->assertFalse(carr::isAssoc([0 => 'a', 1 => 'b']));
        $this->assertFalse(carr::isAssoc(['a', 'b']));

        $this->assertFalse(carr::isAssoc([]));
        $this->assertFalse(carr::isAssoc([1, 2, 3]));
        $this->assertFalse(carr::isAssoc(['foo', 2, 3]));
        $this->assertFalse(carr::isAssoc([0 => 'foo', 'bar']));

        $this->assertTrue(carr::isAssoc([1 => 'foo', 'bar']));
        $this->assertTrue(carr::isAssoc([0 => 'foo', 'bar' => 'baz']));
        $this->assertTrue(carr::isAssoc([0 => 'foo', 2 => 'bar']));
        $this->assertTrue(carr::isAssoc(['foo' => 'bar', 'baz' => 'qux']));
    }

    public function testIsList() {
        $this->assertTrue(carr::isList([]));
        $this->assertTrue(carr::isList([1, 2, 3]));
        $this->assertTrue(carr::isList(['foo', 2, 3]));
        $this->assertTrue(carr::isList(['foo', 'bar']));
        $this->assertTrue(carr::isList([0 => 'foo', 'bar']));
        $this->assertTrue(carr::isList([0 => 'foo', 1 => 'bar']));

        $this->assertFalse(carr::isList([-1 => 1]));
        $this->assertFalse(carr::isList([-1 => 1, 0 => 2]));
        $this->assertFalse(carr::isList([1 => 'foo', 'bar']));
        $this->assertFalse(carr::isList([1 => 'foo', 0 => 'bar']));
        $this->assertFalse(carr::isList([0 => 'foo', 'bar' => 'baz']));
        $this->assertFalse(carr::isList([0 => 'foo', 2 => 'bar']));
        $this->assertFalse(carr::isList(['foo' => 'bar', 'baz' => 'qux']));
    }

    public function testOnly() {
        $array = ['name' => 'Desk', 'price' => 100, 'orders' => 10];
        $array = carr::only($array, ['name', 'price']);
        $this->assertEquals(['name' => 'Desk', 'price' => 100], $array);
        $this->assertEmpty(carr::only($array, ['nonExistingKey']));
        $this->assertEmpty(carr::only($array, null));

        $this->assertEquals(['foo'], carr::only(['foo', 'bar', 'baz'], 0));
        $this->assertEquals([1 => 'bar', 2 => 'baz'], carr::only(['foo', 'bar', 'baz'], [1, 2]));
        $this->assertEmpty(carr::only(['foo', 'bar', 'baz'], [3]));

        $this->assertEquals(['foo'], carr::only(['foo', 'bar' => 'baz'], 0));
        $this->assertEquals(['bar' => 'baz'], carr::only(['foo', 'bar' => 'baz'], 'bar'));
    }

    public function testPluck() {
        $data = [
            'post-1' => ['comments' => ['tags' => ['#foo', '#bar']]],
            'post-2' => ['comments' => ['tags' => ['#baz']]],
        ];

        $this->assertEquals([
            0 => ['tags' => ['#foo', '#bar']],
            1 => ['tags' => ['#baz']],
        ], carr::pluck($data, 'comments'));
        $this->assertEquals([['#foo', '#bar'], ['#baz']], carr::pluck($data, 'comments.tags'));
        $this->assertEquals([null, null], carr::pluck($data, 'foo'));
        $this->assertEquals([null, null], carr::pluck($data, 'foo.bar'));

        $array = [
            ['developer' => ['name' => 'Taylor']],
            ['developer' => ['name' => 'Abigail']],
        ];
        $this->assertEquals(['Taylor', 'Abigail'], carr::pluck($array, 'developer.name'));
        $this->assertEquals(['Taylor', 'Abigail'], carr::pluck($array, ['developer', 'name']));
    }

    public function testPluckWithKeys() {
        $array = [
            ['name' => 'Taylor', 'role' => 'developer'],
            ['name' => 'Abigail', 'role' => 'developer'],
        ];

        $this->assertEquals(['Taylor' => 'developer', 'Abigail' => 'developer'], carr::pluck($array, 'role', 'name'));
        $this->assertEquals([
            'Taylor' => ['name' => 'Taylor', 'role' => 'developer'],
            'Abigail' => ['name' => 'Abigail', 'role' => 'developer'],
        ], carr::pluck($array, null, 'name'));
    }

    public function testPluckWithNestedKeys() {
        $array = [['user' => ['taylor', 'otwell']], ['user' => ['dayle', 'rees']]];
        $this->assertEquals(['taylor', 'dayle'], carr::pluck($array, 'user.0'));
        $this->assertEquals(['taylor', 'dayle'], carr::pluck($array, ['user', 0]));
        $this->assertEquals(['taylor' => 'otwell', 'dayle' => 'rees'], carr::pluck($array, 'user.1', 'user.0'));
        $this->assertEquals(['taylor' => 'otwell', 'dayle' => 'rees'], carr::pluck($array, ['user', 1], ['user', 0]));
    }

    public function testPluckWithNestedArrays() {
        $array = [
            ['account' => 'a', 'users' => [['first' => 'taylor', 'last' => 'otwell', 'email' => 'taylor@example.com']]],
            ['account' => 'b', 'users' => [['first' => 'abigail', 'last' => 'otwell'], ['first' => 'dayle', 'last' => 'rees']]],
        ];

        $this->assertEquals([['taylor'], ['abigail', 'dayle']], carr::pluck($array, 'users.*.first'));
        $this->assertEquals(['a' => ['taylor'], 'b' => ['abigail', 'dayle']], carr::pluck($array, 'users.*.first', 'account'));
        $this->assertEquals([['taylor@example.com'], [null, null]], carr::pluck($array, 'users.*.email'));
    }

    public function testMap() {
        $data = ['first' => 'taylor', 'last' => 'otwell'];
        $mapped = carr::map($data, function ($value, $key) {
            return $key . '-' . strrev($value);
        });
        $this->assertEquals(['first' => 'first-rolyat', 'last' => 'last-llewto'], $mapped);
        $this->assertEquals(['first' => 'taylor', 'last' => 'otwell'], $data);

        $this->assertSame([], carr::map([], function ($value) {
            return $value;
        }));

        $mapped = carr::map(['first' => 'taylor', 'last' => null], function ($value, $key) {
            return $key . '-' . $value;
        });
        $this->assertEquals(['first' => 'first-taylor', 'last' => 'last-'], $mapped);

        //beda dari hulu: iteratee bergaya lodash - string adalah shorthand properti, bukan nama fungsi
        $users = [['user' => 'barney'], ['user' => 'fred']];
        $this->assertEquals(['barney', 'fred'], carr::map($users, 'user'));
    }

    public function testPrepend() {
        $this->assertEquals(['zero', 'one', 'two', 'three', 'four'], carr::prepend(['one', 'two', 'three', 'four'], 'zero'));
        $this->assertEquals(['zero' => 0, 'one' => 1, 'two' => 2], carr::prepend(['one' => 1, 'two' => 2], 0, 'zero'));
        $this->assertEquals(['' => 0, 'one' => 1, 'two' => 2], carr::prepend(['one' => 1, 'two' => 2], 0, ''));
        $this->assertEquals(['zero'], carr::prepend([], 'zero'));
        $this->assertEquals(['zero', ''], carr::prepend([''], 'zero'));
        $this->assertEquals([['zero'], 'one', 'two'], carr::prepend(['one', 'two'], ['zero']));
        $this->assertEquals(['key' => ['zero'], 'one', 'two'], carr::prepend(['one', 'two'], ['zero'], 'key'));
    }

    public function testPull() {
        $array = ['name' => 'Desk', 'price' => 100];
        $this->assertSame('Desk', carr::pull($array, 'name'));
        $this->assertSame(['price' => 100], $array);

        $array = ['joe@example.com' => 'Joe', 'jane@localhost' => 'Jane'];
        $this->assertSame('Joe', carr::pull($array, 'joe@example.com'));
        $this->assertSame(['jane@localhost' => 'Jane'], $array);

        //kunci bertitik di dalam nama tidak bisa dicapai lewat notasi titik
        $array = ['emails' => ['joe@example.com' => 'Joe', 'jane@localhost' => 'Jane']];
        $this->assertNull(carr::pull($array, 'emails.joe@example.com'));
        $this->assertSame(['emails' => ['joe@example.com' => 'Joe', 'jane@localhost' => 'Jane']], $array);

        $array = ['First', 'Second'];
        $this->assertSame('First', carr::pull($array, 0));
        $this->assertSame([1 => 'Second'], $array);
    }

    public function testQuery() {
        $this->assertSame('', carr::query([]));
        $this->assertSame('foo=bar', carr::query(['foo' => 'bar']));
        $this->assertSame('foo=bar&bar=baz', carr::query(['foo' => 'bar', 'bar' => 'baz']));
        $this->assertSame('foo=bar&bar=1', carr::query(['foo' => 'bar', 'bar' => true]));
        $this->assertSame('foo=bar', carr::query(['foo' => 'bar', 'bar' => null]));
        $this->assertSame('foo=bar&bar=', carr::query(['foo' => 'bar', 'bar' => '']));
    }

    public function testRandom() {
        $random = carr::random(['foo', 'bar', 'baz']);
        $this->assertContains($random, ['foo', 'bar', 'baz']);

        $random = carr::random(['foo', 'bar', 'baz'], 0);
        $this->assertIsArray($random);
        $this->assertCount(0, $random);

        $random = carr::random(['foo', 'bar', 'baz'], 1);
        $this->assertIsArray($random);
        $this->assertCount(1, $random);
        $this->assertContains($random[0], ['foo', 'bar', 'baz']);

        $random = carr::random(['foo', 'bar', 'baz'], 2);
        $this->assertIsArray($random);
        $this->assertCount(2, $random);
        $this->assertContains($random[0], ['foo', 'bar', 'baz']);
        $this->assertContains($random[1], ['foo', 'bar', 'baz']);

        $random = carr::random(['foo', 'bar', 'baz'], '2');
        $this->assertIsArray($random);
        $this->assertCount(2, $random);
    }

    public function testRandomOnEmptyArray() {
        $this->assertIsArray(carr::random([], 0));
        $this->assertCount(0, carr::random([], 0));
    }

    public function testRandomThrowsWhenRequestingMoreItemsThanAvailable() {
        $this->expectException(InvalidArgumentException::class);
        carr::random(['foo'], 2);
    }

    public function testSet() {
        $array = ['products' => ['desk' => ['price' => 100]]];
        carr::set($array, 'products.desk.price', 200);
        $this->assertEquals(['products' => ['desk' => ['price' => 200]]], $array);

        //kunci null mengganti seluruh array
        $array = ['products' => ['desk' => ['price' => 100]]];
        carr::set($array, null, ['price' => 300]);
        $this->assertSame(['price' => 300], $array);

        $array = [];
        carr::set($array, 'products.desk.price', 200);
        $this->assertSame(['products' => ['desk' => ['price' => 200]]], $array);

        $array = ['products' => 'desk'];
        carr::set($array, 'products.desk.price', 200);
        $this->assertSame(['products' => ['desk' => ['price' => 200]]], $array);
    }

    public function testShuffleKeepsSameValues() {
        $input = ['a', 'b', 'c', 'd', 'e'];
        $shuffled = carr::shuffle($input);
        sort($shuffled);
        $this->assertEquals($input, $shuffled);

        $this->assertSame([], carr::shuffle([]));
    }

    public function testSort() {
        $unsorted = [
            ['name' => 'Desk'],
            ['name' => 'Chair'],
        ];
        $expected = [
            ['name' => 'Chair'],
            ['name' => 'Desk'],
        ];

        $this->assertEquals($expected, array_values(carr::sort($unsorted, function ($value) {
            return $value['name'];
        })));
        $this->assertEquals($expected, array_values(carr::sort($unsorted, 'name')));

        $unsorted = ['Desk', 'Chair'];
        $this->assertEquals(['Chair', 'Desk'], array_values(carr::sort($unsorted)));
    }

    public function testSortDesc() {
        $unsorted = [
            ['name' => 'Chair'],
            ['name' => 'Desk'],
        ];
        $expected = [
            ['name' => 'Desk'],
            ['name' => 'Chair'],
        ];

        $this->assertEquals($expected, array_values(carr::sortDesc($unsorted, function ($value) {
            return $value['name'];
        })));
        $this->assertEquals($expected, array_values(carr::sortDesc($unsorted, 'name')));

        $this->assertEquals(['Desk', 'Chair'], array_values(carr::sortDesc(['Chair', 'Desk'])));
    }

    public function testSortRecursive() {
        $array = [
            'users' => [
                ['name' => 'taylor', 'age' => 27],
                ['name' => 'dayle', 'age' => 28],
            ],
            'php' => ['b', 'c', 'a'],
            'numbers' => [3, 1, 2],
        ];
        $expected = [
            'numbers' => [1, 2, 3],
            'php' => ['a', 'b', 'c'],
            'users' => [
                ['age' => 27, 'name' => 'taylor'],
                ['age' => 28, 'name' => 'dayle'],
            ],
        ];

        $this->assertSame($expected, carr::sortRecursive($array));
    }

    public function testToCssClasses() {
        $this->assertSame('font-bold mt-4', carr::toCssClasses(['font-bold', 'mt-4', 'ml-2' => false]));
        $this->assertSame('font-bold mt-4 ml-2', carr::toCssClasses(['font-bold', 'mt-4', 'ml-2' => true]));
        $this->assertSame('', carr::toCssClasses([]));
    }

    public function testToCssStyles() {
        $this->assertSame('font-weight: bold; margin-top: 4px;', carr::toCssStyles(['font-weight: bold', 'margin-top: 4px;', 'margin-left: 2px' => false]));
        $this->assertSame('font-weight: bold; margin-top: 4px; margin-left: 2px;', carr::toCssStyles(['font-weight: bold', 'margin-top: 4px;', 'margin-left: 2px' => true]));
    }

    public function testWhere() {
        $array = [100, '200', 300, '400', 500];
        $filtered = carr::where($array, function ($value, $key) {
            return is_string($value);
        });
        $this->assertEquals([1 => '200', 3 => '400'], $filtered);

        $array = ['10' => 1, 'foo' => 3, 20 => 2];
        $filtered = carr::where($array, function ($value, $key) {
            return is_numeric($key);
        });
        $this->assertEquals(['10' => 1, 20 => 2], $filtered);
    }

    public function testForget() {
        $array = ['products' => ['desk' => ['price' => 100]]];
        carr::forget($array, null);
        $this->assertEquals(['products' => ['desk' => ['price' => 100]]], $array);

        $array = ['products' => ['desk' => ['price' => 100]]];
        carr::forget($array, []);
        $this->assertEquals(['products' => ['desk' => ['price' => 100]]], $array);

        $array = ['products' => ['desk' => ['price' => 100]]];
        carr::forget($array, 'products.desk');
        $this->assertEquals(['products' => []], $array);

        $array = ['products' => ['desk' => ['price' => 100]]];
        carr::forget($array, 'products.desk.price');
        $this->assertEquals(['products' => ['desk' => []]], $array);

        $array = ['products' => ['desk' => ['price' => 100]]];
        carr::forget($array, 'products.final.price');
        $this->assertEquals(['products' => ['desk' => ['price' => 100]]], $array);

        $array = ['shop' => ['cart' => [150 => 0]]];
        carr::forget($array, 'shop.final.cart');
        $this->assertEquals(['shop' => ['cart' => [150 => 0]]], $array);

        $array = ['products' => ['desk' => ['price' => ['original' => 50, 'taxes' => 60]]]];
        carr::forget($array, 'products.desk.price.taxes');
        $this->assertEquals(['products' => ['desk' => ['price' => ['original' => 50]]]], $array);

        $array = ['products' => ['desk' => ['price' => ['original' => 50, 'taxes' => 60]]]];
        carr::forget($array, 'products.desk.final.taxes');
        $this->assertEquals(['products' => ['desk' => ['price' => ['original' => 50, 'taxes' => 60]]]], $array);

        $array = ['products' => ['desk' => ['price' => 50], null => 'something']];
        carr::forget($array, ['products.amount.all', 'products.desk.price']);
        $this->assertEquals(['products' => ['desk' => [], null => 'something']], $array);

        //kunci bertitik yang harfiah tetap bisa dihapus
        $array = ['joe@example.com' => 'Joe', 'jane@example.com' => 'Jane'];
        carr::forget($array, 'joe@example.com');
        $this->assertEquals(['jane@example.com' => 'Jane'], $array);

        $array = ['emails' => ['joe@example.com' => ['name' => 'Joe'], 'jane@localhost' => ['name' => 'Jane']]];
        carr::forget($array, ['emails.joe@example.com', 'emails.jane@localhost']);
        $this->assertEquals(['emails' => ['joe@example.com' => ['name' => 'Joe']]], $array);
    }

    public function testWrap() {
        $string = 'a';
        $array = ['a'];
        $object = new stdClass();
        $object->value = 'a';
        $this->assertEquals(['a'], carr::wrap($string));
        $this->assertEquals($array, carr::wrap($array));
        $this->assertEquals([$object], carr::wrap($object));
        $this->assertEquals([], carr::wrap(null));
        $this->assertEquals([null], carr::wrap([null]));
        $this->assertEquals([null, null], carr::wrap([null, null]));
        $this->assertEquals([''], carr::wrap(''));
        $this->assertEquals([''], carr::wrap(['']));
        $this->assertEquals([false], carr::wrap(false));
        $this->assertEquals([false], carr::wrap([false]));
        $this->assertEquals([0], carr::wrap(0));

        $obj = new stdClass();
        $obj->value = 'a';
        $obj = unserialize(serialize($obj));
        $this->assertEquals([$obj], carr::wrap($obj));
        $this->assertSame($obj, carr::wrap($obj)[0]);
    }
}
