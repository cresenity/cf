<?php
use PHPUnit\Framework\TestCase;

/**
 * Port SupportCollectionTest hulu (bagian pengambilan, penyaringan, transformasi, agregasi)
 * ke CCollection. Divergensi CF dicatat di pesan assert.
 */
class CollectionPortTest extends TestCase {
    public function testFirstReturnsFirstItemInCollection() {
        $c = new CCollection(['foo', 'bar']);
        $this->assertSame('foo', $c->first());
    }

    public function testFirstWithCallback() {
        $c = new CCollection(['foo', 'bar', 'baz']);
        $this->assertSame('bar', $c->first(function ($value) {
            return $value === 'bar';
        }));
    }

    public function testFirstWithCallbackAndDefault() {
        $c = new CCollection(['foo', 'bar']);
        $this->assertSame('default', $c->first(function ($value) {
            return $value === 'baz';
        }, 'default'));
        $this->assertSame('lazy', $c->first(function () {
            return false;
        }, function () {
            return 'lazy';
        }), 'default berupa closure dievaluasi');
    }

    public function testFirstWithDefaultAndWithoutCallback() {
        $c = new CCollection();
        $this->assertSame('default', $c->first(null, 'default'));
        $this->assertNull($c->first());
    }

    public function testFirstWhere() {
        $data = new CCollection([
            ['material' => 'paper', 'type' => 'book'],
            ['material' => 'rubber', 'type' => 'gasket'],
        ]);
        $this->assertSame('book', $data->firstWhere('material', 'paper')['type']);
        $this->assertSame('gasket', $data->firstWhere('material', 'rubber')['type']);
        $this->assertNull($data->firstWhere('material', 'nonexistent'));
        $this->assertNull($data->firstWhere('nonexistent', 'key'));
        $this->assertSame('gasket', $data->firstWhere('material', '!=', 'paper')['type']);
    }

    public function testLastReturnsLastItemInCollection() {
        $c = new CCollection(['foo', 'bar']);
        $this->assertSame('bar', $c->last());
        $this->assertNull((new CCollection())->last());
    }

    public function testLastWithCallback() {
        $data = new CCollection([100, 200, 300]);
        $this->assertSame(200, $data->last(function ($value) {
            return $value < 250;
        }));
        $this->assertSame(200, $data->last(function ($value, $key) {
            return $key < 2;
        }));
        $this->assertSame('default', $data->last(function ($value) {
            return $value > 1000;
        }, 'default'));
    }

    public function testLastWithDefaultAndWithoutCallback() {
        $this->assertSame('default', (new CCollection())->last(null, 'default'));
    }

    public function testSoleReturnsFirstItemInCollectionIfOnlyOneExists() {
        $collection = new CCollection([
            ['name' => 'foo'],
            ['name' => 'bar'],
        ]);
        $this->assertSame(['name' => 'foo'], $collection->where('name', 'foo')->sole());
        $this->assertSame(['name' => 'foo'], $collection->sole('name', '=', 'foo'));
        $this->assertSame(['name' => 'foo'], $collection->sole('name', 'foo'));
        $this->assertSame(['name' => 'bar'], $collection->sole(function ($item) {
            return $item['name'] === 'bar';
        }));
    }

    public function testSoleThrowsExceptionIfNoItemsExist() {
        $this->expectException(CCollection_Exception_ItemNotFoundException::class);
        (new CCollection([['name' => 'foo']]))->where('name', 'INVALID')->sole();
    }

    public function testSoleThrowsExceptionIfMoreThanOneItemExists() {
        $this->expectException(CCollection_Exception_MultipleItemsFoundException::class);
        (new CCollection([['name' => 'foo'], ['name' => 'foo']]))->where('name', 'foo')->sole();
    }

    public function testFirstOrFailReturnsFirstItemInCollection() {
        $collection = new CCollection([['name' => 'foo'], ['name' => 'bar'], ['name' => 'foo']]);
        $this->assertSame(['name' => 'foo'], $collection->firstOrFail('name', 'foo'));
        $this->assertSame(['name' => 'bar'], $collection->firstOrFail(function ($item) {
            return $item['name'] === 'bar';
        }));
        $this->assertSame(['name' => 'foo'], $collection->firstOrFail(), 'tanpa filter = item pertama');
    }

    public function testFirstOrFailThrowsExceptionIfNoItemsExist() {
        $this->expectException(CCollection_Exception_ItemNotFoundException::class);
        (new CCollection([['name' => 'foo']]))->firstOrFail('name', 'INVALID');
    }

    public function testFirstOrFailDoesNotThrowOnMultipleMatches() {
        $collection = new CCollection([['name' => 'foo', 'id' => 1], ['name' => 'foo', 'id' => 2]]);
        $this->assertSame(1, $collection->firstOrFail('name', 'foo')['id']);
    }

