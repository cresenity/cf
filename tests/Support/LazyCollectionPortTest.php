<?php
use PHPUnit\Framework\TestCase;

/**
 * Port SupportLazyCollectionIsLazyTest hulu (inti): operasi CCollection_LazyCollection tidak
 * menghabiskan sumber sebelum dibutuhkan, dan hasilnya sama dengan CCollection.
 */
class LazyCollectionPortTest extends TestCase {
    /**
     * Sumber tak berhingga yang mencatat berapa item sudah ditarik.
     *
     * @param int $pulled
     *
     * @return CCollection_LazyCollection
     */
    protected function counting(&$pulled) {
        $pulled = 0;

        return new CCollection_LazyCollection(function () use (&$pulled) {
            for ($i = 1; ; $i++) {
                $pulled++;

                yield $i;
            }
        });
    }

    public function testMakingTheCollectionDoesNotEnumerate() {
        $c = $this->counting($pulled);
        $c->map(function ($v) {
            return $v * 2;
        })->filter(function ($v) {
            return $v % 3 === 0;
        })->take(2);
        $this->assertSame(0, $pulled, 'rantai operasi belum menarik apa pun');
    }

    public function testTakeOnlyEnumeratesWhatItNeeds() {
        $c = $this->counting($pulled);
        $this->assertSame([1, 2, 3], $c->take(3)->all());
        $this->assertSame(3, $pulled);
    }

    public function testFirstStopsAtTheFirstMatch() {
        $c = $this->counting($pulled);
        $this->assertSame(4, $c->first(function ($v) {
            return $v > 3;
        }));
        $this->assertSame(4, $pulled);
    }

    public function testMapFilterRejectAreLazy() {
        $c = $this->counting($pulled);
        $result = $c->map(function ($v) {
            return $v * 10;
        })->filter(function ($v) {
            return $v % 20 === 0;
        })->reject(function ($v) {
            return $v === 40;
        })->take(2)->values()->all();
        $this->assertSame([20, 60], $result);
        $this->assertSame(6, $pulled);
    }

    public function testTakeWhileAndTakeUntilStopEarly() {
        $c = $this->counting($pulled);
        $this->assertSame([1, 2], $c->takeWhile(function ($v) {
            return $v < 3;
        })->all());
        $this->assertSame(3, $pulled, 'berhenti setelah membaca item pertama yang gagal');

        $c = $this->counting($pulled);
        $this->assertSame([1, 2, 3], $c->takeUntil(4)->all());
        $this->assertSame(4, $pulled);
    }

    public function testSkipAndSkipWhileAreLazy() {
        $c = $this->counting($pulled);
        $this->assertSame([4, 5], $c->skip(3)->take(2)->values()->all());
        $this->assertSame(5, $pulled);

        $c = $this->counting($pulled);
        $this->assertSame([3], $c->skipWhile(function ($v) {
            return $v < 3;
        })->take(1)->values()->all());
        $this->assertSame(3, $pulled);
    }

    public function testChunkAndSlidingAreLazy() {
        $c = $this->counting($pulled);
        $this->assertSame([[1, 2], [3, 4]], $c->chunk(2)->take(2)->map(function ($chunk) {
            return $chunk->values()->all();
        })->all());
        $this->assertLessThanOrEqual(5, $pulled);

        $c = $this->counting($pulled);
        $this->assertSame([[1, 2], [2, 3]], $c->sliding(2)->take(2)->map(function ($w) {
            return $w->values()->all();
        })->all());
        $this->assertLessThanOrEqual(4, $pulled);
    }

    public function testContainsStopsAtTheFirstMatch() {
        $c = $this->counting($pulled);
        $this->assertTrue($c->contains(5));
        $this->assertSame(5, $pulled);
        $c = $this->counting($pulled);
        $this->assertTrue($c->contains(function ($v) {
            return $v === 2;
        }));
        $this->assertSame(2, $pulled);
    }

    public function testEveryAndSomeShortCircuit() {
        $c = $this->counting($pulled);
        $this->assertFalse($c->every(function ($v) {
            return $v < 3;
        }));
        $this->assertSame(3, $pulled);
        $c = $this->counting($pulled);
        $this->assertTrue($c->some(function ($v) {
            return $v === 2;
        }));
        $this->assertSame(2, $pulled);
    }

    public function testEachCanBreakEarly() {
        $c = $this->counting($pulled);
        $seen = [];
        $c->each(function ($v) use (&$seen) {
            $seen[] = $v;

            return $v < 3;
        });
        $this->assertSame([1, 2, 3], $seen);
        $this->assertSame(3, $pulled);
    }

    public function testEagerMaterialisesOnce() {
        $runs = 0;
        $c = new CCollection_LazyCollection(function () use (&$runs) {
            $runs++;
            yield 1;
            yield 2;
        });
        $c->all();
        $c->all();
        $this->assertSame(2, $runs, 'tanpa eager, generator dijalankan ulang tiap enumerasi');
        $eager = $c->eager();
        $this->assertSame(3, $runs);
        $eager->all();
        $eager->all();
        $this->assertSame(3, $runs, 'eager: sumber dibaca sekali');
    }

