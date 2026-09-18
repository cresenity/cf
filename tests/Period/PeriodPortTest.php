<?php

use PHPUnit\Framework\TestCase;

/**
 * Port terpilih suite spatie/period yang belum ada di PeriodTest: presisi jam/menit/bulan pada
 * iterasi/length/ceilingEnd, boundaries eksklusif, overlapAny/diffSymmetric, perbandingan tepi,
 * CPeriod_Collection (boundaries, gaps, overlapAll, intersect, subtract, unique, sort, map/filter/
 * reduce), Precision (round/ceil/higherThan/fromString), Boundaries::fromString, dan Duration.
 */
class PeriodPortTest extends TestCase {
    public function testMakeAcceptsDateStringsWithOrWithoutTimeAndProducesCarbonDates() {
        $period = CPeriod::make('2026-01-01', '2026-01-03');

        $this->assertInstanceOf(CCarbon::class, $period->start(), 'tanggal dari factory dinormalkan ke CCarbon (dulu DateTimeImmutable → length() fatal)');
        $this->assertEquals(3, $period->length());
        $this->assertEquals(2, CPeriod::make('2026-01-01 08:00:00', '2026-01-01 09:00:00', CPeriod_Precision::HOUR())->length());
        $this->assertEquals(3, CPeriod::make('01/01/2026', '03/01/2026', null, null, 'd/m/Y')->length(), 'format kustom');
        $this->expectException(CPeriod_Exception_InvalidDateException::class);
        CPeriod::make('2026-01-01 08:00', '2026-01-01 09:00', CPeriod_Precision::HOUR());
    }

    public function testHourPrecisionIteratesPerHourAndCountsLength() {
        $period = CPeriod::make('2026-01-01 08:00:00', '2026-01-01 11:00:00', CPeriod_Precision::HOUR());

        $this->assertEquals(4, $period->length(), 'length() bertipe float untuk presisi tetap');
        $dates = iterator_to_array($period);
        $this->assertCount(4, $dates);
        $this->assertSame('2026-01-01 09:00', $dates[1]->format('Y-m-d H:i'));
        $this->assertSame('2026-01-01 11:59:59', $period->ceilingEnd()->format('Y-m-d H:i:s'), 'ceilingEnd = detik terakhir jam terakhir');
        $this->assertTrue($period->precision()->equals(CPeriod_Precision::HOUR()));
    }

    public function testMonthPrecisionRoundsDatesDown() {
        $period = CPeriod::make('2026-01-15', '2026-03-20', CPeriod_Precision::MONTH());

        $this->assertSame('2026-01-01', $period->start()->format('Y-m-d'), 'tanggal dibulatkan ke presisi');
        $this->assertSame('2026-03-01', $period->end()->format('Y-m-d'));
        $this->assertSame(3, $period->length());
        $this->assertSame(3, $period->length(), 'length() berulang tidak menggeser includedEnd');
        $this->assertSame(['2026-01', '2026-02', '2026-03'], array_map(function ($date) {
            return $date->format('Y-m');
        }, iterator_to_array($period)));
    }

    public function testExclusiveBoundariesShiftIncludedStartAndEnd() {
        $period = CPeriod::make('2026-01-01', '2026-01-10', CPeriod_Precision::DAY(), CPeriod_Boundaries::EXCLUDE_ALL());

        $this->assertTrue((bool) $period->isStartExcluded());
        $this->assertTrue((bool) $period->isEndExcluded());
        $this->assertFalse((bool) $period->isStartIncluded());
        $this->assertSame('2026-01-02', $period->includedStart()->format('Y-m-d'));
        $this->assertSame('2026-01-09', $period->includedEnd()->format('Y-m-d'));
        $this->assertEquals(8, $period->length());
        $this->assertFalse($period->contains(CCarbon::parse('2026-01-01')));
        $this->assertTrue($period->contains(CCarbon::parse('2026-01-02')));
        $this->assertSame('(2026-01-02,2026-01-09)', $period->asString(), 'asString memakai tanggal yang termasuk, kurung bulat = eksklusif, tanpa spasi');
        $this->assertSame('[2026-01-01,2026-01-10]', CPeriod::make('2026-01-01', '2026-01-10')->asString());
    }