    public function testNth() {
        $data = new CCollection(['a', 'b', 'c', 'd', 'e', 'f']);
        $this->assertSame(['a', 'e'], $data->nth(4)->all());
        $this->assertSame(['b', 'f'], $data->nth(4, 1)->all());
        $this->assertSame(['c'], $data->nth(4, 2)->all());
        $this->assertSame(['d'], $data->nth(4, 3)->all());
        $this->assertSame(['c', 'e'], $data->nth(2, 2)->all());
        $this->assertSame(['c', 'd', 'e', 'f'], $data->nth(1, 2)->all());
    }

    public function testGetWithDefault() {
        $data = new CCollection(['name' => 'taylor', 'framework' => 'cf']);
        $this->assertSame('taylor', $data->get('name'));
        $this->assertSame('dflt', $data->get('missing', 'dflt'));
        $this->assertSame('lazy', $data->get('missing', function () {
            return 'lazy';
        }));
        $this->assertSame('cf', $data->get('framework', 'ignored'));
    }

    public function testFilter() {
        $c = new CCollection([['id' => 1, 'name' => 'Hello'], ['id' => 2, 'name' => 'World']]);
        $this->assertEquals([1 => ['id' => 2, 'name' => 'World']], $c->filter(function ($item) {
            return $item['id'] == 2;
        })->all());

        $c = new CCollection(['', 'Hello', '', 'World']);
        $this->assertEquals(['Hello', 'World'], $c->filter()->values()->toArray());

        $c = new CCollection(['id' => 1, 'first' => 'Hello', 'second' => 'World']);
        $this->assertEquals(['first' => 'Hello', 'second' => 'World'], $c->filter(function ($item, $key) {
            return $key != 'id';
        })->all());
    }

    public function testWhere() {
        $c = new CCollection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);

        $this->assertEquals([['v' => 3], ['v' => '3']], $c->where('v', 3)->values()->all());
        $this->assertEquals([['v' => 3], ['v' => '3']], $c->where('v', '=', 3)->values()->all());
        $this->assertEquals([['v' => 3], ['v' => '3']], $c->where('v', '==', 3)->values()->all());
        $this->assertEquals([['v' => 3], ['v' => '3']], $c->where('v', 'garbage', 3)->values()->all(), 'operator tak dikenal → =');
        $this->assertEquals([['v' => 3]], $c->where('v', '===', 3)->values()->all());

