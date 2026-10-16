<?php
use PHPUnit\Framework\TestCase;

/**
 * CDatabase_Platform_Sqlite + CDatabase_Schema_Manager_Sqlite: introspeksi (PRAGMA-based) dan
 * rebuild DDL (change()/renameColumn()/dropColumn() lewat Schema Builder sungguhan) - #-21866.
 */
class DatabaseSqliteSchemaManagerTest extends TestCase {
    /**
     * @var CDatabase_Manager
     */
    protected $manager;

    /**
     * @var string
     */
    protected $previousDefaultConnection;

    protected function setUp(): void {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('butuh ekstensi pdo_sqlite');
        }
        $this->manager = CDatabase_Manager::instance();
        $this->previousDefaultConnection = $this->manager->getDefaultConnection();
        $this->manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'uji_sqlite_schema');
        $this->manager->setDefaultConnection('uji_sqlite_schema');
    }

    protected function tearDown(): void {
        $this->manager->setDefaultConnection($this->previousDefaultConnection);
        $this->manager->purge('uji_sqlite_schema');
    }

    /**
     * @return CDatabase_Connection
     */
    protected function connection() {
        return $this->manager->connection('uji_sqlite_schema');
    }

    public function testGetSchemaManagerAndPlatformAreTheNativeSqliteClasses() {
        $conn = $this->connection();

        $this->assertInstanceOf(CDatabase_Schema_Manager_Sqlite::class, $conn->getSchemaManager());
        $this->assertInstanceOf(CDatabase_Platform_Sqlite::class, $conn->getDatabasePlatform());
    }

    public function testListTableColumnsReadsTypesLengthDefaultAndSingleColumnAutoincrement() {
        $conn = $this->connection();
        $conn->statement('create table uji_people (id integer primary key autoincrement, name varchar(100) not null, age integer null, balance decimal(10,2) null default 0)');

        $columns = $conn->getSchemaManager()->listTableColumns('uji_people');

        $this->assertSame(['id', 'name', 'age', 'balance'], array_keys($columns));
        $this->assertSame(CDatabase_Type::INTEGER, $columns['id']->getType()->getName());
        $this->assertTrue($columns['id']->getAutoincrement(), 'kolom INTEGER PRIMARY KEY tunggal berperilaku sebagai rowid alias');
        $this->assertSame(CDatabase_Type::STRING, $columns['name']->getType()->getName());
        $this->assertSame(100, $columns['name']->getLength());
        $this->assertTrue($columns['name']->getNotnull());
        $this->assertFalse($columns['age']->getNotnull());
        $this->assertSame(CDatabase_Type::DECIMAL, $columns['balance']->getType()->getName());
        $this->assertSame('0', $columns['balance']->getDefault());
    }

    public function testCompositePrimaryKeyColumnsAreNotMarkedAutoincrement() {
        $conn = $this->connection();
        $conn->statement('create table uji_composite (a integer not null, b integer not null, primary key (a, b))');

        $columns = $conn->getSchemaManager()->listTableColumns('uji_composite');

        $this->assertFalse($columns['a']->getAutoincrement());
        $this->assertFalse($columns['b']->getAutoincrement());

        $indexes = $conn->getSchemaManager()->listTableIndexes('uji_composite');
        $this->assertTrue($indexes['primary']->isPrimary());
        $this->assertSame(['a', 'b'], $indexes['primary']->getColumns());
    }

    public function testListTableIndexesExcludesImplicitAutoindexesButKeepsExplicitOnes() {
        $conn = $this->connection();
        $conn->statement('create table uji_idx (id integer primary key autoincrement, email varchar(255) not null unique, name varchar(100) null)');
        $conn->statement('create index idx_uji_idx_name on uji_idx (name)');

        $indexes = $conn->getSchemaManager()->listTableIndexes('uji_idx');

        $this->assertArrayHasKey('idx_uji_idx_name', $indexes);
        $this->assertArrayHasKey('primary', $indexes);
        foreach (array_keys($indexes) as $name) {
            $this->assertStringStartsNotWith('sqlite_autoindex_', $name, 'index implisit UNIQUE/PK tidak didaftarkan ulang');
        }
    }

    public function testListTableForeignKeys() {
        $conn = $this->connection();
        $conn->statement('create table uji_parent (id integer primary key autoincrement)');
        $conn->statement('create table uji_child (id integer primary key autoincrement, parent_id integer not null, foreign key (parent_id) references uji_parent(id) on delete cascade)');

        $fks = $conn->getSchemaManager()->listTableForeignKeys('uji_child');

        $this->assertCount(1, $fks);
        $this->assertSame(['parent_id'], $fks[0]->getLocalColumns());
        $this->assertSame('uji_parent', $fks[0]->getForeignTableName());
        $this->assertSame(['id'], $fks[0]->getForeignColumns());
    }

    public function testListTableNamesAndCreateSchema() {
        $conn = $this->connection();
        $conn->statement('create table uji_a (id integer primary key)');
        $conn->statement('create table uji_b (id integer primary key)');

        $this->assertSame(['uji_a', 'uji_b'], $conn->getSchemaManager()->listTableNames());

        $schema = $conn->getSchemaManager()->createSchema();
        $this->assertSame(['id'], array_keys($schema->getTable('uji_a')->getColumns()));
    }

    public function testBlueprintChangePreservesDataIndexAndAutoincrementAcrossRebuild() {
        $conn = $this->connection();
        $conn->statement('create table uji_people (id integer primary key autoincrement, name varchar(100) not null, age integer null)');
        $conn->statement('create unique index idx_uji_people_name on uji_people (name)');
        $conn->table('uji_people')->insert([
            ['name' => 'Budi', 'age' => 30],
            ['name' => 'Siti', 'age' => null],
        ]);

        $conn->getSchemaBuilder()->table('uji_people', function (CDatabase_Schema_Blueprint $table) {
            $table->string('age')->nullable()->change();
        });

        $rows = $conn->table('uji_people')->orderBy('id')->get();
        $this->assertSame('30', $rows[0]->age, 'tipe berubah ke string, nilai lama tetap terbaca');
        $this->assertNull($rows[1]->age);

        $indexes = $conn->getSchemaManager()->listTableIndexes('uji_people');
        $this->assertArrayHasKey('idx_uji_people_name', $indexes, 'index bertahan setelah rebuild');
        $this->assertTrue($indexes['idx_uji_people_name']->isUnique());

        $this->expectException(CDatabase_Exception_QueryException::class);
        $conn->table('uji_people')->insert(['name' => 'Budi', 'age' => '99']);
    }

    public function testBlueprintChangeKeepsAutoincrementSequenceAfterRebuild() {
        $conn = $this->connection();
        $conn->statement('create table uji_seq (id integer primary key autoincrement, name varchar(50) not null, score integer null)');
        $conn->table('uji_seq')->insert(['name' => 'a', 'score' => 1]);
        $conn->table('uji_seq')->insert(['name' => 'b', 'score' => 2]);

        $conn->getSchemaBuilder()->table('uji_seq', function (CDatabase_Schema_Blueprint $table) {
            $table->string('score')->nullable()->change();
        });

        $newId = $conn->table('uji_seq')->insertGetId(['name' => 'c', 'score' => '3']);
        $this->assertSame(3, $newId, 'rowid alias tidak reset ke 1 setelah rebuild');
    }

    public function testBlueprintChangePreservesForeignKey() {
        $conn = $this->connection();
        $conn->statement('create table uji_parent (id integer primary key autoincrement)');
        $conn->statement('create table uji_child (id integer primary key autoincrement, parent_id integer not null, note varchar(50) null, foreign key (parent_id) references uji_parent(id))');

        $conn->getSchemaBuilder()->table('uji_child', function (CDatabase_Schema_Blueprint $table) {
            $table->string('note')->nullable()->change();
        });

        $fks = $conn->getSchemaManager()->listTableForeignKeys('uji_child');
        $this->assertCount(1, $fks, 'foreign key dibawa ke tabel hasil rebuild, tidak hilang');
        $this->assertSame('uji_parent', $fks[0]->getForeignTableName());
    }

    public function testBlueprintRenameColumnIsNativeAndKeepsData() {
        $conn = $this->connection();
        $conn->statement('create table uji_rename (id integer primary key autoincrement, old_name varchar(50) not null)');
        $conn->table('uji_rename')->insert(['old_name' => 'nilai']);

        $conn->getSchemaBuilder()->table('uji_rename', function (CDatabase_Schema_Blueprint $table) {
            $table->renameColumn('old_name', 'new_name');
        });

        $columns = $conn->getSchemaManager()->listTableColumns('uji_rename');
        $this->assertArrayHasKey('new_name', $columns);
        $this->assertArrayNotHasKey('old_name', $columns);
        $this->assertSame('nilai', $conn->table('uji_rename')->first()->new_name);
    }

    public function testBlueprintDropColumnRebuildsCorrectlyEvenWithoutNativeDropColumnSupport() {
        //bukti nyata kenapa removedColumns ikut memicu rebuild di CDatabase_Platform_Sqlite:
        //`ALTER TABLE ... DROP COLUMN` native baru ada sejak SQLite 3.35 - lingkungan test ini
        //sendiri terbukti punya sqlite lebih lama saat berkas ini ditulis (3.31.1)
        $conn = $this->connection();
        $conn->statement('create table uji_drop (id integer primary key autoincrement, name varchar(50) not null, gone varchar(10) null)');
        $conn->table('uji_drop')->insert(['name' => 'x', 'gone' => 'y']);

        $conn->getSchemaBuilder()->table('uji_drop', function (CDatabase_Schema_Blueprint $table) {
            $table->dropColumn('gone');
        });

        $columns = $conn->getSchemaManager()->listTableColumns('uji_drop');
        $this->assertSame(['id', 'name'], array_keys($columns));
        $this->assertSame('x', $conn->table('uji_drop')->first()->name);
    }
}