    public function testRememberSharesOneEnumerationAcrossConsumers() {
        $runs = 0;
        $c = (new CCollection_LazyCollection(function () use (&$runs) {
            $runs++;
            yield 'a' => 1;
            yield 'b' => 2;
            yield 'c' => 3;
        }))->remember();
        $this->assertSame(['a' => 1], $c->take(1)->all());
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $c->all(), 'melanjutkan dari cache lalu sumber');
        $this->assertSame(1, $runs);
    }

    public function testResultsMatchCollectionForCommonOperations() {
        $source = [3, 1, 2, 5, 4];
        $lazy = new CCollection_LazyCollection($source);
        $eager = new CCollection($source);
        $this->assertSame($eager->sort()->values()->all(), $lazy->sort()->values()->all());
        $this->assertSame($eager->sortDesc()->values()->all(), $lazy->sortDesc()->values()->all());
        $this->assertSame($eager->sum(), $lazy->sum());
        $this->assertSame($eager->avg(), $lazy->avg());
        $this->assertSame($eager->max(), $lazy->max());
        $this->assertSame($eager->min(), $lazy->min());
        $this->assertSame($eager->median(), $lazy->median());
        $this->assertSame($eager->reverse()->values()->all(), $lazy->reverse()->values()->all());
        $this->assertSame($eager->unique()->values()->all(), $lazy->unique()->values()->all());
        $this->assertSame($eager->chunk(2)->map->all()->all(), $lazy->chunk(2)->map->all()->all());
        $this->assertSame($eager->keys()->all(), $lazy->keys()->all());
        $this->assertSame($eager->last(), $lazy->last());
        $this->assertSame($eager->count(), $lazy->count());
        $this->assertSame($eager->implode(','), $lazy->implode(','));
        $this->assertSame($eager->join(', ', ' and '), $lazy->join(', ', ' and '));
        $this->assertSame($eager->pad(7, 0)->all(), $lazy->pad(7, 0)->all());
        $this->assertSame($eager->zip([1, 2])->map->all()->all(), $lazy->zip([1, 2])->map->all()->all());
        $this->assertSame([1, 2, 3], (new CCollection_LazyCollection([[1], [2, 3]]))->collapse()->all());
    }

    public function testGroupByKeyByPluckAndWhereMatchCollection() {
        $rows = [['id' => 1, 'type' => 'a'], ['id' => 2, 'type' => 'b'], ['id' => 3, 'type' => 'a']];
        $lazy = new CCollection_LazyCollection($rows);
        $eager = new CCollection($rows);
        $this->assertSame($eager->groupBy('type')->map->pluck('id')->map->all()->all(), $lazy->groupBy('type')->map->pluck('id')->map->all()->all());
        $this->assertSame($eager->keyBy('id')->keys()->all(), $lazy->keyBy('id')->keys()->all());
        $this->assertSame($eager->pluck('type', 'id')->all(), $lazy->pluck('type', 'id')->all());
        $this->assertSame($eager->where('type', 'a')->pluck('id')->all(), $lazy->where('type', 'a')->pluck('id')->all());
        $this->assertSame($eager->firstWhere('type', 'b'), $lazy->firstWhere('type', 'b'));
        $this->assertSame($eager->countBy('type')->all(), $lazy->countBy('type')->all());
        $this->assertSame($eager->sortBy('type')->pluck('id')->all(), $lazy->sortBy('type')->pluck('id')->all());
    }

    public function testSoleAndFirstOrFailThrowLikeCollection() {
        $lazy = new CCollection_LazyCollection([1, 2, 2]);
        $this->assertSame(1, $lazy->sole(function ($v) {
            return $v === 1;
        }));
        try {
            $lazy->sole(function ($v) {
                return $v === 2;
            });
            $this->fail('harus melempar');
        } catch (CCollection_Exception_MultipleItemsFoundException $e) {
            $this->assertTrue(true);
        }
        $this->expectException(CCollection_Exception_ItemNotFoundException::class);
        $lazy->firstOrFail(function ($v) {
            return $v === 9;
        });
    }

    public function testTapEachOnlyRunsForEnumeratedItems() {
        $c = $this->counting($pulled);
        $tapped = [];
        $result = $c->tapEach(function ($v) use (&$tapped) {
            $tapped[] = $v;
        });
        $this->assertSame([], $tapped, 'tapEach malas');
        $this->assertSame([1, 2], $result->take(2)->all());
        $this->assertSame([1, 2], $tapped);
    }

    public function testTakeUntilTimeoutStopsWhenTimePasses() {
        $c = new CCollection_LazyCollection(function () {
            for ($i = 1; ; $i++) {
                yield $i;
            }
        });
        $result = $c->takeUntilTimeout(CCarbon::now()->subSecond());
        $this->assertSame([], $result->all(), 'batas waktu sudah lewat → tidak ada item');
    }

    public function testRangeAndTimesAreLazyAndInfiniteRangeCanBeTaken() {
        $this->assertSame([1, 2, 3], CCollection_LazyCollection::range(1, 3)->all());
        $this->assertSame([1, 2, 3], CCollection_LazyCollection::range(1, INF)->take(3)->all(), 'range tak berhingga boleh, asal dipotong');
        $this->assertSame([2, 4], CCollection_LazyCollection::times(2, function ($n) {
            return $n * 2;
        })->all());
    }

    public function testToBaseCollectAndLazyBridge() {
        $lazy = new CCollection_LazyCollection([1, 2]);
        $this->assertInstanceOf(CCollection::class, $lazy->collect());
        $this->assertSame([1, 2], $lazy->collect()->all());
        $this->assertInstanceOf(CCollection_LazyCollection::class, (new CCollection([1, 2]))->lazy());
        $this->assertSame('[1,2]', $lazy->toJson());
    }
}
