<?php
use PHPUnit\Framework\TestCase;

/**
 * Ajax round-trip coverage for `CElement_FormInput_SelectSearch` when
 * backed by `setDataFromModel()`, run against a real sqlite in-memory
 * database (same rationale as tests/Model/NestedSetTest.php).
 *
 * This sandbox's default `php` binary has no pdo_sqlite extension, so this
 * file must be run with:
 *   php7.4 vendor/bin/phpcf test --filter=SelectSearchModelRoundTripTest
 *
 * Exercises the exact path devcloud's SelectSearch controls (see
 * application/devcloud/default/libraries/DFormInput/SelectSearch/*) go
 * through in production: build() -> setDataFromModel() -> html()/js() ->
 * createAjaxUrl() (real serialize() into a CTemporary temp file) ->
 * CAjax::createMethod() -> CAjax_Method::createEngine() ->
 * CAjax_Engine_SelectSearch -> CAjax_Engine_SelectSearch_Processor_DataProvider
 * (picked because the unserialized dataProvider implements
 * CManager_Contract_DataProviderInterface) -> real DB query -> JSONP
 * response. This is the round trip that silently broke in production when
 * ModelDataProvider's queryCallback was lost during unserialize (see
 * tests/Manager/ModelDataProviderTest.php for the isolated version of that
 * regression).
 */
class SelectSearchModelRoundTripTest extends TestCase {
    /** @var CDatabase_Connection_Pdo_SqliteConnection */
    protected $connection;

    protected function setUp(): void {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped(
                'pdo_sqlite is not available for the active PHP CLI - run under a PHP binary that has it '
                . '(e.g. `php7.4 vendor/bin/phpcf test --filter=SelectSearchModelRoundTripTest`) to actually execute this suite.'
            );
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->connection = new CDatabase_Connection_Pdo_SqliteConnection($pdo, '', '', ['driver' => 'sqlite']);
        $this->connection->setEventDispatcher(new CEvent_Dispatcher());

        SelectSearchModelRoundTripTest_Item::$conn = $this->connection;

        $schema = $this->connection->getSchemaBuilder();
        $schema->create('ssmrt_items', function (CDatabase_Schema_Blueprint $table) {
            $table->increments('ssmrt_item_id');
            $table->string('name');
            $table->unsignedInteger('team_id');
            $table->unsignedInteger('status')->default(1);
        });

        SelectSearchModelRoundTripTest_Item::create(['name' => 'Alpha', 'team_id' => 1]);
        SelectSearchModelRoundTripTest_Item::create(['name' => 'Beta', 'team_id' => 1]);
        SelectSearchModelRoundTripTest_Item::create(['name' => 'Gamma', 'team_id' => 2]);
    }

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

    public function testQueryCallbackScopingSurvivesTheAjaxRoundTrip() {
        $teamId = 1;

        $instance = new CElement_FormInput_SelectSearch('sel_' . uniqid());
        $instance->setDataFromModel(SelectSearchModelRoundTripTest_Item::class, function (CModel_Query $query) use ($teamId) {
            $query->where('team_id', $teamId);
        });
        $instance->setKeyField('ssmrt_item_id');
        $instance->setSearchField(['name']);

        $result = $this->executeSelectSearchAjax($instance);

        $this->assertSame(2, $result['total']);
        $names = array_column($result['data'], 'name');
        sort($names);
        $this->assertSame(['Alpha', 'Beta'], $names);
    }

    public function testSearchFieldFiltersResultsThroughTheRealDatabase() {
        $instance = new CElement_FormInput_SelectSearch('sel_' . uniqid());
        $instance->setDataFromModel(SelectSearchModelRoundTripTest_Item::class);
        $instance->setKeyField('ssmrt_item_id');
        $instance->setSearchField(['name']);

        $result = $this->executeSelectSearchAjax($instance, ['q' => 'gam']);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Gamma', $result['data'][0]['name']);
    }

    public function testKeyFieldIsMappedToId() {
        $instance = new CElement_FormInput_SelectSearch('sel_' . uniqid());
        $instance->setDataFromModel(SelectSearchModelRoundTripTest_Item::class, function (CModel_Query $query) {
            $query->where('name', 'Alpha');
        });
        $instance->setKeyField('ssmrt_item_id');
        $instance->setSearchField(['name']);

        $result = $this->executeSelectSearchAjax($instance);

        $this->assertSame(1, $result['total']);
        $this->assertSame($result['data'][0]['ssmrt_item_id'], $result['data'][0]['id']);
    }
}

class SelectSearchModelRoundTripTest_Item extends CModel {
    public static $conn;

    protected $table = 'ssmrt_items';

    protected $primaryKey = 'ssmrt_item_id';

    protected $guarded = [];

    public $timestamps = false;

    public static function resolveConnection($connection = null) {
        return static::$conn;
    }
}
