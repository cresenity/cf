<?php
use PHPUnit\Framework\TestCase;

/**
 * Port SupportCollectionTest hulu (bagian pengurutan, operasi himpunan, pengelompokan,
 * kondisional, dan utilitas lain) ke CCollection.
 */
class CollectionPortSetOpsTest extends TestCase {
    public function testSortWithoutCallbackKeepsKeys() {
        $data = (new CCollection([5, 3, 1, 2, 4]))->sort();
        $this->assertEquals([1, 2, 3, 4, 5], $data->values()->all());
        $this->assertSame([2 => 1, 3 => 2, 1 => 3, 4 => 4, 0 => 5], $data->all(), 'kunci asli dipertahankan');

        $data = (new CCollection([-1, -3, -2, -4, -5, 0, 5, 3, 1, 2, 4]))->sort();
        $this->assertEquals([-5, -4, -3, -2, -1, 0, 1, 2, 3, 4, 5], $data->values()->all());

        $data = (new CCollection(['foo', 'bar-10', 'bar-1']))->sort();
        $this->assertEquals(['bar-1', 'bar-10', 'foo'], $data->values()->all());
    }

    public function testSortWithCallback() {
        $data = (new CCollection([5, 3, 1, 2, 4]))->sort(function ($a, $b) {
            if ($a === $b) {
                return 0;
            }

            return ($a < $b) ? -1 : 1;
        });
        $this->assertEquals(range(1, 5), array_values($data->all()));
    }

    public function testSortWithFlags() {
        $data = (new CCollection(['item-10', 'item-2', 'item-1']))->sort(SORT_NATURAL);
        $this->assertSame(['item-1', 'item-2', 'item-10'], $data->values()->all());
    }

    public function testSortDesc() {
        $data = (new CCollection([5, 3, 1, 2, 4]))->sortDesc();
        $this->assertEquals([5, 4, 3, 2, 1], $data->values()->all());
        $this->assertSame([0 => 5, 4 => 4, 1 => 3, 3 => 2, 2 => 1], $data->all());
    }

    public function testSortBy() {
        $data = new CCollection(['taylor', 'dayle']);
        $data = $data->sortBy(function ($x) {
            return $x;
        });
        $this->assertEquals(['dayle', 'taylor'], array_values($data->all()));

        $data = new CCollection(['dayle', 'taylor']);
        $data = $data->sortByDesc(function ($x) {
            return $x;
        });
        $this->assertEquals(['taylor', 'dayle'], array_values($data->all()));
    }

    public function testSortByString() {
        $data = new CCollection([['name' => 'taylor'], ['name' => 'dayle']]);
        $data = $data->sortBy('name', SORT_STRING);
        $this->assertEquals([['name' => 'dayle'], ['name' => 'taylor']], array_values($data->all()));

        $data = new CCollection([['name' => 'taylor'], ['name' => 'dayle']]);
        $data = $data->sortBy('name', SORT_STRING, true);
        $this->assertEquals([['name' => 'taylor'], ['name' => 'dayle']], array_values($data->all()));
    }

    public function testSortByPreservesKeysAndUsesDotNotation() {
        $data = new CCollection(['a' => ['user' => ['age' => 30]], 'b' => ['user' => ['age' => 20]]]);
        $this->assertSame(['b', 'a'], $data->sortBy('user.age')->keys()->all());
        $this->assertSame(['a', 'b'], $data->sortByDesc('user.age')->keys()->all());
    }

    public function testSortByCallableStringWithKey() {
        $data = new CCollection([2 => 'b', 1 => 'a', 3 => 'c']);
        $this->assertSame([1 => 'a', 2 => 'b', 3 => 'c'], $data->sortBy(function ($value, $key) {
            return $key;
        })->all(), 'callback menerima kunci sebagai argumen kedua');
    }

    public function testSortByMany() {
        $data = new CCollection([['item' => '1', 'price' => 200], ['item' => '2', 'price' => 100], ['item' => '3', 'price' => 200]]);
        $sorted = $data->sortBy([['price', 'desc'], ['item', 'asc']]);
        $this->assertSame(['1', '3', '2'], $sorted->pluck('item')->all());

        $sorted = $data->sortBy([['price', 'asc'], ['item', 'desc']]);
        $this->assertSame(['2', '3', '1'], $sorted->pluck('item')->all());

        $sorted = $data->sortBy([function ($a, $b) {
            return $b['price'] <=> $a['price'];
        }, ['item', 'desc']]);
        $this->assertSame(['3', '1', '2'], $sorted->pluck('item')->all(), 'perbandingan boleh closure');
        $this->assertSame([0, 1, 2], $sorted->keys()->all(), 'sortBy banyak kolom menata ulang kunci');
    }