    public function testBoundariesFromStringAndPrecisionHelpers() {
        $this->assertTrue((bool) CPeriod_Boundaries::fromString('(', ']')->startExcluded());
        $this->assertTrue((bool) CPeriod_Boundaries::fromString('(', ']')->endIncluded());
        $this->assertTrue((bool) CPeriod_Boundaries::fromString('[', ')')->endExcluded());
        $this->assertNull(CPeriod_Boundaries::fromString('{', '}'), 'tanda tak dikenal → null');
        $this->assertTrue(CPeriod_Precision::fromString('2026-01-01 08:30')->equals(CPeriod_Precision::MINUTE()), 'fromString membaca contoh tanggal, bukan pola format');
        $this->assertTrue(CPeriod_Precision::fromString('2026-01')->equals(CPeriod_Precision::MONTH()));
        $this->assertTrue(CPeriod_Precision::fromString('2026-01-01 08:30:15')->equals(CPeriod_Precision::SECOND()));
        $this->assertTrue(CPeriod_Precision::HOUR()->higherThan(CPeriod_Precision::DAY()), 'higherThan = lebih halus (format tanggal lebih panjang)');
        $this->assertFalse(CPeriod_Precision::DAY()->higherThan(CPeriod_Precision::HOUR()));
        $this->assertSame('2026-01-15 00:00:00', CPeriod_Precision::DAY()->roundDate(CCarbon::parse('2026-01-15 13:45:12'))->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-15 23:59:59', CPeriod_Precision::DAY()->ceilDate(CCarbon::parse('2026-01-15 13:45:12'), CPeriod_Precision::DAY())->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-31 23:59:59', CPeriod_Precision::DAY()->ceilDate(CCarbon::parse('2026-01-15 13:45:12'), CPeriod_Precision::MONTH())->format('Y-m-d H:i:s'), 'ceil ke akhir bulan');
        $this->assertSame('2026-01-16 00:00:00', CPeriod_Precision::DAY()->increment(new DateTimeImmutable('2026-01-15'))->format('Y-m-d H:i:s'), 'increment/decrement menuntut DateTimeImmutable');
        $this->assertSame('2026-01-14 00:00:00', CPeriod_Precision::DAY()->decrement(new DateTimeImmutable('2026-01-15'))->format('Y-m-d H:i:s'));
        $this->assertSame('d', CPeriod_Precision::DAY()->intervalName(), 'intervalName = nama unit tunggal, bukan spesifikasi ISO');
        $this->assertSame('Y-m-d', CPeriod_Precision::DAY()->dateFormat());
    }

    public function testMixedPrecisionOperationsThrow() {
        $this->expectException(CPeriod_Exception_CannotComparePeriodException::class);

        CPeriod::make('2026-01-01', '2026-01-10')->overlapsWith(CPeriod::make('2026-01-01 00:00:00', '2026-01-10 00:00:00', CPeriod_Precision::HOUR()));
    }

    public function testEdgeComparisons() {
        $a = CPeriod::make('2026-01-01', '2026-01-10');
        $b = CPeriod::make('2026-01-01', '2026-01-20');

        $this->assertTrue($a->startsAt(CCarbon::parse('2026-01-01')));
        $this->assertTrue($a->startsBeforeOrAt(CCarbon::parse('2026-01-01')));
        $this->assertFalse($a->startsBefore(CCarbon::parse('2026-01-01')));
        $this->assertTrue($a->startsAfterOrAt(CCarbon::parse('2026-01-01')));
        $this->assertTrue($a->endsAt(CCarbon::parse('2026-01-10')));
        $this->assertTrue($a->endsBeforeOrAt(CCarbon::parse('2026-01-10')));
        $this->assertTrue($a->endsBefore($b->end()));
        $this->assertTrue($b->endsAfterOrAt($a->end()));
        $this->assertTrue($a->startsBefore(CCarbon::parse('2026-01-02')));
    }

    public function testOverlapAnyAndDiffSymmetric() {
        $a = CPeriod::make('2026-01-01', '2026-01-10');
        $b = CPeriod::make('2026-01-08', '2026-01-15');
        $c = CPeriod::make('2026-02-01', '2026-02-05');

        $overlaps = $a->overlapAny($b, $c);
        $this->assertInstanceOf(CPeriod_Collection::class, $overlaps);
        $this->assertCount(1, $overlaps, 'hanya irisan yang ada yang dikumpulkan');
        $this->assertSame('[2026-01-08,2026-01-10]', $overlaps[0]->asString());
        $this->assertTrue($a->overlapAny($c)->isEmpty());

        $diff = $a->diffSymmetric($b);
        $this->assertCount(2, $diff);
        $this->assertSame('[2026-01-01,2026-01-07]', $diff[0]->asString());
        $this->assertSame('[2026-01-11,2026-01-15]', $diff[1]->asString());

        $disjoint = $a->diffSymmetric($c);
        $this->assertCount(2, $disjoint, 'tanpa irisan: kedua periode dikembalikan utuh');
    }