        $this->assertEquals([['v' => 1], ['v' => 2], ['v' => 4]], $c->where('v', '<>', 3)->values()->all());
        $this->assertEquals([['v' => 1], ['v' => 2], ['v' => 4]], $c->where('v', '!=', 3)->values()->all());
        $this->assertEquals([['v' => 1], ['v' => 2], ['v' => '3'], ['v' => 4]], $c->where('v', '!==', 3)->values()->all());
        $this->assertEquals([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3']], $c->where('v', '<=', 3)->values()->all());
        $this->assertEquals([['v' => 3], ['v' => '3'], ['v' => 4]], $c->where('v', '>=', 3)->values()->all());
        $this->assertEquals([['v' => 1], ['v' => 2]], $c->where('v', '<', 3)->values()->all());
        $this->assertEquals([['v' => 4]], $c->where('v', '>', 3)->values()->all());

        $object = (object) ['foo' => 'bar'];
        $this->assertEquals([], $c->where('v', $object)->values()->all());
        $this->assertEquals([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]], $c->where('v', '<>', $object)->values()->all());
        $this->assertEquals([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]], $c->where('v', '!=', null)->values()->all());
        $this->assertEquals([], $c->where('v', '<', null)->values()->all());
    }

    public function testWhereWithOnlyKeyFiltersTruthy() {
        $c = new CCollection([['v' => 1], ['v' => null], ['v' => 0], ['v' => 'x']]);
        $this->assertEquals([['v' => 1], ['v' => 'x']], $c->where('v')->values()->all());
    }

    public function testWhereStrict() {
        $c = new CCollection([['v' => 3], ['v' => '3']]);
        $this->assertEquals([['v' => '3']], $c->whereStrict('v', '3')->values()->all());
    }

    public function testWhereInAndNotIn() {
        $c = new CCollection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);
        $this->assertEquals([['v' => 1], ['v' => 3], ['v' => '3']], $c->whereIn('v', [1, 3])->values()->all());
        $this->assertEquals([['v' => 1], ['v' => 3]], $c->whereInStrict('v', [1, 3])->values()->all());
        $this->assertEquals([['v' => 2], ['v' => 4]], $c->whereNotIn('v', [1, 3])->values()->all());
        $this->assertEquals([['v' => 2], ['v' => '3'], ['v' => 4]], $c->whereNotInStrict('v', [1, 3])->values()->all());
        $this->assertEquals([['v' => 1], ['v' => 3], ['v' => '3']], $c->whereIn('v', c::collect([1, 3]))->values()->all(), 'daftar nilai boleh Arrayable');
    }

    public function testWhereBetweenAndNotBetween() {
        $c = new CCollection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);
        $this->assertEquals([['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]], $c->whereBetween('v', [2, 4])->values()->all());
        $this->assertEquals([['v' => 1]], $c->whereBetween('v', [-1, 1])->values()->all());
        $this->assertEquals([['v' => 1]], $c->whereNotBetween('v', [2, 4])->values()->all());
        $this->assertEquals([['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]], $c->whereNotBetween('v', [-1, 1])->values()->all());
    }

    public function testWhereNullAndNotNull() {
        $data = new CCollection([['name' => 'Taylor'], ['name' => null], ['name' => 'Bert'], ['name' => false], ['name' => '']]);
        $this->assertSame([1 => ['name' => null]], $data->whereNull('name')->all());
        $this->assertSame([0 => ['name' => 'Taylor'], 2 => ['name' => 'Bert'], 3 => ['name' => false], 4 => ['name' => '']], $data->whereNotNull('name')->all());

        $scalars = new CCollection([1, null, 3, 'null', false, '']);
        $this->assertSame([1 => null], $scalars->whereNull()->all(), 'tanpa kunci = nilai item itu sendiri');
        $this->assertSame([0 => 1, 2 => 3, 3 => 'null', 4 => false, 5 => ''], $scalars->whereNotNull()->all());
    }

    public function testWhereInstanceOf() {
        $c = new CCollection([new stdClass(), new stdClass(), new CCollection(), new stdClass(), new ArrayObject()]);
        $this->assertCount(3, $c->whereInstanceOf(stdClass::class));
        $this->assertCount(4, $c->whereInstanceOf([stdClass::class, CCollection::class]), 'daftar kelas = salah satu');
    }

    public function testRejectWithKeys() {
        $c = new CCollection(['id' => 1, 'first' => 'Hello', 'second' => 'World']);
        $this->assertEquals(['first' => 'Hello', 'second' => 'World'], $c->reject(function ($item, $key) {
            return $key == 'id';
        })->all());
        $this->assertEquals([1 => 'b'], (new CCollection(['a', 'b']))->reject('a')->all(), 'nilai skalar dibandingkan langsung');
    }

    public function testUnique() {
        $c = new CCollection(['Hello', 'World', 'World']);
        $this->assertEquals(['Hello', 'World'], $c->unique()->all());

        $c = new CCollection([[1, 2], [1, 2], [2, 3], [3, 4], [2, 3]]);
        $this->assertEquals([[1, 2], [2, 3], [3, 4]], $c->unique()->values()->all());
    }

    public function testUniqueWithCallback() {
        $c = new CCollection([
            1 => ['id' => 1, 'first' => 'Taylor', 'last' => 'Otwell'],
            2 => ['id' => 2, 'first' => 'Taylor', 'last' => 'Otwell'],
            3 => ['id' => 3, 'first' => 'Abigail', 'last' => 'Otwell'],
            4 => ['id' => 4, 'first' => 'Abigail', 'last' => 'Otwell'],
            5 => ['id' => 5, 'first' => 'Taylor', 'last' => 'Swift'],
            6 => ['id' => 6, 'first' => 'Taylor', 'last' => 'Swift'],
        ]);

        $this->assertEquals([1, 3], $c->unique('first')->pluck('id')->all(), 'kunci asli dipertahankan, item pertama yang menang');
        $this->assertEquals([1, 3, 5], $c->unique(function ($item) {
            return $item['first'] . $item['last'];
        })->pluck('id')->all());
        $this->assertEquals([1, 2], $c->unique(function ($item, $key) {
            return $key % 2;
        })->pluck('id')->all());
    }

    public function testUniqueStrict() {
        $c = new CCollection([['id' => '0', 'name' => 'zero'], ['id' => '00', 'name' => 'double zero'], ['id' => '0', 'name' => 'again zero']]);
        $this->assertEquals([['id' => '0', 'name' => 'zero'], ['id' => '00', 'name' => 'double zero']], $c->uniqueStrict('id')->values()->all());
        $this->assertCount(1, $c->unique('id'), 'longgar: "0" == "00"');
    }

    public function testDuplicates() {
        $c = new CCollection([1, 2, 1, 'laravel', null, 'laravel', 'php', null]);
        $this->assertSame([2 => 1, 5 => 'laravel', 7 => null], $c->duplicates()->all());

        $c = new CCollection([2, '2', [], null]);
        $this->assertSame([1 => '2', 3 => null], $c->duplicates()->all(), 'longgar: [] == null');
        $this->assertSame([], $c->duplicatesStrict()->all());

        $c = new CCollection([['framework' => 'vue'], ['framework' => 'laravel'], ['framework' => 'laravel']]);
        $this->assertSame([2 => 'laravel'], $c->duplicates('framework')->all());
        $this->assertSame([2 => 'laravel'], $c->duplicates(function ($item) {
            return $item['framework'];
        })->all());
    }

    public function testOnlyAndExceptWithMixedArgs() {
        $data = new CCollection(['first' => 'Taylor', 'last' => 'Otwell', 'email' => 'taylorotwell@gmail.com']);
        $this->assertEquals($data->all(), $data->only(null)->all());
        $this->assertEquals(['first' => 'Taylor'], $data->only(['first', 'missing'])->all());
        $this->assertEquals(['first' => 'Taylor'], $data->only('first', 'missing')->all());
        $this->assertEquals(['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'], $data->only(c::collect(['first', 'email']))->all());
        $this->assertEquals(['first' => 'Taylor'], $data->except(['last', 'email', 'missing'])->all());
        $this->assertEquals(['first' => 'Taylor'], $data->except('last', 'email', 'missing')->all());
        $this->assertEquals(['first' => 'Taylor'], $data->except(c::collect(['last', 'email']))->all());
    }

    public function testSkip() {
        $data = new CCollection([1, 2, 3, 4, 5, 6]);
        $this->assertSame([5, 6], $data->skip(4)->values()->all());
        $this->assertSame([], $data->skip(10)->values()->all());
    }

    public function testSkipUntil() {
        $data = new CCollection([1, 1, 2, 2, 3, 3, 4, 4]);
        $this->assertSame([3, 3, 4, 4], $data->skipUntil(3)->values()->all());
        $this->assertSame([], $data->skipUntil(5)->values()->all(), 'nilai tak pernah ditemui → kosong');
        $this->assertSame([3, 3, 4, 4], $data->skipUntil(function ($value) {
            return $value >= 3;
        })->values()->all());
    }

    public function testSkipWhile() {
        $data = new CCollection([1, 1, 2, 2, 3, 3, 4, 4]);
        $this->assertSame([2, 2, 3, 3, 4, 4], $data->skipWhile(1)->values()->all());
        $this->assertSame([], $data->skipWhile(function ($value) {
            return $value < 10;
        })->values()->all());
        $this->assertSame([3, 3, 4, 4], $data->skipWhile(function ($value) {
            return $value < 3;
        })->values()->all());
    }

    public function testTakeNegativeAndSliceNegative() {
        $data = new CCollection([1, 2, 3, 4, 5, 6, 7, 8]);
        $this->assertSame([7, 8], $data->take(-2)->values()->all());
        $this->assertSame([4, 5, 6, 7, 8], $data->slice(3)->values()->all());
        $this->assertSame([4, 5, 6], $data->slice(3, 3)->values()->all());
        $this->assertSame([6, 7, 8], $data->slice(-3)->values()->all());
        $this->assertSame([6, 7], $data->slice(-3, 2)->values()->all());
        $this->assertSame([3 => 4], $data->slice(3, 1)->all(), 'slice mempertahankan kunci');
    }

    public function testNthOnLazyCollectionMatchesCollection() {
        $data = new CCollection(['a', 'b', 'c', 'd', 'e', 'f']);
        $this->assertSame(['c', 'e'], $data->lazy()->nth(2, 2)->values()->all());
        $this->assertSame(['b', 'f'], $data->lazy()->nth(4, 1)->values()->all());
        $this->assertSame([[1, 2], [3]], (new CCollection(['a' => 1, 'b' => 2, 'c' => 3]))->lazy()->chunk(2, false)->map(function ($chunk) {
            return $chunk->all();
        })->values()->all());
    }

    public function testMap() {
        $data = new CCollection(['first' => 'taylor', 'last' => 'otwell']);
        $data = $data->map(function ($item, $key) {
            return $key . '-' . strrev($item);
        });
        $this->assertEquals(['first' => 'first-rolyat', 'last' => 'last-llewto'], $data->all());
    }

    public function testMapSpread() {
        $c = new CCollection([[1, 'a'], [2, 'b']]);
        $result = $c->mapSpread(function ($number, $character) {
            return "{$number}-{$character}";
        });
        $this->assertEquals(['1-a', '2-b'], $result->all());

        $result = $c->mapSpread(function ($number, $character, $key) {
            return "{$number}-{$character}-{$key}";
        });
        $this->assertEquals(['1-a-0', '2-b-1'], $result->all(), 'kunci disisipkan sebagai argumen terakhir');
    }

    public function testMapToDictionary() {
        $data = new CCollection([
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
            ['id' => 3, 'name' => 'C'],
            ['id' => 4, 'name' => 'B'],
        ]);
        $groups = $data->mapToDictionary(function ($item) {
            return [$item['name'] => $item['id']];
        });
        $this->assertInstanceOf(CCollection::class, $groups);
        $this->assertEquals(['A' => [1], 'B' => [2, 4], 'C' => [3]], $groups->toArray());
        $this->assertIsArray($groups->get('A'), 'nilai grup array polos, bukan koleksi');
    }

    public function testMapToGroups() {
        $data = new CCollection([
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
            ['id' => 3, 'name' => 'C'],
            ['id' => 4, 'name' => 'B'],
        ]);
        $groups = $data->mapToGroups(function ($item) {
            return [$item['name'] => $item['id']];
        });
        $this->assertEquals(['A' => [1], 'B' => [2, 4], 'C' => [3]], $groups->toArray());
        $this->assertInstanceOf(CCollection::class, $groups->get('A'), 'nilai grup koleksi');
    }

    public function testMapWithKeys() {
        $data = new CCollection([
            ['name' => 'Blastoise', 'type' => 'Water', 'idx' => 9],
            ['name' => 'Charmander', 'type' => 'Fire', 'idx' => 4],
            ['name' => 'Dragonair', 'type' => 'Dragon', 'idx' => 148],
        ]);
        $data = $data->mapWithKeys(function ($pokemon) {
            return [$pokemon['name'] => $pokemon['type']];
        });
        $this->assertEquals(['Blastoise' => 'Water', 'Charmander' => 'Fire', 'Dragonair' => 'Dragon'], $data->all());
    }

    public function testMapWithKeysOverwritingKeysAndMultiplePairs() {
        $data = new CCollection([['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B'], ['id' => 1, 'name' => 'C']]);
        $this->assertSame([1 => 'C', 2 => 'B'], $data->mapWithKeys(function ($item) {
            return [$item['id'] => $item['name']];
        })->all(), 'kunci sama: yang terakhir menang');

        $this->assertSame(['a' => 1, 'b' => 2], (new CCollection([['a' => 1, 'b' => 2]]))->mapWithKeys(function ($item) {
            return $item;
        })->all(), 'satu callback boleh mengembalikan beberapa pasangan');
    }

    public function testMapInto() {
        $data = new CCollection(['first', 'second']);
        $data = $data->mapInto(UjiCollection_ValueObject::class);
        $this->assertSame('first', $data->get(0)->value);
        $this->assertSame('second', $data->get(1)->value);
    }

    public function testFlatMap() {
        $data = new CCollection([
            ['name' => 'taylor', 'hobbies' => ['programming', 'basketball']],
            ['name' => 'adam', 'hobbies' => ['music', 'powerlifting']],
        ]);
        $data = $data->flatMap(function ($person) {
            return $person['hobbies'];
        });
        $this->assertEquals(['programming', 'basketball', 'music', 'powerlifting'], $data->all());
    }

    public function testFlatten() {
        $c = new CCollection([['#foo', '#bar'], ['#baz']]);
        $this->assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

        $c = new CCollection(['#foo', ['#bar', ['#baz']], '#zap']);
        $this->assertEquals(['#foo', '#bar', '#baz', '#zap'], $c->flatten()->all());

        $c = new CCollection([[1, [2, [3, [4]]]], 5]);
        $this->assertEquals([1, [2, [3, [4]]], 5], $c->flatten(1)->all());
        $this->assertEquals([1, 2, [3, [4]], 5], $c->flatten(2)->all());

        $c = new CCollection([['#foo', ['#bar']], new CCollection(['#baz'])]);
        $this->assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all(), 'koleksi bersarang ikut diratakan');
    }

    public function testCollapse() {
        $data = new CCollection([[$object1 = new stdClass()], [$object2 = new stdClass()]]);
        $this->assertEquals([$object1, $object2], $data->collapse()->all());

        $data = new CCollection([new CCollection([1, 2, 3]), new CCollection([4, 5, 6])]);
        $this->assertEquals([1, 2, 3, 4, 5, 6], $data->collapse()->all());

        $data = new CCollection([[1, 2], 'not-an-array', [3]]);
        $this->assertEquals([1, 2, 3], $data->collapse()->all(), 'skalar di level atas dilewati');
    }

    public function testFlip() {
        $data = new CCollection(['name' => 'taylor', 'framework' => 'laravel']);
        $this->assertEquals(['taylor' => 'name', 'laravel' => 'framework'], $data->flip()->toArray());
    }

    public function testTransformMutatesInPlace() {
        $data = new CCollection(['first' => 'taylor', 'last' => 'otwell']);
        $result = $data->transform(function ($item, $key) {
            return $key . '-' . strrev($item);
        });
        $this->assertSame($data, $result);
        $this->assertEquals(['first' => 'first-rolyat', 'last' => 'last-llewto'], $data->all());
    }

    public function testCrossJoin() {
        $this->assertSame([[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']], (new CCollection([1, 2]))->crossJoin(['a', 'b'])->all());
        $this->assertSame([[1, 'a', 'I'], [1, 'a', 'II'], [1, 'b', 'I'], [1, 'b', 'II'], [2, 'a', 'I'], [2, 'a', 'II'], [2, 'b', 'I'], [2, 'b', 'II']], (new CCollection([1, 2]))->crossJoin(['a', 'b'], ['I', 'II'])->all());
        $this->assertSame([[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']], (new CCollection([1, 2]))->crossJoin(new CCollection(['a', 'b']))->all(), 'argumen boleh koleksi');
    }

    public function testZipWithArrayAndCollection() {
        $c = new CCollection([1, 2, 3]);
        $c = $c->zip(new CCollection([4, 5, 6]));
        $this->assertInstanceOf(CCollection::class, $c);
        $this->assertInstanceOf(CCollection::class, $c[0]);
        $this->assertEquals([1, 4], $c[0]->all());

        $c = (new CCollection([1, 2, 3]))->zip([4, 5, 6], [7, 8, 9]);
        $this->assertEquals([1, 4, 7], $c[0]->all());
        $this->assertEquals([3, 6, 9], $c[2]->all());

        $c = (new CCollection([1, 2, 3]))->zip([4, 5, 6], [7]);
        $this->assertEquals([2, 5, null], $c[1]->all(), 'panjang berbeda diisi null');
    }

    public function testPadWithNegativeSize() {
        $c = new CCollection([1, 2, 3]);
        $this->assertEquals([0, 0, 1, 2, 3], $c->pad(-5, 0)->all(), 'negatif = pad di depan');
        $this->assertEquals([1, 2, 3], $c->pad(2, 0)->all(), 'ukuran lebih kecil tidak memotong');
    }

    public function testSplit() {
        $data = new CCollection([1, 2, 3, 4, 5]);
        $this->assertEquals([[1, 2], [3, 4], [5]], $data->split(3)->map(function (CCollection $chunk) {
            return $chunk->values()->all();
        })->all());
        $this->assertEquals([[1], [2], [3], [4], [5]], $data->split(5)->map(function ($chunk) {
            return $chunk->values()->all();
        })->all());
        $this->assertEquals([[1, 2, 3, 4, 5]], $data->split(1)->map(function ($chunk) {
            return $chunk->values()->all();
        })->all());
        $this->assertEquals([[1, 2, 3], [4, 5]], $data->split(2)->map(function ($chunk) {
            return $chunk->values()->all();
        })->all(), 'sisa disebar ke grup pertama');
        $this->assertSame([], (new CCollection())->split(3)->all());
    }

    public function testSplitCollectionIntoThreeWithCountOfFour() {
        $data = new CCollection(['a', 'b', 'c', 'd']);
        $this->assertEquals([['a', 'b'], ['c'], ['d']], $data->split(3)->map(function ($chunk) {
            return $chunk->values()->all();
        })->all());
    }

    public function testChunkPreservesKeys() {
        $data = new CCollection(['a' => 1, 'b' => 2, 'c' => 3]);
        $chunks = $data->chunk(2);
        $this->assertSame(['a' => 1, 'b' => 2], $chunks->first()->all());
        $this->assertSame(['c' => 3], $chunks->last()->all());
        $this->assertSame([[1, 2], [3]], $data->chunk(2, false)->map(function ($chunk) {
            return $chunk->all();
        })->all(), 'preserveKeys=false menata ulang kunci');
    }

    public function testSum() {
        $c = new CCollection([(object) ['foo' => 50], (object) ['foo' => 50]]);
        $this->assertEquals(100, $c->sum('foo'));
        $this->assertEquals(100, $c->sum(function ($i) {
            return $i->foo;
        }));
        $this->assertEquals(6, (new CCollection([1, 2, 3]))->sum());
        $this->assertEquals(0, (new CCollection())->sum(), 'koleksi kosong = 0');
        $this->assertEquals(10, (new CCollection([['pages' => 4], ['pages' => 6]]))->sum('pages'));
        $this->assertEquals(7, (new CCollection([['a' => ['b' => 3]], ['a' => ['b' => 4]]]))->sum('a.b'), 'notasi titik');
    }

    public function testAvg() {
        $c = new CCollection([(object) ['foo' => 10], (object) ['foo' => 20]]);
        $this->assertEquals(15, $c->avg(function ($item) {
            return $item->foo;
        }));
        $this->assertEquals(15, $c->avg('foo'));
        $this->assertEquals(15, $c->average('foo'));
        $this->assertEquals(2, (new CCollection([1, 2, 3]))->avg());
        $this->assertEquals(2, (new CCollection([1, null, 3]))->avg(), 'null diabaikan dari pembagi');
        $this->assertNull((new CCollection())->avg());
        $this->assertEquals(15, (new CCollection([['foo' => 10], ['foo' => 20]]))->avg('foo'));
        $this->assertEquals(12.5, (new CCollection([['foo' => 10], ['foo' => 20], ['foo' => 5], ['foo' => 15]]))->avg('foo'));
    }

    public function testMinAndMaxWithKeysAndNulls() {
        $c = new CCollection([(object) ['foo' => 10], (object) ['foo' => 20]]);
        $this->assertEquals(10, $c->min('foo'));
        $this->assertEquals(20, $c->max('foo'));
        $this->assertEquals(10, $c->min(function ($item) {
            return $item->foo;
        }));
        $this->assertEquals(1, (new CCollection([1, null, 3]))->min(), 'null diabaikan');
        $this->assertEquals(3, (new CCollection([1, null, 3]))->max());
        $this->assertNull((new CCollection())->min());
        $this->assertNull((new CCollection())->max());
        $this->assertEquals(-5, (new CCollection([0, -5, 3]))->min());
    }

    public function testMedianAndModeOnAssociative() {
        $this->assertEquals(2, (new CCollection([['foo' => 1], ['foo' => 2], ['foo' => 3]]))->median('foo'));
        $this->assertEquals(1.5, (new CCollection([1, 2]))->median());
        $this->assertEquals([1], (new CCollection([1, 1, 2, 4]))->mode());
        $this->assertEquals([1, 2], (new CCollection([1, 1, 2, 2, 4]))->mode(), 'multi-modus urut naik');
        $this->assertNull((new CCollection())->mode());
    }

    public function testCountByWithDotNotationAndCallbackKeys() {
        $c = new CCollection([['user' => ['role' => 'admin']], ['user' => ['role' => 'staff']], ['user' => ['role' => 'admin']]]);
        $this->assertEquals(['admin' => 2, 'staff' => 1], $c->countBy('user.role')->all());
        $this->assertEquals(['a' => 2, 'b' => 1], (new CCollection(['alice', 'aaron', 'bob']))->countBy(function ($name) {
            return substr($name, 0, 1);
        })->all());
    }

    public function testEach() {
        $c = new CCollection($original = [1, 2, 'foo' => 'bar', 'bam' => 'baz']);
        $result = [];
        $c->each(function ($item, $key) use (&$result) {
            $result[$key] = $item;
        });
        $this->assertEquals($original, $result);

        $result = [];
        $c->each(function ($item, $key) use (&$result) {
            $result[$key] = $item;
            if (is_string($key)) {
                return false;
            }
        });
        $this->assertEquals([1, 2, 'foo' => 'bar'], $result, 'false menghentikan iterasi');
    }

    public function testEachSpread() {
        $c = new CCollection([[1, 'a'], [2, 'b']]);
        $result = [];
        $c->eachSpread(function ($number, $character) use (&$result) {
            $result[] = [$number, $character];
        });
        $this->assertEquals($c->all(), $result);

        $result = [];
        $c->eachSpread(function ($number, $character) use (&$result) {
            $result[] = [$number, $character];

            return false;
        });
        $this->assertEquals([[1, 'a']], $result);

        $result = [];
        $c->eachSpread(function ($number, $character, $key) use (&$result) {
            $result[] = [$number, $character, $key];
        });
        $this->assertEquals([[1, 'a', 0], [2, 'b', 1]], $result);
    }

    public function testContains() {
        $c = new CCollection([1, 3, 5]);
        $this->assertTrue($c->contains(1));
        $this->assertTrue($c->contains('1'), 'longgar');
        $this->assertFalse($c->contains(2));
        $this->assertTrue($c->contains(function ($value) {
            return $value < 5;
        }));
        $this->assertFalse($c->contains(function ($value) {
            return $value > 5;
        }));

        $c = new CCollection([['v' => 1], ['v' => 3], ['v' => 5]]);
        $this->assertTrue($c->contains('v', 1));
        $this->assertFalse($c->contains('v', 2));
        $this->assertTrue($c->contains('v', '>', 3));

        $c = new CCollection(['date', 'class', (object) ['foo' => 50]]);
        $this->assertTrue($c->contains('date'));
        $this->assertTrue($c->contains('class'));
        $this->assertFalse($c->contains('foo'));

        $c = new CCollection([null, 1, 2]);
        $this->assertTrue($c->contains(function ($value) {
            return is_null($value);
        }));
        $this->assertTrue((new CCollection([0]))->contains(0));
        $this->assertFalse((new CCollection([0]))->contains(1));
    }

    public function testContainsStrict() {
        $c = new CCollection([1, 3, 5, '02']);
        $this->assertTrue($c->containsStrict(1));
        $this->assertFalse($c->containsStrict('1'));
        $this->assertTrue($c->containsStrict('02'));
        $this->assertFalse($c->containsStrict(2));

        $c = new CCollection([['v' => 1], ['v' => 3], ['v' => '04'], ['v' => 5]]);
        $this->assertTrue($c->containsStrict('v', 1));
        $this->assertFalse($c->containsStrict('v', '1'));
        $this->assertTrue($c->containsStrict('v', '04'));
        $this->assertFalse($c->containsStrict('v', 4));
    }

    public function testSome() {
        $c = new CCollection([1, 3, 5]);
        $this->assertTrue($c->some(1));
        $this->assertFalse($c->some(2));
        $this->assertTrue($c->some(function ($value) {
            return $value < 5;
        }));
        $this->assertTrue((new CCollection([['v' => 1]]))->some('v', 1));
    }

    public function testEveryWithKeyOperatorValue() {
        $c = new CCollection([['age' => 18], ['age' => 20], ['age' => 20]]);
        $this->assertFalse($c->every('age', 18));
        $this->assertTrue($c->every('age', '>=', 18));
        $this->assertTrue($c->every(function ($item) {
            return $item['age'] >= 18;
        }));
        $this->assertTrue((new CCollection())->every('age', 18), 'kosong = true');
    }

    public function testImplode() {
        $data = new CCollection([['name' => 'taylor', 'email' => 'foo'], ['name' => 'dayle', 'email' => 'bar']]);
        $this->assertSame('foobar', $data->implode('email'));
        $this->assertSame('foo,bar', $data->implode('email', ','));

        $data = new CCollection(['taylor', 'dayle']);
        $this->assertSame('taylordayle', $data->implode(''));
        $this->assertSame('taylor,dayle', $data->implode(','));

        $data = new CCollection([['name' => 'taylor', 'email' => 'foo'], ['name' => 'dayle', 'email' => 'bar']]);
        $this->assertSame('TAYLOR, DAYLE', $data->implode(function ($item) {
            return strtoupper($item['name']);
        }, ', '), 'callback pemetaan sebelum digabung');
    }

    public function testJoin() {
        $this->assertSame('a, b, c', (new CCollection(['a', 'b', 'c']))->join(', '));
        $this->assertSame('a, b and c', (new CCollection(['a', 'b', 'c']))->join(', ', ' and '));
        $this->assertSame('a and b', (new CCollection(['a', 'b']))->join(', ', ' and '));
        $this->assertSame('a', (new CCollection(['a']))->join(', ', ' and '));
        $this->assertSame('', (new CCollection([]))->join(', ', ' and '));
    }

    public function testToJsonAndJsonSerialize() {
        $c = new CCollection([
            new UjiCollection_ArrayableStub(),
            new UjiCollection_JsonableStub(),
            new UjiCollection_JsonSerializeStub(),
            'baz',
        ]);
        $this->assertSame([
            ['foo' => 'bar'],
            ['foo' => 'bar'],
            ['foo' => 'bar'],
            'baz',
        ], $c->jsonSerialize(), 'Arrayable/Jsonable/JsonSerializable masing-masing diurai');
        $this->assertSame(json_encode($c->jsonSerialize()), $c->toJson());
        $this->assertSame(json_encode($c->jsonSerialize()), (string) $c);
        $this->assertSame(json_encode($c->jsonSerialize()), json_encode($c));
    }

    public function testToArrayCallsToArrayOnEachItemInCollection() {
        $item1 = new UjiCollection_ArrayableStub();
        $item2 = new UjiCollection_ArrayableStub();
        $c = new CCollection([$item1, $item2]);
        $this->assertSame([['foo' => 'bar'], ['foo' => 'bar']], $c->toArray());
        $this->assertSame([$item1, $item2], $c->all(), 'all() mengembalikan objek aslinya');
    }

    public function testPipeAndTap() {
        $data = new CCollection([1, 2, 3]);
        $this->assertSame(6, $data->pipe(function ($collection) {
            return $collection->sum();
        }));
        $fromTap = [];
        $result = $data->tap(function ($collection) use (&$fromTap) {
            $fromTap = $collection->slice(0, 1)->toArray();
        });
        $this->assertSame($data, $result);
        $this->assertSame([1], $fromTap);
    }

    public function testValuesResetKeys() {
        $c = new CCollection([1 => 'a', 2 => 'b']);
        $this->assertSame([0 => 'a', 1 => 'b'], $c->values()->all());
    }

    public function testToBaseAndLazy() {
        $sub = new UjiCollection_Subclass([1, 2]);
        $this->assertInstanceOf(UjiCollection_Subclass::class, $sub->map(function ($v) {
            return $v;
        }), 'metode mengembalikan static (subclass)');
        $base = $sub->toBase();
        $this->assertSame(CCollection::class, get_class($base));
        $this->assertInstanceOf(CCollection_LazyCollection::class, $sub->lazy());
        $this->assertSame([1, 2], $sub->lazy()->all());
    }

    public function testCollectMethodReturnsNewCollection() {
        $data = new CCollection([1, 2]);
        $copy = $data->collect();
        $this->assertNotSame($data, $copy);
        $this->assertSame([1, 2], $copy->all());
    }
}

class UjiCollection_ValueObject {
    public $value;

    public function __construct($value) {
        $this->value = $value;
    }
}

class UjiCollection_Subclass extends CCollection {
}

class UjiCollection_ArrayableStub implements CInterface_Arrayable {
    public function toArray() {
        return ['foo' => 'bar'];
    }
}

class UjiCollection_JsonableStub implements CInterface_Jsonable {
    public function toJson($options = 0) {
        return '{"foo":"bar"}';
    }
}

class UjiCollection_JsonSerializeStub implements JsonSerializable {
    public function jsonSerialize() {
        return ['foo' => 'bar'];
    }
}