    public function testSortKeys() {
        $data = new CCollection(['b' => 'dayle', 'a' => 'taylor']);
        $this->assertSame(['a' => 'taylor', 'b' => 'dayle'], $data->sortKeys()->all());
        $this->assertSame(['b' => 'dayle', 'a' => 'taylor'], $data->sortKeysDesc()->all());
        $this->assertSame(['a' => 'taylor', 'b' => 'dayle'], $data->sortKeys(SORT_STRING)->all());
    }

    public function testReverseKeepsKeys() {
        $data = new CCollection(['zaeed', 'alan']);
        $this->assertSame([1 => 'alan', 0 => 'zaeed'], $data->reverse()->all());
        $data = new CCollection(['name' => 'taylor', 'framework' => 'laravel']);
        $this->assertSame(['framework' => 'laravel', 'name' => 'taylor'], $data->reverse()->all());
    }

    public function testDiff() {
        $c = new CCollection(['id' => 1, 'first_word' => 'Hello']);
        $this->assertEquals(['id' => 1], $c->diff(new CCollection(['first_word' => 'Hello', 'last_word' => 'World']))->all());
        $this->assertEquals(['id' => 1], $c->diff(['first_word' => 'Hello'])->all(), 'argumen array polos');
        $this->assertEquals($c->all(), $c->diff(null)->all(), 'null = tidak ada yang dibuang');
    }

    public function testDiffUsingWithCollectionAndCallback() {
        $c = new CCollection(['en_GB', 'fr', 'HR']);
        $this->assertEquals([1 => 'fr'], $c->diffUsing(new CCollection(['en_gb', 'hr']), 'strcasecmp')->all());
    }

    public function testDiffKeys() {
        $c1 = new CCollection(['id' => 1, 'first_word' => 'Hello']);
        $c2 = new CCollection(['id' => 123, 'foo_bar' => 'Hello']);
        $this->assertEquals(['first_word' => 'Hello'], $c1->diffKeys($c2)->all());

        $c1 = new CCollection(['id' => 1, 'first_word' => 'Hello']);
        $c2 = new CCollection(['ID' => 123, 'foo_bar' => 'Hello']);
        $this->assertEquals(['first_word' => 'Hello'], $c1->diffKeysUsing($c2, 'strcasecmp')->all(), 'pembanding kunci kustom');
    }