    public function testCollectionBoundariesGapsAndOverlapAll() {
        $collection = CPeriod_Collection::make(
            CPeriod::make('2026-01-01', '2026-01-05'),
            CPeriod::make('2026-01-10', '2026-01-15'),
            CPeriod::make('2026-01-20', '2026-01-25')
        );

        $this->assertCount(3, $collection);
        $this->assertSame('[2026-01-01,2026-01-25]', $collection->boundaries()->asString());
        $gaps = $collection->gaps();
        $this->assertCount(2, $gaps);
        $this->assertSame('[2026-01-06,2026-01-09]', $gaps[0]->asString());
        $this->assertSame('[2026-01-16,2026-01-19]', $gaps[1]->asString());

        //overlapAll() mengiris koleksi ini dengan koleksi LAIN (bukan antar anggotanya sendiri)
        $others = CPeriod_Collection::make(CPeriod::make('2026-01-03', '2026-01-12'));
        $overlaps = $collection->overlapAll($others);
        $this->assertCount(2, $overlaps);
        $this->assertSame('[2026-01-03,2026-01-05]', $overlaps[0]->asString());
        $this->assertSame('[2026-01-10,2026-01-12]', $overlaps[1]->asString());
        $this->assertTrue($collection->overlapAll(CPeriod_Collection::make(CPeriod::make('2026-03-01', '2026-03-02')))->isEmpty());
        $this->assertNull(CPeriod_Collection::make()->boundaries(), 'koleksi kosong tanpa batas');
    }

    public function testCollectionIntersectSubtractUniqueAndSort() {
        $collection = CPeriod_Collection::make(CPeriod::make('2026-01-01', '2026-01-10'), CPeriod::make('2026-01-20', '2026-01-31'));

        $intersect = $collection->intersect(CPeriod::make('2026-01-05', '2026-01-25'));
        $this->assertCount(2, $intersect);
        $this->assertSame('[2026-01-05,2026-01-10]', $intersect[0]->asString());
        $this->assertSame('[2026-01-20,2026-01-25]', $intersect[1]->asString());

        $subtract = $collection->subtract(CPeriod::make('2026-01-05', '2026-01-25'));
        $this->assertCount(2, $subtract);
        $this->assertSame('[2026-01-01,2026-01-04]', $subtract[0]->asString());
        $this->assertSame('[2026-01-26,2026-01-31]', $subtract[1]->asString());

        $duplicates = CPeriod_Collection::make(CPeriod::make('2026-02-01', '2026-02-05'), CPeriod::make('2026-01-01', '2026-01-05'), CPeriod::make('2026-01-01', '2026-01-05'));
        $unique = $duplicates->unique();
        $this->assertCount(2, $unique);
        $sorted = $unique->sort();
        $this->assertSame('[2026-01-01,2026-01-05]', $sorted[0]->asString());
        $this->assertSame('[2026-02-01,2026-02-05]', $sorted[1]->asString());
    }

    public function testCollectionMapFilterReduceAndAdd() {
        $collection = CPeriod_Collection::make(CPeriod::make('2026-01-01', '2026-01-03'), CPeriod::make('2026-01-10', '2026-01-14'));

        $mapped = $collection->map(function (CPeriod $period) {
            return (int) $period->length();
        });
        $this->assertInstanceOf(CPeriod_Collection::class, $mapped, 'map() mengembalikan koleksi (isinya boleh bukan periode)');
        $this->assertSame([3, 5], [$mapped[0], $mapped[1]]);
        $this->assertSame(8, $collection->reduce(function ($carry, CPeriod $period) {
            return $carry + $period->length();
        }, 0));
        $this->assertCount(1, $collection->filter(function (CPeriod $period) {
            return $period->length() > 3;
        }));
        $this->assertFalse($collection->isEmpty());
        $this->assertTrue(CPeriod_Collection::make()->isEmpty());
        $added = $collection->add(CPeriod::make('2026-02-01', '2026-02-02'));
        $this->assertCount(3, $added);
        $this->assertCount(2, $collection, 'add() mengembalikan koleksi baru');
    }

    public function testDurationComparisons() {
        $short = CPeriod::make('2026-01-01', '2026-01-03')->duration();
        $long = CPeriod::make('2026-02-01', '2026-02-10')->duration();
        $same = CPeriod::make('2026-03-01', '2026-03-03')->duration();

        $this->assertTrue($short->isSmallerThan($long));
        $this->assertTrue($long->isLargerThan($short));
        $this->assertTrue($short->equals($same));
        $this->assertSame(-1, $short->compareTo($long));
        $this->assertSame(1, $long->compareTo($short));
        $this->assertSame(0, $short->compareTo($same));
    }

    public function testFromStringParsesBoundariesAndPrecision() {
        $period = CPeriod::fromString('(2026-01-01, 2026-01-10]');

        $this->assertTrue((bool) $period->isStartExcluded());
        $this->assertTrue((bool) $period->isEndIncluded());
        $this->assertSame('2026-01-02', $period->includedStart()->format('Y-m-d'));
        $this->assertEquals(9, $period->length());
        $timed = CPeriod::fromString('[2026-01-01 08:00:00, 2026-01-01 10:00:00]');
        $this->assertTrue($timed->precision()->equals(CPeriod_Precision::SECOND()), 'presisi mengikuti komponen terhalus yang ditulis');
        $this->assertEquals(7201, $timed->length());
    }
}
