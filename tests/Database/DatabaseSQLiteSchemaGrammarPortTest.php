<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * CDatabase_Schema_Grammar_SqliteGrammar - lanjutan port suite hulu (kasus yang belum ada di DatabaseSQLiteSchemaGrammarTest.php).
 * Tanpa koneksi nyata: CDatabase_Connection dipalsukan dengan Mockery.
 */
class DatabaseSQLiteSchemaGrammarPortTest extends TestCase {
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
     * @return CDatabase_Schema_Grammar_SqliteGrammar
     */
    protected function getGrammar($connection = null) {
        $grammar = new CDatabase_Schema_Grammar_SqliteGrammar();
        $grammar->setConnection($connection ?: $this->getConnection());

        return $grammar;
    }

    public function testDropIndexWithSchema() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('my_schema.users');
        $blueprint->dropIndex('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('drop index "foo"', $statements[0]);
    }

    public function testAddingPrimaryKey() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->create();
        $blueprint->string('foo')->primary();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create table "users" ("foo" varchar not null, primary key ("foo"))', $statements[0]);
    }

    public function testAddingForeignKey() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->create();
        $blueprint->string('foo')->primary();
        $blueprint->string('order_id');
        $blueprint->foreign('order_id')->references('id')->on('orders');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create table "users" ("foo" varchar not null, "order_id" varchar not null, foreign key("order_id") references "orders"("id"), primary key ("foo"))', $statements[0]);
    }

    public function testAddingUniqueKeyWithSchema() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('foo.users');
        $blueprint->unique('foo', 'bar');

        //divergensi dari hulu: skema dilekatkan ke tabel, bukan ke nama indeks (bentuk hulu yang sah untuk SQLite)
        $this->assertSame(['create unique index "bar" on "foo"."users" ("foo")'], $blueprint->toSql($connection, $this->getGrammar($connection)));
    }

    public function testAddingIndexWithSchema() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('foo.users');
        $blueprint->index(['foo', 'bar'], 'baz');

        //divergensi dari hulu: skema dilekatkan ke tabel, bukan ke nama indeks
        $this->assertSame(['create index "baz" on "foo"."users" ("foo", "bar")'], $blueprint->toSql($connection, $this->getGrammar($connection)));
    }

    public function testAddingRawIndex() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->rawIndex('(function(column))', 'raw_index');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create index "raw_index" on "users" ((function(column)))', $statements[0]);
    }

    public function testAddingIncrementingID() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->increments('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "id" integer not null primary key autoincrement', $statements[0]);
    }

    public function testAddingSmallIncrementingID() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->smallIncrements('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "id" integer not null primary key autoincrement', $statements[0]);
    }

    public function testAddingMediumIncrementingID() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->mediumIncrements('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "id" integer not null primary key autoincrement', $statements[0]);
    }

    public function testAddingBigIncrementingID() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->bigIncrements('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "id" integer not null primary key autoincrement', $statements[0]);
    }

    public function testAddingString() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo', 100);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo', 100)->nullable()->default('bar');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar default \'bar\'', $statements[0]);
    }

    public function testAddingText() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->text('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" text not null', $statements[0]);
    }

    public function testAddingBigInteger() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->bigInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->bigInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null primary key autoincrement', $statements[0]);
    }

    public function testAddingInteger() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->integer('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->integer('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null primary key autoincrement', $statements[0]);
    }

    public function testAddingMediumInteger() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->mediumInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->mediumInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null primary key autoincrement', $statements[0]);
    }

    public function testAddingTinyInteger() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->tinyInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->tinyInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null primary key autoincrement', $statements[0]);
    }

    public function testAddingSmallInteger() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->smallInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->smallInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null primary key autoincrement', $statements[0]);
    }

    public function testAddingFloat() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->float('foo', 5);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" float not null', $statements[0]);
    }

    public function testAddingDouble() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->double('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" float not null', $statements[0]);
    }

    public function testAddingDecimal() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->decimal('foo', 5, 2);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" numeric not null', $statements[0]);
    }

    public function testAddingBoolean() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->boolean('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" tinyint(1) not null', $statements[0]);
    }

    public function testAddingJson() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->json('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" text not null', $statements[0]);
    }

    public function testAddingJsonb() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->jsonb('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" text not null', $statements[0]);
    }

    public function testAddingDate() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->date('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" date not null', $statements[0]);
    }

    public function testAddingDateWithDefaultCurrent() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->date('foo')->useCurrent();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" date not null', $statements[0]);
    }

    public function testAddingDateTime() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dateTime('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
    }

    public function testAddingDateTimeWithPrecision() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dateTime('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
    }

    public function testAddingDateTimeTz() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dateTimeTz('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
    }

    public function testAddingDateTimeTzWithPrecision() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dateTimeTz('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
    }

    public function testAddingTime() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->time('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" time not null', $statements[0]);
    }

    public function testAddingTimeWithPrecision() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->time('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" time not null', $statements[0]);
    }

    public function testAddingTimeTz() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timeTz('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" time not null', $statements[0]);
    }

    public function testAddingTimeTzWithPrecision() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timeTz('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" time not null', $statements[0]);
    }

    public function testAddingTimestamp() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestamp('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
    }

    public function testAddingTimestampWithPrecision() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestamp('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
    }

    public function testAddingTimestampTz() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestampTz('created_at');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
    }

    public function testAddingTimestampTzWithPrecision() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestampTz('created_at', 1);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "created_at" datetime not null', $statements[0]);
    }

    public function testAddingTimestamps() {
        //kolom stempel waktu CF: created/updated
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestamps();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(2, $statements);
        $this->assertEquals([
            'alter table "users" add column "created" datetime',
            'alter table "users" add column "updated" datetime',
        ], $statements);
    }

    public function testAddingTimestampsTz() {
        //kolom stempel waktu CF: created/updated
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->timestampsTz();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(2, $statements);
        $this->assertEquals([
            'alter table "users" add column "created" datetime',
            'alter table "users" add column "updated" datetime',
        ], $statements);
    }

    public function testAddingRememberToken() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->rememberToken();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "remember_token" varchar', $statements[0]);
    }

    public function testAddingBinary() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->binary('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" blob not null', $statements[0]);
    }

    public function testAddingUuid() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->uuid('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);
    }

    public function testAddingUuidDefaultsColumnName() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->uuid();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "uuid" varchar not null', $statements[0]);
    }

    public function testAddingIpAddress() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->ipAddress('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);
    }

    public function testAddingIpAddressDefaultsColumnName() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->ipAddress();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "ip_address" varchar not null', $statements[0]);
    }

    public function testAddingMacAddress() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->macAddress('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);
    }

    public function testAddingMacAddressDefaultsColumnName() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->macAddress();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "mac_address" varchar not null', $statements[0]);
    }

    public function testAddingGeometry() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
    }

    public function testAddingGeneratedColumn() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('products');
        $blueprint->create();
        $blueprint->integer('price');
        $blueprint->integer('discounted_virtual')->virtualAs('"price" - 5');
        $blueprint->integer('discounted_stored')->storedAs('"price" - 5');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create table "products" ("price" integer not null, "discounted_virtual" integer as ("price" - 5), "discounted_stored" integer as ("price" - 5) stored)', $statements[0]);
        //bagian alter dengan storedAs dilewati: SQLite CF menolak kolom stored pada alter (lihat DatabaseSQLiteSchemaGrammarTest::testAddColumnRejectsStoredGeneratedColumns)
    }

    public function testAddingGeneratedColumnByExpression() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('products');
        $blueprint->create();
        $blueprint->integer('price');
        $blueprint->integer('discounted_virtual')->virtualAs(new CDatabase_Query_Expression('"price" - 5'));
        $blueprint->integer('discounted_stored')->storedAs(new CDatabase_Query_Expression('"price" - 5'));
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create table "products" ("price" integer not null, "discounted_virtual" integer as ("price" - 5), "discounted_stored" integer as ("price" - 5) stored)', $statements[0]);
    }
}