    public function testDiffAssoc() {
        $c1 = new CCollection(['id' => 1, 'first_word' => 'Hello', 'not_affected' => 'value']);
        $c2 = new CCollection(['id' => 123, 'foo_bar' => 'Hello', 'not_affected' => 'value']);
        $this->assertEquals(['id' => 1, 'first_word' => 'Hello'], $c1->diffAssoc($c2)->all());

        $c1 = new CCollection(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red']);
        $c2 = new CCollection(['A' => 'green', 'yellow', 'red']);
        $this->assertEquals(['b' => 'brown', 'c' => 'blue', 'red'], $c1->diffAssocUsing($c2, 'strcasecmp')->all());
    }

    public function testIntersect() {
        $c = new CCollection(['id' => 1, 'first_word' => 'Hello']);
        $this->assertEquals(['first_word' => 'Hello'], $c->intersect(new CCollection(['first_world' => 'Hello', 'last_word' => 'World']))->all());
        $this->assertEquals([], $c->intersect(null)->all(), 'null = kosong');
    }

    public function testIntersectByKeys() {
        $c = new CCollection(['name' => 'Mateus', 'age' => 18]);
        $this->assertEquals(['name' => 'Mateus'], $c->intersectByKeys(new CCollection(['name' => 'Mateus', 'surname' => 'Guimaraes']))->all());

        $c = new CCollection(['first_word' => 'Hello', 'last_word' => 'World']);
        $this->assertEquals(['first_word' => 'Hello'], $c->intersectByKeys(['first_world' => 'Hello', 'first_word' => 'World'])->all());
    }

    public function testMerge() {
        $c = new CCollection(['name' => 'Hello']);
        $this->assertEquals(['name' => 'Hello'], $c->merge(null)->all());
        $this->assertEquals(['name' => 'Hello', 'id' => 1], $c->merge(['id' => 1])->all());
        $this->assertEquals(['name' => 'World', 'id' => 1], $c->merge(new CCollection(['name' => 'World', 'id' => 1]))->all(), 'kunci string ditimpa');
        $this->assertEquals([1, 2, 3, 4], (new CCollection([1, 2]))->merge([3, 4])->all(), 'kunci numerik ditambahkan');
    }

    public function testMergeRecursive() {
        $c = new CCollection(['name' => 'Hello', 'id' => 1, 'meta' => ['tags' => ['a', 'b'], 'roles' => 'admin']]);
        $this->assertEquals(
            ['name' => 'Hello', 'id' => 1, 'meta' => ['tags' => ['a', 'b', 'c'], 'roles' => ['admin', 'editor']]],
            $c->mergeRecursive(['meta' => ['tags' => ['c'], 'roles' => 'editor']])->all()
        );
        $this->assertEquals(['name' => 'Hello', 'id' => [1, 2]], (new CCollection(['name' => 'Hello', 'id' => 1]))->mergeRecursive(['id' => 2])->only(['name', 'id'])->all(), 'skalar bentrok menjadi array');
    }

    public function testReplace() {
        $c = new CCollection(['a', 'b', 'c']);
        $this->assertEquals(['a', 'b', 'c'], $c->replace(null)->all());
        $this->assertEquals(['a', 'd', 'e'], $c->replace([1 => 'd', 2 => 'e'])->all());
        $this->assertEquals(['a', 'd', 'e', 'f', 'g'], $c->replace(new CCollection([1 => 'd', 2 => 'e', 3 => 'f', 4 => 'g']))->all(), 'kunci numerik ditimpa, bukan ditambah seperti merge');
    }

    public function testReplaceRecursive() {
        $c = new CCollection(['a', 'b', ['c', 'd']]);
        $this->assertEquals(['a', 'b', ['c', 'd']], $c->replaceRecursive(null)->all());
        $this->assertEquals(['z', 'b', ['c', 'e']], $c->replaceRecursive(['z', 2 => [1 => 'e']])->all());
        $this->assertEquals(['z', 'b', ['c', 'e']], $c->replaceRecursive(new CCollection(['z', 2 => [1 => 'e']]))->all());
    }

    public function testUnion() {
        $c = new CCollection(['name' => 'Hello']);
        $this->assertEquals(['name' => 'Hello'], $c->union(null)->all());
        $this->assertEquals(['name' => 'Hello', 'id' => 1], $c->union(['id' => 1])->all());
        $this->assertEquals(['name' => 'Hello', 'id' => 1], $c->union(new CCollection(['name' => 'World', 'id' => 1]))->all(), 'nilai yang sudah ada dipertahankan');
    }

    public function testCombineWithCollectionValues() {
        $c = new CCollection(['a', 'b']);
        $this->assertSame(['a' => 1, 'b' => 2], $c->combine([1, 2])->all());
        $this->assertSame(['a' => 1, 'b' => 2], $c->combine(new CCollection([1, 2]))->all());
    }

    public function testGroupByAttribute() {
        $data = new CCollection([['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1'], ['rating' => 2, 'url' => '2']]);
        $result = $data->groupBy('rating');
        $this->assertEquals([1 => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']], 2 => [['rating' => 2, 'url' => '2']]], $result->toArray());
        $this->assertInstanceOf(CCollection::class, $result->get(1));

        $result = $data->groupBy('url');
        $this->assertEquals(['1' => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']], '2' => [['rating' => 2, 'url' => '2']]], $result->toArray());
    }

    public function testGroupByAttributePreservingKeys() {
        $data = new CCollection([10 => ['rating' => 1, 'url' => '1'], 20 => ['rating' => 1, 'url' => '1'], 30 => ['rating' => 2, 'url' => '2']]);
        $result = $data->groupBy('rating', true);
        $this->assertEquals([1 => [10 => ['rating' => 1, 'url' => '1'], 20 => ['rating' => 1, 'url' => '1']], 2 => [30 => ['rating' => 2, 'url' => '2']]], $result->toArray());
    }

    public function testGroupByClosureWhereItemsHaveSingleGroup() {
        $data = new CCollection([['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1'], ['rating' => 2, 'url' => '2']]);
        $result = $data->groupBy(function ($item) {
            return $item['rating'];
        });
        $this->assertEquals([1 => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']], 2 => [['rating' => 2, 'url' => '2']]], $result->toArray());
    }

    public function testGroupByClosureWhereItemsHaveMultipleGroups() {
        $data = new CCollection([
            ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
            ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
            ['user' => 3, 'roles' => ['Role_1']],
        ]);
        $result = $data->groupBy(function ($item) {
            return $item['roles'];
        });
        $this->assertEquals([
            'Role_1' => [['user' => 1, 'roles' => ['Role_1', 'Role_3']], ['user' => 2, 'roles' => ['Role_1', 'Role_2']], ['user' => 3, 'roles' => ['Role_1']]],
            'Role_2' => [['user' => 2, 'roles' => ['Role_1', 'Role_2']]],
            'Role_3' => [['user' => 1, 'roles' => ['Role_1', 'Role_3']]],
        ], $result->toArray(), 'callback yang mengembalikan array menempatkan item ke tiap grup');
    }

    public function testGroupByMultiLevelAndWithDotNotation() {
        $data = new CCollection([
            10 => ['user' => 1, 'skilllevel' => 1, 'roles' => 'Role_1'],
            20 => ['user' => 2, 'skilllevel' => 1, 'roles' => 'Role_1'],
            30 => ['user' => 3, 'skilllevel' => 2, 'roles' => 'Role_2'],
        ]);
        $result = $data->groupBy(['skilllevel', 'roles']);
        $this->assertSame([1, 2], $result->keys()->all());
        $this->assertSame(['Role_1'], $result->get(1)->keys()->all());
        $this->assertCount(2, $result->get(1)->get('Role_1'));

        $data = new CCollection([['a' => ['b' => 'x']], ['a' => ['b' => 'y']], ['a' => ['b' => 'x']]]);
        $this->assertSame(['x', 'y'], $data->groupBy('a.b')->keys()->all());
    }

    public function testGroupByBooleanAndNullKeys() {
        $data = new CCollection([['flag' => true], ['flag' => false], ['flag' => null], ['flag' => true]]);
        $result = $data->groupBy('flag');
        $this->assertSame([1, 0, ''], $result->keys()->all(), 'bool dipetakan ke 1/0, null ke ""');
        $this->assertCount(2, $result->get(1));
    }

    public function testKeyByWithDotNotationAndCallbackKey() {
        $data = new CCollection([['a' => ['id' => 'x'], 'v' => 1], ['a' => ['id' => 'y'], 'v' => 2]]);
        $this->assertSame(['x', 'y'], $data->keyBy('a.id')->keys()->all());
        $this->assertSame(['0-x', '1-y'], $data->keyBy(function ($item, $key) {
            return $key . '-' . $item['a']['id'];
        })->keys()->all());
    }

    public function testPartition() {
        $collection = new CCollection(range(1, 10));
        list($firstPartition, $secondPartition) = $collection->partition(function ($i) {
            return $i <= 5;
        })->all();
        $this->assertEquals([1, 2, 3, 4, 5], $firstPartition->values()->toArray());
        $this->assertEquals([6, 7, 8, 9, 10], $secondPartition->values()->toArray());
    }

    public function testPartitionByKeyAndOperator() {
        $courses = new CCollection([['free' => true, 'title' => 'Basic'], ['free' => false, 'title' => 'Premium']]);
        list($free, $premium) = $courses->partition('free')->all();
        $this->assertSame([['free' => true, 'title' => 'Basic']], $free->values()->toArray());
        $this->assertSame([['free' => false, 'title' => 'Premium']], $premium->values()->toArray());

        list($free, $premium) = $courses->partition('free', '===', false)->all();
        $this->assertSame([['free' => false, 'title' => 'Premium']], $free->values()->toArray());
    }

    public function testPartitionPreservesKeysAndEmptyPartitions() {
        $collection = new CCollection(['a' => 1, 'b' => 2, 'c' => 3]);
        list($odd, $even) = $collection->partition(function ($v) {
            return $v % 2;
        })->all();
        $this->assertSame(['a' => 1, 'c' => 3], $odd->all());
        $this->assertSame(['b' => 2], $even->all());

        list($all, $none) = (new CCollection([1, 2]))->partition(function () {
            return true;
        })->all();
        $this->assertCount(2, $all);
        $this->assertTrue($none->isEmpty(), 'partisi kosong tetap koleksi, bukan null');
    }

    public function testWhenAndUnless() {
        $c = new CCollection(['michael', 'tom']);
        $c = $c->when(true, function ($collection) {
            return $collection->push('adam');
        });
        $this->assertSame(['michael', 'tom', 'adam'], $c->toArray());

        $c = new CCollection(['michael', 'tom']);
        $c = $c->when(false, function ($collection) {
            return $collection->push('adam');
        });
        $this->assertSame(['michael', 'tom'], $c->toArray(), 'when(false) mengembalikan koleksi apa adanya');

        $c = new CCollection(['michael', 'tom']);
        $c = $c->when(false, function ($collection) {
            return $collection->push('adam');
        }, function ($collection) {
            return $collection->push('taylor');
        });
        $this->assertSame(['michael', 'tom', 'taylor'], $c->toArray(), 'default dijalankan saat kondisi false');

        $c = new CCollection(['michael', 'tom']);
        $c = $c->unless(false, function ($collection) {
            return $collection->push('adam');
        });
        $this->assertSame(['michael', 'tom', 'adam'], $c->toArray());

        $c = new CCollection(['michael', 'tom']);
        $c = $c->unless(true, function ($collection) {
            return $collection->push('adam');
        }, function ($collection) {
            return $collection->push('taylor');
        });
        $this->assertSame(['michael', 'tom', 'taylor'], $c->toArray());
    }

    public function testWhenPassesTheValueAndAcceptsClosureCondition() {
        $c = new CCollection(['a']);
        $c = $c->when('adam', function ($collection, $value) {
            return $collection->push($value);
        });
        $this->assertSame(['a', 'adam'], $c->toArray(), 'nilai kondisi diteruskan sebagai argumen kedua');

        $c = new CCollection(['a']);
        $c = $c->when(function ($collection) {
            return $collection->count() === 1;
        }, function ($collection) {
            return $collection->push('one');
        });
        $this->assertSame(['a', 'one'], $c->toArray(), 'kondisi boleh closure yang menerima koleksi');
    }

    public function testWhenEmptyAndWhenNotEmpty() {
        $c = new CCollection(['michael', 'tom']);
        $c = $c->whenEmpty(function ($collection) {
            return $collection->push('adam');
        });
        $this->assertSame(['michael', 'tom'], $c->toArray());

        $c = new CCollection();
        $c = $c->whenEmpty(function ($collection) {
            return $collection->push('adam');
        });
        $this->assertSame(['adam'], $c->toArray());

        $c = new CCollection(['michael', 'tom']);
        $c = $c->whenEmpty(function ($collection) {
            return $collection->push('adam');
        }, function ($collection) {
            return $collection->push('taylor');
        });
        $this->assertSame(['michael', 'tom', 'taylor'], $c->toArray());

        $c = new CCollection(['michael', 'tom']);
        $c = $c->whenNotEmpty(function ($collection) {
            return $collection->push('adam');
        });
        $this->assertSame(['michael', 'tom', 'adam'], $c->toArray());

        $c = new CCollection();
        $c = $c->whenNotEmpty(function ($collection) {
            return $collection->push('adam');
        }, function ($collection) {
            return $collection->push('taylor');
        });
        $this->assertSame(['taylor'], $c->toArray());
    }

    public function testUnlessEmptyAndUnlessNotEmpty() {
        $c = new CCollection(['michael', 'tom']);
        $c = $c->unlessEmpty(function ($collection) {
            return $collection->push('adam');
        });
        $this->assertSame(['michael', 'tom', 'adam'], $c->toArray());

        $c = new CCollection();
        $c = $c->unlessEmpty(function ($collection) {
            return $collection->push('adam');
        }, function ($collection) {
            return $collection->push('taylor');
        });
        $this->assertSame(['taylor'], $c->toArray());

        $c = new CCollection();
        $c = $c->unlessNotEmpty(function ($collection) {
            return $collection->push('adam');
        });
        $this->assertSame(['adam'], $c->toArray());

        $c = new CCollection(['michael', 'tom']);
        $c = $c->unlessNotEmpty(function ($collection) {
            return $collection->push('adam');
        }, function ($collection) {
            return $collection->push('taylor');
        });
        $this->assertSame(['michael', 'tom', 'taylor'], $c->toArray());
    }

    public function testHigherOrderProxies() {
        $c = new CCollection([new UjiCollection_Person('taylor', 3), new UjiCollection_Person('dayle', 1)]);
        $this->assertSame(['taylor', 'dayle'], $c->map->name->all(), 'map->prop');
        $this->assertSame(['TAYLOR', 'DAYLE'], $c->map->shout()->all(), 'map->method()');
        $this->assertSame(4, $c->sum->age);
        $this->assertSame(['dayle', 'taylor'], $c->sortBy->age->pluck('name')->all());
        $this->assertSame('taylor', $c->first->isSenior()->name);
        $this->assertSame(1, $c->filter->isSenior()->count());
        $this->assertSame(1, $c->reject->isSenior()->count());
        $this->assertTrue($c->contains->isSenior());
        $this->assertFalse($c->every->isSenior());
        $this->assertSame(['taylor' => 3, 'dayle' => 1], $c->keyBy->name->map->age->all());
    }

    public function testHigherOrderEachAndUnknownPropertyThrows() {
        $c = new CCollection([new UjiCollection_Person('taylor', 3)]);
        $c->each->shout();
        $this->assertSame(1, $c->count());
        $this->expectException(Exception::class);
        $c->nonExistentProxy;
    }

    public function testMacroableWithParameters() {
        CCollection::macro('ujiPrefixAll', function ($prefix) {
            return $this->map(function ($item) use ($prefix) {
                return $prefix . $item;
            });
        });
        $this->assertTrue(CCollection::hasMacro('ujiPrefixAll'));
        $this->assertSame(['x-a', 'x-b'], (new CCollection(['a', 'b']))->ujiPrefixAll('x-')->all());
    }

    public function testArrayAccessAndIteration() {
        $c = new CCollection(['name' => 'taylor']);
        $c['framework'] = 'cf';
        $c[] = 'appended';
        $this->assertSame('cf', $c['framework']);
        $this->assertSame('appended', $c[0]);
        unset($c['name']);
        $this->assertFalse(isset($c['name']));
        $seen = [];
        foreach ($c as $key => $value) {
            $seen[$key] = $value;
        }
        $this->assertSame(['framework' => 'cf', 0 => 'appended'], $seen);
        $this->assertInstanceOf(ArrayIterator::class, $c->getIterator());
    }

    public function testSearchWithCallback() {
        $c = new CCollection([false, 0, 1, [], '']);
        $this->assertSame(2, $c->search(function ($value) {
            return $value === 1;
        }));
        $this->assertFalse($c->search(function ($value) {
            return $value === 'missing';
        }));
        $this->assertSame(0, $c->search(0), 'longgar: false == 0');
        $this->assertSame(1, $c->search(0, true));
    }

    public function testPluckWithKeyAndDotNotation() {
        $data = new CCollection([['id' => 1, 'user' => ['name' => 'a']], ['id' => 2, 'user' => ['name' => 'b']]]);
        $this->assertSame(['a', 'b'], $data->pluck('user.name')->all());
        $this->assertSame([1 => 'a', 2 => 'b'], $data->pluck('user.name', 'id')->all());
        $this->assertSame(['a' => 1, 'b' => 2], $data->pluck('id', 'user.name')->all());
        $this->assertSame([null, null], $data->pluck('missing')->all(), 'kunci hilang → null');
    }

    public function testPluckWithWildcard() {
        $data = new CCollection([['users' => [['name' => 'a'], ['name' => 'b']]], ['users' => [['name' => 'c']]]]);
        $this->assertSame([['a', 'b'], ['c']], $data->pluck('users.*.name')->all());
    }

    public function testShuffleKeepsAllItems() {
        $data = new CCollection(range(1, 20));
        $shuffled = $data->shuffle();
        $this->assertNotSame($data, $shuffled);
        $this->assertEquals(range(1, 20), $shuffled->sort()->values()->all());
    }

    public function testSliceNegativeOffsetAndForPage() {
        $data = new CCollection([1, 2, 3, 4, 5, 6, 7, 8, 9]);
        $this->assertSame([1, 2, 3], $data->forPage(1, 3)->values()->all());
        $this->assertSame([4, 5, 6], $data->forPage(2, 3)->values()->all());
        $this->assertSame([7, 8, 9], $data->forPage(3, 3)->values()->all());
        $this->assertSame([], $data->forPage(4, 3)->values()->all());
    }

    public function testChunkWhile() {
        $data = new CCollection(['A', 'A', 'B', 'B', 'C', 'C', 'C', 'D']);
        $chunks = $data->chunkWhile(function ($current, $key, $chunk) {
            return $current === $chunk->last();
        });
        $this->assertSame([['A', 'A'], [2 => 'B', 3 => 'B'], [4 => 'C', 5 => 'C', 6 => 'C'], [7 => 'D']], $chunks->map(function ($chunk) {
            return $chunk->all();
        })->all(), 'kunci asli dipertahankan tiap potongan');
    }

    public function testSlidingWithStep() {
        $data = new CCollection([1, 2, 3, 4, 5]);
        $this->assertSame([[1, 2, 3], [3, 4, 5]], $data->sliding(3, 2)->map(function ($w) {
            return $w->values()->all();
        })->all());
        $this->assertSame([], (new CCollection([1]))->sliding(2)->all(), 'lebih pendek dari jendela → kosong');
    }

    public function testCountByWithoutArgument() {
        $this->assertSame(['a' => 2, 'b' => 1], (new CCollection(['a', 'b', 'a']))->countBy()->all());
    }

    public function testDumpDoesNotAlterCollection() {
        $data = new CCollection([1, 2]);
        ob_start();
        $result = $data->dump();
        ob_end_clean();
        $this->assertSame($data, $result);
    }

    public function testMakeAndWrapAndTimes() {
        $this->assertSame([1, 2], CCollection::make([1, 2])->all());
        $this->assertSame(['a'], CCollection::wrap('a')->all());
        $this->assertSame([2, 4, 6], CCollection::times(3, function ($n) {
            return $n * 2;
        })->all());
        $this->assertSame([], CCollection::times(0)->all());
        $this->assertSame([1, 2, 3], CCollection::times(3)->all());
    }

    public function testEmptyStaticAndRange() {
        $this->assertTrue(CCollection::empty()->isEmpty());
        $this->assertSame([3, 4, 5], CCollection::range(3, 5)->all());
        $this->assertSame([5, 4, 3], CCollection::range(5, 3)->all());
    }

    public function testIsEmptyIsNotEmptyAndCountable() {
        $this->assertTrue((new CCollection())->isEmpty());
        $this->assertTrue((new CCollection([1]))->isNotEmpty());
        $this->assertCount(3, new CCollection([1, 2, 3]));
        $this->assertSame(3, (new CCollection([1, 2, 3]))->count());
    }

    public function testPushPutPrependAddAndPull() {
        $c = new CCollection([2]);
        $c->prepend(1)->push(3)->put('k', 'v')->add(4);
        $this->assertSame([1, 2, 3, 'k' => 'v', 4], $c->all());
        $this->assertSame('v', $c->pull('k'));
        $this->assertSame([1, 2, 3, 4], $c->values()->all());
        $c->prepend('zero', 'z');
        $this->assertSame('z', $c->keys()->first(), 'prepend dengan kunci');
    }

    public function testEscapeWhenCastingToString() {
        $c = new CCollection(['<b>']);
        $this->assertSame('["<b>"]', (string) $c);
        $this->assertSame('[&quot;&lt;b&gt;&quot;]', (string) $c->escapeWhenCastingToString());
        $this->assertSame('["<b>"]', (string) $c->escapeWhenCastingToString(false));
    }
}

class UjiCollection_Person {
    public $name;

    public $age;

    public function __construct($name, $age) {
        $this->name = $name;
        $this->age = $age;
    }

    public function shout() {
        return strtoupper($this->name);
    }

    public function isSenior() {
        return $this->age >= 3;
    }
}
