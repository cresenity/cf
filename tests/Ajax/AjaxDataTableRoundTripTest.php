<?php

use PHPUnit\Framework\TestCase;

/**
 * DataTable ajax dari setDataFromClosure() + setAjax(true): js elemen membuat method bertipe DataTable
 * (isDataProvider), engine menjawab protokol DataTables lama (sEcho/iTotalRecords/aaData) dengan
 * paginasi, pencarian, dan urutan dari parameter request; kolom callback dirender di server.
 */
class AjaxDataTableRoundTripTest extends TestCase {
    /**
     * @var string[]
     */
    protected $created = [];

    protected function setUp(): void {
        $_GET = [];
        $_POST = [];
        CPagination_Paginator::currentPageResolver(function () {
            return 1;
        });
    }

    protected function tearDown(): void {
        $disk = CTemporary::disk();
        foreach ($this->created as $file) {
            if ($disk->exists($file)) {
                $disk->delete($file);
            }
        }
        $_GET = [];
        $_POST = [];
    }

    /**
     * @return array
     */
    protected function rows() {
        return [
            ['id' => 1, 'name' => 'Merah', 'qty' => 5],
            ['id' => 2, 'name' => 'Hijau', 'qty' => 2],
            ['id' => 3, 'name' => 'Biru', 'qty' => 9],
            ['id' => 4, 'name' => 'Kuning', 'qty' => 1],
            ['id' => 5, 'name' => 'Ungu', 'qty' => 7],
        ];
    }

    /**
     * @return CElement_Component_DataTable
     */
    protected function table() {
        $table = new CElement_Component_DataTable('tbl_' . uniqid());
        $table->setDataFromClosure(function (CManager_DataProviderParameter $parameter) {
            $rows = c::collect($this->rows());
            foreach ($parameter->getSearchOrData() as $field => $keyword) {
                $rows = $rows->filter(function ($row) use ($field, $keyword) {
                    return stripos((string) carr::get($row, $field), $keyword) !== false;
                });
            }
            foreach ($parameter->getSortData() as $field => $direction) {
                $rows = strtolower($direction) === 'desc' ? $rows->sortByDesc($field) : $rows->sortBy($field);
            }
            $perPage = $parameter->getPerPage() ?: 10;
            $page = $parameter->getPage() ?: 1;

            return c::paginator($rows->values()->forPage($page, $perPage)->values()->all(), $rows->count(), $perPage, $page);
        });
        $table->setAjax(true);
        $table->addColumn('name')->setLabel('Nama');
        $table->addColumn('qty')->setLabel('Jumlah')->setCallback(function ($row, $value) {
            return $value . ' pcs';
        });

        return $table;
    }

    /**
     * @param CElement_Component_DataTable $table
     * @param array                        $get
     *
     * @return array
     */
    protected function request(CElement_Component_DataTable $table, array $get) {
        $table->html();
        $js = $table->js();
        $this->assertSame(1, preg_match('#cresenity/ajax/([0-9a-f]{40})#', $js, $m), 'url ajax tidak ditemukan');
        $id = $m[1];
        $file = CAjax::temporaryFile($id);
        $this->created[] = $file;
        $method = CAjax::createMethod(CTemporary::disk()->get($file))->setArgs([$id]);
        $this->assertSame('DataTable', $method->getType());
        $this->assertTrue((bool) $method->getData()['isDataProvider']);

        $_GET = array_merge(['sEcho' => 3, 'iDisplayStart' => 0, 'iDisplayLength' => 10], $get);
        $response = CAjax_Method::createEngine($method)->execute();
        $this->assertInstanceOf(CHTTP_JsonResponse::class, $response);

        return json_decode($response->getContent(), true);
    }

    public function testFirstPageReturnsAllRowsWithRenderedColumns() {
        $payload = $this->request($this->table(), []);

        $this->assertSame(['datatable', 'js'], array_keys($payload));
        $datatable = $payload['datatable'];
        $this->assertSame(3, $datatable['sEcho'], 'sEcho dipantulkan');
        $this->assertSame(5, $datatable['iTotalRecords']);
        $this->assertSame(5, $datatable['iTotalDisplayRecords']);
        $this->assertCount(5, $datatable['aaData']);
        $this->assertContains('Merah', $datatable['aaData'][0]);
        $this->assertContains('5 pcs', $datatable['aaData'][0], 'kolom callback dirender di server');
    }

    public function testPaginationFollowsDisplayStartAndLength() {
        $payload = $this->request($this->table(), ['iDisplayStart' => 2, 'iDisplayLength' => 2]);

        $datatable = $payload['datatable'];
        $this->assertSame(5, $datatable['iTotalRecords']);
        $this->assertCount(2, $datatable['aaData']);
        $this->assertContains('Biru', $datatable['aaData'][0]);
        $this->assertContains('Kuning', $datatable['aaData'][1]);
    }

    public function testShowAllUsesMinusOne() {
        $payload = $this->request($this->table(), ['iDisplayLength' => -1]);

        $this->assertCount(5, $payload['datatable']['aaData']);
    }

    public function testSearchIsForwardedToTheProviderForSearchableColumns() {
        $payload = $this->request($this->table(), ['sSearch' => 'ng', 'bSearchable_0' => 'true', 'bSearchable_1' => 'false']);

        $names = array_map(function ($row) {
            return $row[0];
        }, $payload['datatable']['aaData']);
        sort($names);
        $this->assertSame(['Kuning', 'Ungu'], $names, 'hanya kolom name (bSearchable_0) yang dicari');
        $this->assertSame(2, $payload['datatable']['iTotalRecords']);

        $none = $this->request($this->table(), ['sSearch' => 'ng', 'bSearchable_0' => 'false', 'bSearchable_1' => 'false']);
        $this->assertSame(5, $none['datatable']['iTotalRecords'], 'tanpa kolom searchable, kata kunci diabaikan');
    }

    public function testSortParametersReachTheProvider() {
        $payload = $this->request($this->table(), ['iSortCol_0' => 1, 'sSortDir_0' => 'desc', 'iSortingCols' => 1, 'bSortable_1' => 'true']);

        $names = array_map(function ($row) {
            return $row[0];
        }, $payload['datatable']['aaData']);
        $this->assertSame(['Biru', 'Ungu', 'Merah', 'Hijau', 'Kuning'], $names, 'qty menurun');

        $asc = $this->request($this->table(), ['iSortCol_0' => 0, 'sSortDir_0' => 'asc', 'iSortingCols' => 1]);
        $this->assertSame('Biru', $asc['datatable']['aaData'][0][0], 'bSortable_N yang tidak dikirim dianggap true');
    }

    public function testRenderedJsContainsTheAjaxUrlAndServerSideFlags() {
        $table = $this->table();
        $table->html();
        $js = $table->js();

        $this->assertStringContainsString('cresenity/ajax/', $js);
        $this->assertStringContainsString('serverSide', $js);
    }
}
