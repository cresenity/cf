<?php

require_once __DIR__ . '/../Model/Integration/UjiModelSupport.php';

class UjiLog_Model extends UjiModel_Base {
    protected $table = 'log_activity';
}

class UjiLog_Tracked extends UjiModel_Base {
    use CModel_Activity_ActivityTrait;

    protected $table = 'uji_user';
}

class UjiLogActivity {
    use CApp_Trait_LogActivity;
}

/**
 * Siklus <Prefix>LogActivity::start()/stop() menulis satu baris log_activity berisi perubahan model
 * (create/update/delete lewat CModel_Activity_Observer) sebagai JSON; di luar route (CLI) kolom
 * nav/controller/action kosong, bukan galat.
 */
class AppLogActivityIntegrationTest extends UjiModel_IntegrationTestCase {
    /**
     * @var mixed
     */
    protected $originalModelConfig;

    protected function setUp(): void {
        parent::setUp();
        $this->originalModelConfig = CConfig::repository()->get('app.model.log_activity');
        CConfig::repository()->set('app.model.log_activity', UjiLog_Model::class);
    }

    protected function tearDown(): void {
        CConfig::repository()->set('app.model.log_activity', $this->originalModelConfig);
        if (CModel_Activity::instance()->isStarted()) {
            CModel_Activity::instance()->cancel();
        }
        parent::tearDown();
    }

    protected function createSchema() {
        parent::createSchema();
        $this->connection->statement('create table log_activity (log_activity_id integer primary key autoincrement, org_id integer null, app_id integer null, user_id integer null, session_id varchar(191) null, remote_addr varchar(45) null, user_agent varchar(255) null, platform_version varchar(50) null, platform varchar(50) null, browser_version varchar(50) null, browser varchar(50) null, uri varchar(500) null, routed_uri varchar(500) null, controller varchar(191) null, method varchar(191) null, query_string varchar(500) null, nav varchar(191) null, nav_label varchar(191) null, action varchar(191) null, action_label varchar(191) null, createdby varchar(100) null, description text null, data text null, activity_date datetime null, created datetime null, updated datetime null, status integer not null default 1, deleted datetime null, deletedby varchar(100) null, extra_note varchar(100) null)');
    }

    public function testStartStopWritesOneRowWithTheCollectedChanges() {
        UjiLogActivity::start('Create User');
        $user = UjiLog_Tracked::create(['name' => 'Budi']);
        $user->update(['name' => 'Budi S']);
        UjiLogActivity::stop();

        $rows = $this->table('log_activity')->get();
        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('Create User', $row->description);
        $this->assertNotNull($row->activity_date);
        $this->assertNotNull($row->created, 'kolom audit CModel terisi');
        $this->assertSame('', (string) $row->nav, 'tanpa route: nav kosong');
        $this->assertSame('', (string) $row->controller);

        $data = json_decode($row->data, true);
        $this->assertCount(2, $data, 'create + update');
        $this->assertSame('create', $data[0]['type']);
        $this->assertSame('uji_user', $data[0]['table']);
        $this->assertSame('Budi', $data[0]['after']['name']);
        $this->assertSame('update', $data[1]['type']);
        $this->assertSame('Budi', $data[1]['before']['name']);
        $this->assertSame('Budi S', $data[1]['after']['name']);
        $this->assertSame(['name' => 'Budi S'], carr::only($data[1]['changes'], ['name']));
    }

    public function testDatesInTheChangesAreSerializedAsStrings() {
        UjiLogActivity::start('Update User');
        $user = UjiLog_Tracked::create(['name' => 'a']);
        UjiLogActivity::stop();

        $data = json_decode($this->table('log_activity')->value('data'), true);
        $this->assertIsString($data[0]['after']['created'], 'CCarbon → string, bukan objek');
    }

    public function testChangesOutsideAStartedCycleAreNotRecorded() {
        UjiLog_Tracked::create(['name' => 'diam']);

        $this->assertSame(0, $this->table('log_activity')->count());
        $this->assertFalse(CModel_Activity::instance()->isStarted());
    }

    public function testCancelDiscardsTheCycleWithoutWriting() {
        UjiLogActivity::start('Batal');
        UjiLog_Tracked::create(['name' => 'x']);
        UjiLogActivity::cancel();

        $this->assertSame(0, $this->table('log_activity')->count());
        $this->assertFalse(CModel_Activity::instance()->isStarted());
    }

    public function testDeleteIsRecordedAsADeleteEntry() {
        $user = UjiLog_Tracked::create(['name' => 'hapus']);
        UjiLogActivity::start('Delete User');
        $user->delete();
        UjiLogActivity::stop();

        $data = json_decode($this->table('log_activity')->value('data'), true);
        $this->assertSame('delete', $data[0]['type']);
        $this->assertEquals($user->getKey(), $data[0]['key']);
    }

    public function testPopulateCanBeCalledDirectlyWithExtraColumns() {
        $model = CApp_Log_Activity::populate('Langsung', [['type' => 'custom', 'before' => null, 'after' => null]], ['extra_note' => 'tambahan', 'nav' => 'manual']);

        $this->assertInstanceOf(UjiLog_Model::class, $model);
        $this->assertTrue($model->exists);
        $this->assertSame('tambahan', $this->table('log_activity')->value('extra_note'), '$extra menambah kolom di luar set standar');
        $this->assertSame('', (string) $this->table('log_activity')->value('nav'), '$extra tidak menimpa kolom standar yang dihitung (array union, kiri menang)');
        $this->assertSame('Langsung', $this->table('log_activity')->value('description'));
        $this->assertSame('custom', json_decode($this->table('log_activity')->value('data'), true)[0]['type']);
    }
}
