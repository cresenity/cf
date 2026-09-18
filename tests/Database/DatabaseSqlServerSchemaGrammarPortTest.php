<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * CDatabase_Schema_Grammar_SqlServerGrammar - lanjutan port suite hulu (kasus yang belum ada di DatabaseSqlServerSchemaGrammarTest.php).
 * Tanpa koneksi nyata: CDatabase_Connection dipalsukan dengan Mockery.
 */
class DatabaseSqlServerSchemaGrammarPortTest extends TestCase {
    protected function tearDown(): void {
        m::close();
    }

    /**
     * @param bool   $usingNative
     * @param string $prefix
     *
     * @return CDatabase_Connection
     */
    protected function getConnection($usingNative = true, $prefix = '') {
        $connection = m::mock(CDatabase_Connection::class);
        $connection->shouldReceive('getConfig')->andReturn(null)->byDefault();
        $connection->shouldReceive('getTablePrefix')->andReturn($prefix)->byDefault();
        $connection->shouldReceive('usingNativeSchemaOperations')->andReturn($usingNative)->byDefault();

        return $connection;
    }

    /**
     * @param null|CDatabase_Connection $connection
     *
     * @return CDatabase_Schema_Grammar_SqlServerGrammar
     */
    protected function getGrammar($connection = null) {
        $grammar = new CDatabase_Schema_Grammar_SqlServerGrammar();
        $grammar->setConnection($connection ?: $this->getConnection());

        return $grammar;
    }

