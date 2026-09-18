<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * CDatabase_Schema_Grammar_PostgresGrammar - lanjutan port suite hulu (kasus yang belum ada di DatabasePostgresSchemaGrammarTest.php).
 * Tanpa koneksi nyata: CDatabase_Connection dipalsukan dengan Mockery.
 */
class DatabasePostgresSchemaGrammarPortTest extends TestCase {
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
     * @return CDatabase_Schema_Grammar_PostgresGrammar
     */
    protected function getGrammar($connection = null) {
        $grammar = new CDatabase_Schema_Grammar_PostgresGrammar();
        $grammar->setConnection($connection ?: $this->getConnection());

        return $grammar;
    }

    public function testCreateTableAndCommentColumn() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->create();
        $blueprint->increments('id');
        $blueprint->string('email')->comment('my first comment');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        //divergensi dari hulu: komentar kolom hanya diterbitkan pada alter, tidak saat create
        $this->assertCount(1, $statements);
        $this->assertSame('create table "users" ("id" serial not null primary key, "email" varchar(255) not null)', $statements[0]);
    }

    public function testDropSpatialIndex() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->dropSpatialIndex(['coordinates']);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('drop index "geo_coordinates_spatialindex"', $statements[0]);
    }

    public function testDropTimestamps() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dropTimestamps();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" drop column "created", drop column "updated"', $statements[0]);
    }

    public function testDropTimestampsTz() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dropTimestampsTz();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" drop column "created", drop column "updated"', $statements[0]);
    }

    public function testAddingFulltextIndex() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->fulltext('body');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create index "users_body_fulltext" on "users" using gin ((to_tsvector(\'english\', "body")))', $statements[0]);
    }

    public function testAddingFulltextIndexMultipleColumns() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->fulltext(['body', 'title']);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create index "users_body_title_fulltext" on "users" using gin ((to_tsvector(\'english\', "body") || to_tsvector(\'english\', "title")))', $statements[0]);
    }

    public function testAddingFulltextIndexWithLanguage() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->fulltext('body')->language('spanish');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create index "users_body_fulltext" on "users" using gin ((to_tsvector(\'spanish\', "body")))', $statements[0]);
    }

    public function testAddingFulltextIndexWithFluency() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('body')->fulltext();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(2, $statements);
        $this->assertSame('create index "users_body_fulltext" on "users" using gin ((to_tsvector(\'english\', "body")))', $statements[1]);
    }

    public function testAddingFluentSpatialIndex() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates', 'point')->spatialIndex();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(2, $statements);
        $this->assertSame('create index "geo_coordinates_spatialindex" on "geo" using gist ("coordinates")', $statements[1]);
    }

    public function testAddingSpatialIndexWithOperatorClass() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->spatialIndex('coordinates', 'my_index', 'point_ops');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create index "my_index" on "geo" using gist ("coordinates")', $statements[0]);
    }

    public function testAddingSpatialIndexWithOperatorClassMultipleColumns() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->spatialIndex(['coordinates', 'location'], 'my_index', 'point_ops');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('create index "my_index" on "geo" using gist ("coordinates", "location")', $statements[0]);
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
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->increments('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "id" serial not null primary key', $statements[0]);
    }

    public function testAddingSmallIncrementingID() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->smallIncrements('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "id" smallserial not null primary key', $statements[0]);
    }

    public function testAddingMediumIncrementingID() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->mediumIncrements('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "id" serial not null primary key', $statements[0]);
    }

    public function testAddingBigIncrementingID() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->bigIncrements('id');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "id" bigserial not null primary key', $statements[0]);
    }

    public function testAddingString() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar(255) not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo', 100);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar(100) not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo', 100)->nullable()->default('bar');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar(100) null default \'bar\'', $statements[0]);
    }

    public function testAddingStringWithoutLengthLimit() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" varchar(255) not null', $statements[0]);

        CDatabase_Schema_Builder::$defaultStringLength = null;

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        try {
            $this->assertCount(1, $statements);
            $this->assertSame('alter table "users" add column "foo" varchar not null', $statements[0]);
        } finally {
            CDatabase_Schema_Builder::$defaultStringLength = 255;
        }
    }

    public function testAddingCharWithoutLengthLimit() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->char('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" char(255) not null', $statements[0]);

        CDatabase_Schema_Builder::$defaultStringLength = null;

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->char('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        try {
            $this->assertCount(1, $statements);
            $this->assertSame('alter table "users" add column "foo" char not null', $statements[0]);
        } finally {
            CDatabase_Schema_Builder::$defaultStringLength = 255;
        }
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
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->bigInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" bigint not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->bigInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" bigserial not null primary key', $statements[0]);
    }

    public function testAddingInteger() {
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
        $this->assertSame('alter table "users" add column "foo" serial not null primary key', $statements[0]);
    }

    public function testAddingMediumInteger() {
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
        $this->assertSame('alter table "users" add column "foo" serial not null primary key', $statements[0]);
    }

    public function testAddingTinyInteger() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->tinyInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" smallint not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->tinyInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" smallserial not null primary key', $statements[0]);
    }

    public function testAddingSmallInteger() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->smallInteger('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" smallint not null', $statements[0]);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->smallInteger('foo', true);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" smallserial not null primary key', $statements[0]);
    }

    public function testAddingFloat() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->float('foo', 5);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" double precision not null', $statements[0]);
    }

    public function testAddingDouble() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->double('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" double precision not null', $statements[0]);
    }

    public function testAddingDecimal() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->decimal('foo', 5, 2);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" decimal(5, 2) not null', $statements[0]);
    }

    public function testAddingBoolean() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->boolean('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" boolean not null', $statements[0]);
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

    public function testAddingJson() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->json('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" json not null', $statements[0]);
    }

    public function testAddingJsonb() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->jsonb('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" jsonb not null', $statements[0]);
    }

    public function testAddingBinary() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->binary('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" bytea not null', $statements[0]);
    }

    public function testAddingUuid() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->uuid('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" uuid not null', $statements[0]);
    }

    public function testAddingUuidDefaultsColumnName() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->uuid();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "uuid" uuid not null', $statements[0]);
    }

    public function testAddingGeneratedAs() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->increments('foo')->generatedAs();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null generated by default as identity primary key', $statements[0]);
        // With always modifier
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->increments('foo')->generatedAs()->always();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null generated always as identity primary key', $statements[0]);
        // With sequence options
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->increments('foo')->generatedAs('increment by 10 start with 100');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null generated by default as identity (increment by 10 start with 100) primary key', $statements[0]);
        // Not a primary key
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->integer('foo')->generatedAs();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" integer not null generated by default as identity', $statements[0]);
    }

    public function testAddingVirtualAs() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->integer('foo')->nullable();
        $blueprint->boolean('bar')->virtualAs('foo is not null');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        //divergensi dari hulu: satu pernyataan alter untuk semua kolom, dan tanpa kata kunci "virtual"
        $this->assertCount(1, $statements);
        $this->assertSame([
            'alter table "users" add column "foo" integer null, add column "bar" boolean not null generated always as (foo is not null)',
        ], $statements);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->integer('foo')->nullable();
        $blueprint->boolean('bar')->virtualAs(new CDatabase_Query_Expression('foo is not null'));
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        //divergensi dari hulu: satu pernyataan alter untuk semua kolom, dan tanpa kata kunci "virtual"
        $this->assertCount(1, $statements);
        $this->assertSame([
            'alter table "users" add column "foo" integer null, add column "bar" boolean not null generated always as (foo is not null)',
        ], $statements);
    }

    public function testAddingStoredAs() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->integer('foo')->nullable();
        $blueprint->boolean('bar')->storedAs('foo is not null');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        //divergensi dari hulu: satu pernyataan alter untuk semua kolom
        $this->assertCount(1, $statements);
        $this->assertSame([
            'alter table "users" add column "foo" integer null, add column "bar" boolean not null generated always as (foo is not null) stored',
        ], $statements);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->integer('foo')->nullable();
        $blueprint->boolean('bar')->storedAs(new CDatabase_Query_Expression('foo is not null'));
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));
        //divergensi dari hulu: satu pernyataan alter untuk semua kolom
        $this->assertCount(1, $statements);
        $this->assertSame([
            'alter table "users" add column "foo" integer null, add column "bar" boolean not null generated always as (foo is not null) stored',
        ], $statements);
    }

    public function testAddingIpAddress() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->ipAddress('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" inet not null', $statements[0]);
    }

    public function testAddingIpAddressDefaultsColumnName() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->ipAddress();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "ip_address" inet not null', $statements[0]);
    }

    public function testAddingMacAddress() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->macAddress('foo');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "foo" macaddr not null', $statements[0]);
    }

    public function testAddingMacAddressDefaultsColumnName() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->macAddress();
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "users" add column "mac_address" macaddr not null', $statements[0]);
    }

    public function testAddingGeometry() {
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
    }

    public function testAddingPoint() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates', 'point');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
    }

    public function testAddingPointWithSrid() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates', 'point', 4269);
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
    }

    public function testAddingLineString() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates', 'linestring');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
    }

    public function testAddingPolygon() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates', 'polygon');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
    }

    public function testAddingGeometryCollection() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates', 'geometrycollection');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
    }

    public function testAddingMultiPoint() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates', 'multipoint');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
    }

    public function testAddingMultiLineString() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates', 'multilinestring');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
    }

    public function testAddingMultiPolygon() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->geometry('coordinates', 'multipolygon');
        $statements = $blueprint->toSql($connection, $this->getGrammar($connection));

        $this->assertCount(1, $statements);
        $this->assertSame('alter table "geo" add column "coordinates" geometry not null', $statements[0]);
    }

    public function testDropAllTablesEscapesTableNames() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $statement = $this->getGrammar($connection)->compileDropAllTables(['alpha', 'beta', 'gamma']);

        $this->assertSame('drop table "alpha","beta","gamma" cascade', $statement);
    }

    public function testDropAllViewsEscapesTableNames() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $statement = $this->getGrammar($connection)->compileDropAllViews(['alpha', 'beta', 'gamma']);

        $this->assertSame('drop view "alpha","beta","gamma" cascade', $statement);
    }

    public function testDropAllTypesEscapesTableNames() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection();
        $statement = $this->getGrammar($connection)->compileDropAllTypes(['alpha', 'beta', 'gamma']);

        $this->assertSame('drop type "alpha","beta","gamma" cascade', $statement);
    }

    public function testDropAllTablesWithPrefixAndSchema() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection(true, 'prefix_');
        $statement = $this->getGrammar($connection)->compileDropAllTables(['schema.alpha', 'schema.beta', 'schema.gamma']);

        $this->assertSame('drop table "schema"."alpha","schema"."beta","schema"."gamma" cascade', $statement);
    }

    public function testDropAllViewsWithPrefixAndSchema() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection(true, 'prefix_');
        $statement = $this->getGrammar($connection)->compileDropAllViews(['schema.alpha', 'schema.beta', 'schema.gamma']);

        $this->assertSame('drop view "schema"."alpha","schema"."beta","schema"."gamma" cascade', $statement);
    }

    public function testDropAllTypesWithPrefixAndSchema() {
        //divergensi dari hulu: bentuk DDL CF sekarang dikunci apa adanya
        $connection = $this->getConnection(true, 'prefix_');
        $statement = $this->getGrammar($connection)->compileDropAllTypes(['schema.alpha', 'schema.beta', 'schema.gamma']);

        $this->assertSame('drop type "schema"."alpha","schema"."beta","schema"."gamma" cascade', $statement);
    }
}
