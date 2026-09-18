<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * CDatabase_Schema_Blueprint - padanan suite hulu DatabaseSchemaBlueprintTest: build() menjalankan
 * pernyataan lewat koneksi, nama indeks bawaan (dengan/tanpa prefix), dan perintah yang sama
 * dikompilasi keempat grammar.
 */
class DatabaseSchemaBlueprintTest extends TestCase {
    protected function tearDown(): void {
        m::close();
    }

    /**
     * @param string $prefix
     *
     * @return CDatabase_Connection
     */
    protected function getConnection($prefix = '') {
        $connection = m::mock(CDatabase_Connection::class);
        $connection->shouldReceive('getConfig')->andReturn(null)->byDefault();
        $connection->shouldReceive('getTablePrefix')->andReturn($prefix)->byDefault();
        $connection->shouldReceive('usingNativeSchemaOperations')->andReturn(true)->byDefault();
        $connection->shouldReceive('getServerVersion')->andReturn('8.0.13')->byDefault();
        $connection->shouldReceive('isMaria')->andReturn(false)->byDefault();

        return $connection;
    }

    /**
     * @param string $grammar MySql|Postgres|SQLite|SqlServer
     * @param string $table
     * @param callable $callback
     *
     * @return array
     */
    protected function sql($grammar, $table, callable $callback) {
        $classes = [
            'MySql' => CDatabase_Schema_Grammar_MySqlGrammar::class,
            'Postgres' => CDatabase_Schema_Grammar_PostgresGrammar::class,
            'SQLite' => CDatabase_Schema_Grammar_SqliteGrammar::class,
            'SqlServer' => CDatabase_Schema_Grammar_SqlServerGrammar::class,
        ];
        $connection = $this->getConnection();
        $grammar = new $classes[$grammar]();
        $grammar->setConnection($connection);
        $blueprint = new CDatabase_Schema_Blueprint($table);
        $callback($blueprint);

        return $blueprint->toSql($connection, $grammar);
    }

    public function testBuildRunsEveryCompiledStatementThroughTheConnection() {
        $connection = $this->getConnection();
        $grammar = new CDatabase_Schema_Grammar_MySqlGrammar();
        $grammar->setConnection($connection);
        $define = function (CDatabase_Schema_Blueprint $table) {
            $table->string('name');
            $table->index('name');
        };

        $expected = (new CDatabase_Schema_Blueprint('users', $define))->toSql($connection, $grammar);
        $this->assertCount(2, $expected);
        foreach ($expected as $statement) {
            $connection->shouldReceive('query')->once()->with($statement);
        }

        (new CDatabase_Schema_Blueprint('users', $define))->build($connection, $grammar);
    }

    public function testCallbackIsRunOnConstruction() {
        $blueprint = new CDatabase_Schema_Blueprint('users', function (CDatabase_Schema_Blueprint $table) {
            $table->string('email');
        });

        $this->assertSame('users', $blueprint->getTable());
        $this->assertCount(1, $blueprint->getColumns());
        $this->assertSame('email', $blueprint->getColumns()[0]->name);
    }

    public function testIndexDefaultNames() {
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->unique(['foo', 'bar']);
        $this->assertSame('users_foo_bar_unique', $blueprint->getCommands()[0]->index);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->index('foo');
        $this->assertSame('users_foo_index', $blueprint->getCommands()[0]->index);

        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->spatialIndex('coordinates');
        $this->assertSame('geo_coordinates_spatialindex', $blueprint->getCommands()[0]->index);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->primary(['id', 'org_id']);
        $this->assertSame('users_id_org_id_primary', $blueprint->getCommands()[0]->index);
    }

    public function testIndexNamesDoNotCarryTheTablePrefix() {
        //divergensi dari hulu: nama indeks dihitung dari nama tabel tanpa prefix koneksi
        $connection = $this->getConnection('prefix_');
        $grammar = new CDatabase_Schema_Grammar_MySqlGrammar();
        $grammar->setConnection($connection);
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->index('foo');

        $this->assertSame('users_foo_index', $blueprint->getCommands()[0]->index);
        $this->assertSame(['alter table `prefix_users` add index `users_foo_index`(`foo`)'], $blueprint->toSql($connection, $grammar));
    }

    public function testDropIndexDefaultNames() {
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dropUnique(['foo', 'bar']);
        $this->assertSame('users_foo_bar_unique', $blueprint->getCommands()[0]->index);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dropIndex(['foo']);
        $this->assertSame('users_foo_index', $blueprint->getCommands()[0]->index);

        $blueprint = new CDatabase_Schema_Blueprint('geo');
        $blueprint->dropSpatialIndex(['coordinates']);
        $this->assertSame('geo_coordinates_spatialindex', $blueprint->getCommands()[0]->index);

        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->dropIndex('nama_eksplisit');
        $this->assertSame('nama_eksplisit', $blueprint->getCommands()[0]->index);
    }

    public function testDefaultCurrentDateTime() {
        $define = function ($table) {
            $table->dateTime('created')->useCurrent();
        };

        $this->assertSame(['alter table `users` add `created` datetime default CURRENT_TIMESTAMP not null'], $this->sql('MySql', 'users', $define));
        $this->assertSame(['alter table "users" add column "created" timestamp(0) without time zone not null default CURRENT_TIMESTAMP'], $this->sql('Postgres', 'users', $define));
        $this->assertSame(['alter table "users" add column "created" datetime default CURRENT_TIMESTAMP not null'], $this->sql('SQLite', 'users', $define));
        $this->assertSame(['alter table [users] add [created] datetime not null default CURRENT_TIMESTAMP'], $this->sql('SqlServer', 'users', $define));
    }