    public function testCreateTemporaryTable() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->create();
        $blueprint->temporary();
        $blueprint->increments('id');
        $blueprint->string('email');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create table [users] ([id] int not null identity primary key, [email] nvarchar(255) not null)', $statements[0]);
    }

    public function testCreateTemporaryTableWithPrefix() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection(true, 'prefix_');
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->create();
        $blueprint->temporary();
        $blueprint->increments('id');
        $blueprint->string('email');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create table [prefix_users] ([id] int not null identity primary key, [email] nvarchar(255) not null)', $statements[0]);
    }

    public function testDropColumn() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dropColumn('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertStringContainsString('alter table [users] drop column [foo]', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dropColumn(['foo', 'bar']);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertStringContainsString('alter table [users] drop column [foo], [bar]', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dropColumn('foo', 'bar');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertStringContainsString('alter table [users] drop column [foo], [bar]', $statements[0]);
    }

    public function testDropColumnDropsCreatesSqlToDropDefaultConstraints() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu "")
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('foo');
        $blueprint->dropColumn('bar');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('DECLARE @sql NVARCHAR(MAX) = \'\';SELECT @sql += \'ALTER TABLE [dbo].[foo] DROP CONSTRAINT \' + OBJECT_NAME([default_object_id]) + \';\' FROM sys.columns WHERE [object_id] = OBJECT_ID(\'[dbo].[foo]\') AND [name] in (\'bar\') AND [default_object_id] <> 0;EXEC(@sql);alter table [foo] drop column [bar]', $statements[0]);
    }

    public function testDropSpatialIndex() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->dropSpatialIndex(['coordinates']);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('drop index [geo_coordinates_spatialindex] on [geo]', $statements[0]);
    }

    public function testDropTimestamps() {
        //kolom stempel waktu CF: created/updated
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dropTimestamps();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertStringContainsString('alter table [users] drop column [created], [updated]', $statements[0]);
    }

    public function testDropTimestampsTz() {
        //kolom stempel waktu CF: created/updated
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dropTimestampsTz();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertStringContainsString('alter table [users] drop column [created], [updated]', $statements[0]);
    }

    public function testAddingFluentSpatialIndex() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates', 'point')->spatialIndex();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(2, $statements);
        $this->assertSame('create spatial index [geo_coordinates_spatialindex] on [geo] ([coordinates])', $statements[1]);
    }

    public function testAddingRawIndex() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->rawIndex('(function(column))', 'raw_index');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create index [raw_index] on [users] ((function(column)))', $statements[0]);
    }

    public function testAddingIncrementingID() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->increments('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [id] int not null identity primary key', $statements[0]);
    }

    public function testAddingSmallIncrementingID() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->smallIncrements('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [id] smallint not null identity primary key', $statements[0]);
    }

    public function testAddingMediumIncrementingID() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->mediumIncrements('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [id] int not null identity primary key', $statements[0]);
    }

    public function testAddingBigIncrementingID() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->bigIncrements('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [id] bigint not null identity primary key', $statements[0]);
    }

    public function testAddingString() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] nvarchar(255) not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo', 100);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] nvarchar(100) not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo', 100)->nullable()->default('bar');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] nvarchar(100) null default \'bar\'', $statements[0]);
    }

    public function testAddingText() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->text('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] nvarchar(max) not null', $statements[0]);
    }

    public function testAddingBigInteger() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->bigInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] bigint not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->bigInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] bigint not null identity primary key', $statements[0]);
    }

    public function testAddingInteger() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->integer('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] int not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->integer('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] int not null identity primary key', $statements[0]);
    }

    public function testAddingMediumInteger() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->mediumInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] int not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->mediumInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] int not null identity primary key', $statements[0]);
    }

    public function testAddingTinyInteger() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->tinyInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] tinyint not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->tinyInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] tinyint not null identity primary key', $statements[0]);
    }

    public function testAddingSmallInteger() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->smallInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] smallint not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->smallInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] smallint not null identity primary key', $statements[0]);
    }

    public function testAddingFloat() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->float('foo', 5);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] float not null', $statements[0]);
    }

    public function testAddingDouble() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->double('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] float not null', $statements[0]);
    }

    public function testAddingDecimal() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->decimal('foo', 5, 2);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] decimal(5, 2) not null', $statements[0]);
    }

    public function testAddingBoolean() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->boolean('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] bit not null', $statements[0]);
    }

    public function testAddingJson() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->json('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] nvarchar(max) not null', $statements[0]);
    }

    public function testAddingJsonb() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->jsonb('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] nvarchar(max) not null', $statements[0]);
    }

    public function testAddingDate() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->date('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] date not null', $statements[0]);
    }

    public function testAddingDateWithDefaultCurrent() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->date('foo')->useCurrent();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] date not null', $statements[0]);
    }

    public function testAddingDateTime() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dateTime('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [created_at] datetime not null', $statements[0]);
    }

    public function testAddingDateTimeWithPrecision() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dateTime('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [created_at] datetime2(1) not null', $statements[0]);
    }

    public function testAddingDateTimeTz() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dateTimeTz('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] datetimeoffset not null', $statements[0]);
    }

    public function testAddingDateTimeTzWithPrecision() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dateTimeTz('foo', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] datetimeoffset(1) not null', $statements[0]);
    }

    public function testAddingTime() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->time('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [created_at] time not null', $statements[0]);
    }

    public function testAddingTimeWithPrecision() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->time('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [created_at] time(1) not null', $statements[0]);
    }

    public function testAddingTimeTz() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timeTz('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [created_at] time not null', $statements[0]);
    }

    public function testAddingTimeTzWithPrecision() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timeTz('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [created_at] time(1) not null', $statements[0]);
    }

    public function testAddingTimestamp() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestamp('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [created_at] datetime not null', $statements[0]);
    }

    public function testAddingTimestampWithPrecision() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestamp('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [created_at] datetime2(1) not null', $statements[0]);
    }

    public function testAddingTimestampTz() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestampTz('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [created_at] datetimeoffset not null', $statements[0]);
    }

    public function testAddingTimestampTzWithPrecision() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestampTz('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [created_at] datetimeoffset(1) not null', $statements[0]);
    }

    public function testAddingTimestamps() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestamps();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        //divergensi dari hulu: satu pernyataan alter untuk kedua kolom
        $this->assertCount(1, $statements);
        $this->assertSame(['alter table [users] add [created] datetime null, [updated] datetime null'], $statements);
    }

    public function testAddingTimestampsTz() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestampsTz();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        //divergensi dari hulu: satu pernyataan alter untuk kedua kolom
        $this->assertCount(1, $statements);
        $this->assertSame(['alter table [users] add [created] datetimeoffset null, [updated] datetimeoffset null'], $statements);
    }

    public function testAddingRememberToken() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->rememberToken();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [remember_token] nvarchar(100) null', $statements[0]);
    }

    public function testAddingBinary() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->binary('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] varbinary(max) not null', $statements[0]);
    }

    public function testAddingUuid() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->uuid('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] uniqueidentifier not null', $statements[0]);
    }

    public function testAddingUuidDefaultsColumnName() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->uuid();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [uuid] uniqueidentifier not null', $statements[0]);
    }

    public function testAddingIpAddress() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->ipAddress('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] nvarchar(45) not null', $statements[0]);
    }

    public function testAddingIpAddressDefaultsColumnName() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->ipAddress();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [ip_address] nvarchar(45) not null', $statements[0]);
    }

    public function testAddingMacAddress() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->macAddress('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [foo] nvarchar(17) not null', $statements[0]);
    }

    public function testAddingMacAddressDefaultsColumnName() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->macAddress();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [users] add [mac_address] nvarchar(17) not null', $statements[0]);
    }

    public function testAddingGeometry() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table [geo] add [coordinates] geography not null', $statements[0]);
    }

    public function testQuoteString() {
        $connection = $this->getConnection();
        $this->assertSame("N'中文測試'", $this->getGrammar()->quoteString('中文測試'));
    }

    public function testQuoteStringOnArray() {
        $connection = $this->getConnection();
        $this->assertSame("N'中文', N'測試'", $this->getGrammar()->quoteString(['中文', '測試']));
    }

    public function testCreateDatabase() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu "")
        $connection = $this->getConnection();
        $statement = $this->getGrammar($connection)->compileCreateDatabase('my_database_a', $connection);

        $this->assertSame(
            'create database [my_database_a]',
            $statement
        );

        $statement = $this->getGrammar($connection)->compileCreateDatabase('my_database_b', $connection);

        $this->assertSame(
            'create database [my_database_b]',
            $statement
        );
    }

    public function testDropDatabaseIfExists() {
        //divergensi dari hulu: CF mengutip pengenal SQL Server dengan [] (hulu ""); bentuk DDL sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $statement = $this->getGrammar($connection)->compileDropDatabaseIfExists('my_database_a');

        $this->assertSame(
            'drop database if exists [my_database_a]',
            $statement
        );

        $statement = $this->getGrammar($connection)->compileDropDatabaseIfExists('my_database_b');

        $this->assertSame(
            'drop database if exists [my_database_b]',
            $statement
        );
    }
}
