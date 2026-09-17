<?php
use PHPUnit\Framework\TestCase;

/**
 * Ajax round-trip coverage for `CElement_FormInput_SelectSearch` when
 * backed by `setDataFromClosure()` (no database needed, unlike the
 * model-backed variant in SelectSearchModelRoundTripTest.php). Exercises
 * the full real pipeline: createAjaxUrl() -> CAjax_Method::makeUrl() writes
 * a temp file -> CAjax::createMethod() reads it back -> CAjax_Method::
 * createEngine() -> CAjax_Engine_SelectSearch picks
 * CAjax_Engine_SelectSearch_Processor_DataProvider because the unserialized
 * dataProvider implements CManager_Contract_DataProviderInterface.
 *
 * Note: CManager_DataProvider_ClosureDataProvider (used internally by
 * setDataFromClosure()) still wraps its closure in raw
 * `Opis\Closure\SerializableClosure` rather than `CFunction_SerializableClosure`
 * - see the "finish auditing remaining Opis\Closure\SerializableClosure
 * usages" TODO item. This suite doesn't reproduce the PHP reflection edge
 * case (it isn't reliably reproducible outside the specific PHP build it
 * was seen on), so it will keep passing right up until that migration
 * happens - it does not prove the closure path is safe from the same bug
 * class the model-backed path had.
 */
class SelectSearchClosureRoundTripTest extends TestCase {
    protected function executeSelectSearchAjax(CElement_FormInput_SelectSearch $instance, array $get = []) {
        $instance->html();
        $js = $instance->js();

        preg_match("#url: '([^']+)'#", $js, $m);
        $this->assertNotEmpty($m, 'ajax url not found in rendered js: ' . $js);

        $ajaxUrl = html_entity_decode($m[1]);
        $path = parse_url($ajaxUrl, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', $path)));
        $methodId = end($segments);

        $file = CAjax::temporaryFile($methodId);
        $json = CTemporary::disk()->get($file);

        $_GET = array_merge(['q' => '', 'page' => 1, 'limit' => 10], $get);

        $ajaxMethod = CAjax::createMethod($json)->setArgs([$methodId]);
        $engine = CAjax_Method::createEngine($ajaxMethod);
        $response = $engine->execute();

        return json_decode($response->getContent(), true);
    }

    public function testClosureBackedRoundTripReturnsData() {
        $instance = new CElement_FormInput_SelectSearch('sel_' . uniqid());
        $instance->setDataFromClosure(function () {
            return [
                ['id' => 1, 'name' => 'Merah'],
                ['id' => 2, 'name' => 'Hijau'],
                ['id' => 3, 'name' => 'Biru'],
            ];
        });
        $instance->setKeyField('id');
        $instance->setSearchField(['name']);

        $result = $this->executeSelectSearchAjax($instance);

        $this->assertSame(3, $result['total']);
        $names = array_column($result['data'], 'name');
        sort($names);
        $this->assertSame(['Biru', 'Hijau', 'Merah'], $names);
    }

    public function testClosureBackedRoundTripAppliesSearchTerm() {
        // Unlike ModelDataProvider, ClosureDataProvider does not auto-apply
        // search - it hands the term through via CManager_DataProviderParameter
        // and leaves filtering entirely to the closure.
        $instance = new CElement_FormInput_SelectSearch('sel_' . uniqid());
        $instance->setDataFromClosure(function (CManager_DataProviderParameter $parameter) {
            $rows = [
                ['id' => 1, 'name' => 'Merah'],
                ['id' => 2, 'name' => 'Hijau'],
                ['id' => 3, 'name' => 'Biru'],
            ];
            $searchOr = $parameter->getSearchOrData();
            if (empty($searchOr)) {
                return $rows;
            }
            $term = strtolower(reset($searchOr));

            return array_values(array_filter($rows, function ($row) use ($term) {
                return strpos(strtolower($row['name']), $term) !== false;
            }));
        });
        $instance->setKeyField('id');
        $instance->setSearchField(['name']);

        $result = $this->executeSelectSearchAjax($instance, ['q' => 'hij']);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Hijau', $result['data'][0]['name']);
    }

    public function testClosureBackedRoundTripIncludesPrependDataOnFirstPageOnly() {
        $instance = new CElement_FormInput_SelectSearch('sel_' . uniqid());
        $instance->setDataFromClosure(function () {
            return [
                ['id' => 1, 'name' => 'Merah'],
            ];
        });
        $instance->setKeyField('id');
        $instance->setSearchField(['name']);
        $instance->setPrependData([['id' => '', 'name' => 'ALL']]);

        $resultPage1 = $this->executeSelectSearchAjax($instance, ['page' => 1]);
        $names1 = array_column($resultPage1['data'], 'name');
        $this->assertContains('ALL', $names1);

        $resultPage2 = $this->executeSelectSearchAjax($instance, ['page' => 2]);
        $names2 = array_column($resultPage2['data'], 'name');
        $this->assertNotContains('ALL', $names2);
    }
}