    public function testDefaultCurrentTimestamp() {
        $define = function ($table) {
            $table->timestamp('created')->useCurrent();
        };

        $this->assertSame(['alter table `users` add `created` timestamp default CURRENT_TIMESTAMP not null'], $this->sql('MySql', 'users', $define));
        $this->assertSame(['alter table "users" add column "created" timestamp(0) without time zone not null default CURRENT_TIMESTAMP'], $this->sql('Postgres', 'users', $define));
        $this->assertSame(['alter table "users" add column "created" datetime default CURRENT_TIMESTAMP not null'], $this->sql('SQLite', 'users', $define));
        $this->assertSame(['alter table [users] add [created] datetime not null default CURRENT_TIMESTAMP'], $this->sql('SqlServer', 'users', $define));
    }

    public function testRenameColumn() {
        $define = function ($table) {
            $table->renameColumn('foo', 'bar');
        };

        $this->assertSame(['alter table `users` rename column `foo` to `bar`'], $this->sql('MySql', 'users', $define));
        $this->assertSame(['alter table "users" rename column "foo" to "bar"'], $this->sql('Postgres', 'users', $define));
        $this->assertSame(['alter table "users" rename column "foo" to "bar"'], $this->sql('SQLite', 'users', $define));
        $this->assertSame(["sp_rename '[users].[foo]', [bar], 'COLUMN'"], $this->sql('SqlServer', 'users', $define));
    }

    public function testDropColumn() {
        $define = function ($table) {
            $table->dropColumn('foo');
        };

        $this->assertSame(['alter table `users` drop `foo`'], $this->sql('MySql', 'users', $define));
        $this->assertSame(['alter table "users" drop column "foo"'], $this->sql('Postgres', 'users', $define));
        $this->assertStringContainsString('alter table [users] drop column [foo]', $this->sql('SqlServer', 'users', $define)[0]);
        //SQLite membutuhkan Doctrine schema manager pada koneksi nyata; hanya tipe parameternya yang diuji
        $parameter = (new ReflectionMethod(CDatabase_Schema_Grammar_SqliteGrammar::class, 'compileDropColumn'))->getParameters()[2];
        $this->assertSame(CDatabase_Connection::class, $parameter->getType()->getName());
    }

    public function testColumnDefaultWithAnApostropheIsEscaped() {
        $define = function ($table) {
            $table->text('note')->default("this'll work too");
        };

        $this->assertSame(["alter table `users` add `note` text not null default 'this''ll work too'"], $this->sql('MySql', 'users', $define));
        $this->assertSame(["alter table \"users\" add column \"note\" text not null default 'this''ll work too'"], $this->sql('Postgres', 'users', $define));
        $this->assertSame(["alter table \"users\" add column \"note\" text not null default 'this''ll work too'"], $this->sql('SQLite', 'users', $define));
        $this->assertSame(["alter table [users] add [note] nvarchar(max) not null default 'this''ll work too'"], $this->sql('SqlServer', 'users', $define));
    }

    public function testColumnDefaultExpressionIsNotQuoted() {
        $define = function ($table) {
            $table->dateTime('created')->default(new CDatabase_Query_Expression('NOW()'));
        };

        //ekspresi ditulis sesudah "not null", nilai literal sebelum (lihat testDefaultCurrentDateTime)
        $this->assertSame(['alter table `users` add `created` datetime not null default NOW()'], $this->sql('MySql', 'users', $define));
    }

    public function testRemoveColumnDropsItBeforeCompiling() {
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $blueprint->string('foo');
        $blueprint->string('remove_this');
        $blueprint->removeColumn('remove_this');

        $this->assertSame(['foo'], array_map(function ($column) {
            return $column->name;
        }, $blueprint->getColumns()));
    }

    public function testAddColumnWithArbitraryTypeAndParameters() {
        $blueprint = new CDatabase_Schema_Blueprint('users');
        $column = $blueprint->addColumn('text', 'note', ['nullable' => true]);

        $this->assertInstanceOf(CDatabase_Schema_ColumnDefinition::class, $column);
        $this->assertSame('text', $column->type);
        $this->assertTrue($column->nullable);
        $this->assertSame(['alter table `users` add `note` text null'], $this->sql('MySql', 'users', function ($table) {
            $table->addColumn('text', 'note', ['nullable' => true]);
        }));
    }

    public function testUuidIpAddressAndMacAddressDefaultColumnNames() {
        $this->assertSame(['alter table `users` add `uuid` char(36) not null'], $this->sql('MySql', 'users', function ($table) {
            $table->uuid();
        }));
        $this->assertSame(['alter table `users` add `ip_address` varchar(45) not null'], $this->sql('MySql', 'users', function ($table) {
            $table->ipAddress();
        }));
        $this->assertSame(['alter table `users` add `mac_address` varchar(17) not null'], $this->sql('MySql', 'users', function ($table) {
            $table->macAddress();
        }));
        $this->assertSame(['alter table `users` add `identifier` char(36) not null'], $this->sql('MySql', 'users', function ($table) {
            $table->uuid('identifier');
        }));
    }
}
