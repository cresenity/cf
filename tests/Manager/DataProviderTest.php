<?php

use PHPUnit\Framework\TestCase;

/**
 * Data provider (CManager_DataProvider_*) di luar basis data: Collection (pencarian OR/AND,
 * urutan, paginasi, agregat), Closure (parameter yang diteruskan, array vs paginator), dan
 * CManager_DataProviderParameter.
 */
class DataProviderTest extends TestCase {
    protected function setUp(): void {
        CPagination_Paginator::currentPageResolver(function () {
            return 1;
        });
    }

    /**
     * @return array
     */
    protected function rows() {
        return [
            ['id' => 1, 'name' => 'Merah', 'group' => 'warna', 'qty' => 5],
            ['id' => 2, 'name' => 'Hijau', 'group' => 'warna', 'qty' => 2],
            ['id' => 3, 'name' => 'Biru', 'group' => 'warna', 'qty' => 9],
            ['id' => 4, 'name' => 'Meja', 'group' => 'benda', 'qty' => 1],
            ['id' => 5, 'name' => 'Kursi', 'group' => 'benda', 'qty' => 7],
        ];
    }

    public function testCollectionProviderPaginatesWithTheRealTotal() {
        $provider = new CManager_DataProvider_CollectionDataProvider($this->rows());

        $page = $provider->paginate(2, ['*'], 'page', 2);
        $this->assertInstanceOf(CPagination_LengthAwarePaginator::class, $page);
        $this->assertSame(5, $page->total());
        $this->assertSame(2, $page->currentPage());
        $this->assertSame(3, $page->lastPage());
        $this->assertSame(['Biru', 'Meja'], array_column($page->items(), 'name'));
        $this->assertSame(5, $provider->toEnumerable()->count());
    }

    public function testCollectionProviderSearchOrIsCaseInsensitiveContains() {
        $provider = new CManager_DataProvider_CollectionDataProvider($this->rows());
        $provider->searchOr(['name' => 'me', 'group' => 'benda']);

        $page = $provider->paginate(10, ['*'], 'page', 1);
        $names = array_column($page->items(), 'name');
        sort($names);
        $this->assertSame(['Kursi', 'Meja', 'Merah'], $names, 'Merah/Meja lewat name, Kursi lewat group');
        $this->assertSame(3, $page->total(), 'total mengikuti hasil filter');
    }

    public function testCollectionProviderSearchAndRequiresEveryField() {
        $provider = new CManager_DataProvider_CollectionDataProvider($this->rows());
        $provider->searchAnd(['name' => 'me', 'group' => 'warna']);

        $this->assertSame(['Merah'], array_column($provider->paginate(10, ['*'], 'page', 1)->items(), 'name'));
    }

    public function testCollectionProviderSortsBeforePaging() {
        $provider = new CManager_DataProvider_CollectionDataProvider($this->rows());
        $provider->sort(['qty' => 'desc']);

        $this->assertSame(['Biru', 'Kursi'], array_column($provider->paginate(2, ['*'], 'page', 1)->items(), 'name'));

        $asc = new CManager_DataProvider_CollectionDataProvider($this->rows());
        $asc->sort(['name' => 'asc']);
        $this->assertSame(['Biru', 'Hijau', 'Kursi', 'Meja', 'Merah'], array_column($asc->paginate(10, ['*'], 'page', 1)->items(), 'name'));
    }

    public function testCollectionProviderAggregates() {
        $provider = new CManager_DataProvider_CollectionDataProvider($this->rows());

        $this->assertSame(5, $provider->aggregate('count', 'id'));
        $this->assertSame(24, $provider->aggregate('sum', 'qty'));
        $this->assertSame(9, $provider->aggregate('max', 'qty'));
        $this->assertSame(1, $provider->aggregate('min', 'qty'));
        $this->assertEquals(4.8, $provider->aggregate('avg', 'qty'));
        $this->expectException(Exception::class);
        $provider->aggregate('median', 'qty');
    }

    public function testClosureProviderPassesSearchSortAndPagingThroughTheParameter() {
        $seen = null;
        $provider = new CManager_DataProvider_ClosureDataProvider(function (CManager_DataProviderParameter $parameter) use (&$seen) {
            $seen = $parameter;

            return [['id' => 1], ['id' => 2], ['id' => 3]];
        });
        $provider->searchOr(['name' => 'x']);
        $provider->searchAnd(['group' => 'y']);
        $provider->sort(['id' => 'desc']);

        $page = $provider->paginate(2, ['*'], 'page', 3);

        $this->assertInstanceOf(CManager_DataProviderParameter::class, $seen);
        $this->assertSame(['name' => 'x'], $seen->getSearchOrData());
        $this->assertSame(['group' => 'y'], $seen->getSearchAndData());
        $this->assertSame(['id' => 'desc'], $seen->getSortData());
        $this->assertSame(3, $seen->getPage());
        $this->assertSame(2, $seen->getPerPage());
        $this->assertSame(3, $page->total(), 'array polos: total = count(array) — closure yang tidak memotong sendiri melaporkan semua barisnya');
        $this->assertSame(3, $page->currentPage());
    }

    public function testClosureProviderReturnsTheClosurePaginatorAsIs() {
        $provider = new CManager_DataProvider_ClosureDataProvider(function (CManager_DataProviderParameter $parameter) {
            return c::paginator([['id' => 9]], 42, $parameter->getPerPage(), $parameter->getPage());
        });

        $page = $provider->paginate(5, ['*'], 'page', 2);

        $this->assertSame(42, $page->total(), 'paginator dari closure dipakai apa adanya (total nyata dari sumber)');
        $this->assertSame(2, $page->currentPage());
        $this->assertSame([['id' => 9]], $page->items());
    }

    public function testClosureProviderToEnumerableAndSerialization() {
        $provider = new CManager_DataProvider_ClosureDataProvider(function () {
            return [['id' => 1], ['id' => 2]];
        });

        $this->assertSame(2, $provider->toEnumerable()->count());
        $copy = unserialize(serialize($provider));
        $this->assertInstanceOf(CManager_DataProvider_ClosureDataProvider::class, $copy);
        $this->assertSame(2, $copy->toEnumerable()->count(), 'closure terserialisasi ikut (dipakai lewat berkas ajax method)');
        $this->assertSame('default', $provider->getConnection());
    }

    public function testParameterDefaultsAndPaginationSetter() {
        $parameter = new CManager_DataProviderParameter();

        $this->assertSame([], $parameter->getSearchAndData());
        $this->assertSame([], $parameter->getSearchOrData());
        $this->assertSame([], $parameter->getSortData());
        $this->assertSame(-1, $parameter->getPage(), '-1 = belum dipaginasi');
        $this->assertSame(-1, $parameter->getPerPage(), '-1 = tanpa paginasi (semua baris)');
        $this->assertSame($parameter, $parameter->setForPagination(4, 25));
        $this->assertSame(4, $parameter->getPage());
        $this->assertSame(25, $parameter->getPerPage());
    }

    public function testSearchValueMayBeACallable() {
        $provider = new CManager_DataProvider_CollectionDataProvider($this->rows());
        $provider->searchOr(['name' => function () {
            return 'kursi';
        }]);

        $this->assertSame(['Kursi'], array_column($provider->paginate(10, ['*'], 'page', 1)->items(), 'name'));
    }
}
