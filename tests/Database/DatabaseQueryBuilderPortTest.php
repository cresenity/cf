<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * CDatabase_Query_Builder - porting lanjutan suite hulu (DatabaseQueryBuilderTest) untuk kasus
 * yang belum tercakup DatabaseQueryBuilderTest CF: SQL yang dihasilkan tiap grammar, binding,
 * agregat, insert/update/upsert/delete, join, subquery, paginasi, chunk, dan lock.
 */
class DatabaseQueryBuilderPortTest extends TestCase {
    protected function tearDown(): void {
        //ekspektasi Mockery dihitung sebagai assertion supaya test mock-only tidak dilaporkan "risky"
        if ($container = m::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
        m::close();
    }

    /**
     * @param null|CDatabase_Query_Grammar   $grammar
     * @param null|CDatabase_Query_Processor $processor
     * @param string                         $prefix
     *
     * @return \Mockery\MockInterface|CDatabase_Connection
     */
    protected function getConnection($grammar = null, $processor = null, $prefix = '') {
        $grammar = $grammar ?: new CDatabase_Query_Grammar();
        $processor = $processor ?: m::mock(CDatabase_Query_Processor::class)->makePartial();
        $connection = m::mock(CDatabase_Connection::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $connection->shouldReceive('getDatabaseName')->andReturn('database');
        $connection->shouldReceive('getTablePrefix')->andReturn($prefix);
        $connection->shouldReceive('raw')->andReturnUsing(function ($value) {
            return new CDatabase_Query_Expression($value);
        });
        $grammar->setConnection($connection);

        return $connection;
    }

    /**
     * @param string $grammarClass
     * @param string $processorClass
     * @param string $prefix
     * @param bool   $realProcessor
     *
     * @return CDatabase_Query_Builder
     */
    private function makeBuilder($grammarClass, $processorClass, $prefix = '', $realProcessor = false) {
        $grammar = new $grammarClass();
        $processor = $realProcessor ? new $processorClass() : m::mock($processorClass)->makePartial();

        return new CDatabase_Query_Builder($this->getConnection($grammar, $processor, $prefix), $grammar, $processor);
    }

    /**
     * @param string $prefix
     *
     * @return CDatabase_Query_Builder
     */
    protected function getBuilder($prefix = '') {
        return $this->makeBuilder(CDatabase_Query_Grammar::class, CDatabase_Query_Processor::class, $prefix);
    }

    /**
     * @param string $prefix
     *
     * @return CDatabase_Query_Builder
     */
    protected function getMySqlBuilder($prefix = '') {
        return $this->makeBuilder(CDatabase_Query_Grammar_MySqlGrammar::class, CDatabase_Query_Processor_MySqlProcessor::class, $prefix);
    }

    /**
     * @param string $prefix
     *
     * @return CDatabase_Query_Builder
     */
    protected function getMySqlBuilderWithProcessor($prefix = '') {
        return $this->makeBuilder(CDatabase_Query_Grammar_MySqlGrammar::class, CDatabase_Query_Processor_MySqlProcessor::class, $prefix, true);
    }

    /**
     * @param string $prefix
     *
     * @return CDatabase_Query_Builder
     */
    protected function getPostgresBuilder($prefix = '') {
        return $this->makeBuilder(CDatabase_Query_Grammar_PostgresGrammar::class, CDatabase_Query_Processor_PostgresProcessor::class, $prefix);
    }

    /**
     * @param string $prefix
     *
     * @return CDatabase_Query_Builder
     */
    protected function getPostgresBuilderWithProcessor($prefix = '') {
        return $this->makeBuilder(CDatabase_Query_Grammar_PostgresGrammar::class, CDatabase_Query_Processor_PostgresProcessor::class, $prefix, true);
    }

    /**
     * @param string $prefix
     *
     * @return CDatabase_Query_Builder
     */
    protected function getSQLiteBuilder($prefix = '') {
        return $this->makeBuilder(CDatabase_Query_Grammar_SqliteGrammar::class, CDatabase_Query_Processor_SqliteProcessor::class, $prefix);
    }

    /**
     * @param string $prefix
     *
     * @return CDatabase_Query_Builder
     */
    protected function getSqlServerBuilder($prefix = '') {
        return $this->makeBuilder(CDatabase_Query_Grammar_SqlServerGrammar::class, CDatabase_Query_Processor_SqlServerProcessor::class, $prefix);
    }

    /**
     * @return \Mockery\MockInterface|CDatabase_Query_Builder
     */
    protected function getMockQueryBuilder() {
        $grammar = new CDatabase_Query_Grammar();
        $processor = m::mock(CDatabase_Query_Processor::class);

        return m::mock(CDatabase_Query_Builder::class, [$this->getConnection($grammar, $processor), $grammar, $processor])->makePartial();
    }

    public function testBasicSelectWithGetColumns() {
        $builder = $this->getBuilder();
        $builder->getProcessor()->expects('processSelect')->times(3);
        $builder->getConnection()->expects('select')->andReturnUsing(function ($sql) {
            $this->assertSame('select * from "users"', $sql);
        });
        $builder->getConnection()->expects('select')->andReturnUsing(function ($sql) {
            $this->assertSame('select "foo", "bar" from "users"', $sql);
        });
        $builder->getConnection()->expects('select')->andReturnUsing(function ($sql) {
            $this->assertSame('select "baz" from "users"', $sql);
        });

        $builder->from('users')->get();
        $this->assertNull($builder->columns);

        $builder->from('users')->get(['foo', 'bar']);
        $this->assertNull($builder->columns);

        $builder->from('users')->get('baz');
        $this->assertNull($builder->columns);

        $this->assertSame('select * from "users"', $builder->toSql());
        $this->assertNull($builder->columns);
    }

    public function testBasicSelectUseWritePdo() {
        $builder = $this->getMySqlBuilderWithProcessor();
        $builder->getConnection()->expects('select')
            ->with('select * from `users`', [], false);
        $builder->useWritePdo()->select('*')->from('users')->get();

        $builder = $this->getMySqlBuilderWithProcessor();
        $builder->getConnection()->expects('select')
            ->with('select * from `users`', [], true);
        $builder->select('*')->from('users')->get();
    }

    public function testAliasWrappingWithSpacesInDatabaseName() {
        $builder = $this->getBuilder();
        $builder->select('w x.y.z as foo.bar')->from('baz');
        $this->assertSame('select "w x"."y"."z" as "foo.bar" from "baz"', $builder->toSql());
    }

    public function testBasicSelectDistinctOnColumns() {
        $builder = $this->getBuilder();
        $builder->distinct('foo')->select('foo', 'bar')->from('users');
        $this->assertSame('select distinct "foo", "bar" from "users"', $builder->toSql());

        $builder = $this->getPostgresBuilder();
        $builder->distinct('foo')->select('foo', 'bar')->from('users');
        $this->assertSame('select distinct on ("foo") "foo", "bar" from "users"', $builder->toSql());
    }

    public function testAliasWithPrefix() {
        $builder = $this->getBuilder('prefix_');
        $builder->select('*')->from('users as people');
        $this->assertSame('select * from "prefix_users" as "prefix_people"', $builder->toSql());
    }

    public function testJoinAliasesWithPrefix() {
        $builder = $this->getBuilder('prefix_');
        $builder->select('*')->from('services')->join('translations AS t', 't.item_id', '=', 'services.id');
        $this->assertSame('select * from "prefix_services" inner join "prefix_translations" as "prefix_t" on "prefix_t"."item_id" = "prefix_services"."id"', $builder->toSql());
    }

    public function testBasicTableWrapping() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('public.users');
        $this->assertSame('select * from "public"."users"', $builder->toSql());
    }

    public function testWhenCallback() {
        $callback = function ($query, $condition) {
            $this->assertTrue($condition);

            $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(true, $callback)->where('email', 'foo');
        $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(false, $callback)->where('email', 'foo');
        $this->assertSame('select * from "users" where "email" = ?', $builder->toSql());
    }

    public function testWhenCallbackWithReturn() {
        $callback = function ($query, $condition) {
            $this->assertTrue($condition);

            return $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(true, $callback)->where('email', 'foo');
        $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(false, $callback)->where('email', 'foo');
        $this->assertSame('select * from "users" where "email" = ?', $builder->toSql());
    }

    public function testWhenCallbackWithDefault() {
        $callback = function ($query, $condition) {
            $this->assertSame('truthy', $condition);

            $query->where('id', '=', 1);
        };

        $default = function ($query, $condition) {
            $this->assertEquals(0, $condition);

            $query->where('id', '=', 2);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when('truthy', $callback, $default)->where('email', 'foo');
        $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(0, $callback, $default)->where('email', 'foo');
        $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 2, 1 => 'foo'], $builder->getBindings());
    }

    public function testUnlessCallback() {
        $callback = function ($query, $condition) {
            $this->assertFalse($condition);

            $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(false, $callback)->where('email', 'foo');
        $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(true, $callback)->where('email', 'foo');
        $this->assertSame('select * from "users" where "email" = ?', $builder->toSql());
    }

    public function testUnlessCallbackWithReturn() {
        $callback = function ($query, $condition) {
            $this->assertFalse($condition);

            return $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(false, $callback)->where('email', 'foo');
        $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(true, $callback)->where('email', 'foo');
        $this->assertSame('select * from "users" where "email" = ?', $builder->toSql());
    }

    public function testUnlessCallbackWithDefault() {
        $callback = function ($query, $condition) {
            $this->assertEquals(0, $condition);

            $query->where('id', '=', 1);
        };

        $default = function ($query, $condition) {
            $this->assertSame('truthy', $condition);

            $query->where('id', '=', 2);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(0, $callback, $default)->where('email', 'foo');
        $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless('truthy', $callback, $default)->where('email', 'foo');
        $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 2, 1 => 'foo'], $builder->getBindings());
    }

    public function testTapCallback() {
        $callback = function ($query) {
            return $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->tap($callback)->where('email', 'foo');
        $this->assertSame('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
    }

    public function testWheresWithArrayValue() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', [12]);
        $this->assertSame('select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', [12, 30]);
        $this->assertSame('select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '!=', [12, 30]);
        $this->assertSame('select * from "users" where "id" != ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '<>', [12, 30]);
        $this->assertSame('select * from "users" where "id" <> ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', [[12, 30]]);
        $this->assertSame('select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 12], $builder->getBindings());
    }

    public function testDateBasedOrWheresAcceptsTwoArguments() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereDate('created_at', 1);
        $this->assertSame('select * from `users` where `id` = ? or date(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereDay('created_at', 1);
        $this->assertSame('select * from `users` where `id` = ? or day(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereMonth('created_at', 1);
        $this->assertSame('select * from `users` where `id` = ? or month(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereYear('created_at', 1);
        $this->assertSame('select * from `users` where `id` = ? or year(`created_at`) = ?', $builder->toSql());
    }

    public function testDateBasedWheresExpressionIsNotBound() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', new CDatabase_Query_Expression('NOW()'))->where('admin', true);
        $this->assertEquals([true], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', new CDatabase_Query_Expression('NOW()'));
        $this->assertSame([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', new CDatabase_Query_Expression('NOW()'));
        $this->assertSame([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', new CDatabase_Query_Expression('NOW()'));
        $this->assertSame([], $builder->getBindings());
    }

    public function testWhereDateMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
        $this->assertSame('select * from `users` where date(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', new CDatabase_Query_Expression('NOW()'));
        $this->assertSame('select * from `users` where date(`created_at`) = NOW()', $builder->toSql());
    }

    public function testWhereDayMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
        $this->assertSame('select * from `users` where day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testOrWhereDayMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->orWhereDay('created_at', '=', 2);
        $this->assertSame('select * from `users` where day(`created_at`) = ? or day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testOrWhereDayPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->orWhereDay('created_at', '=', 2);
        $this->assertSame('select * from "users" where extract(day from "created_at") = ? or extract(day from "created_at") = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testOrWhereDaySqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->orWhereDay('created_at', '=', 2);
        $this->assertSame('select * from [users] where day([created_at]) = ? or day([created_at]) = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testWhereMonthMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
        $this->assertSame('select * from `users` where month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 5], $builder->getBindings());
    }

    public function testOrWhereMonthMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->orWhereMonth('created_at', '=', 6);
        $this->assertSame('select * from `users` where month(`created_at`) = ? or month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
    }

    public function testOrWhereMonthPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->orWhereMonth('created_at', '=', 6);
        $this->assertSame('select * from "users" where extract(month from "created_at") = ? or extract(month from "created_at") = ?', $builder->toSql());
        $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
    }

    public function testOrWhereMonthSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->orWhereMonth('created_at', '=', 6);
        $this->assertSame('select * from [users] where month([created_at]) = ? or month([created_at]) = ?', $builder->toSql());
        $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
    }

    public function testWhereYearMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
        $this->assertSame('select * from `users` where year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014], $builder->getBindings());
    }

    public function testOrWhereYearMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->orWhereYear('created_at', '=', 2015);
        $this->assertSame('select * from `users` where year(`created_at`) = ? or year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
    }

    public function testOrWhereYearPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->orWhereYear('created_at', '=', 2015);
        $this->assertSame('select * from "users" where extract(year from "created_at") = ? or extract(year from "created_at") = ?', $builder->toSql());
        $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
    }

    public function testOrWhereYearSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->orWhereYear('created_at', '=', 2015);
        $this->assertSame('select * from [users] where year([created_at]) = ? or year([created_at]) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
    }

    public function testWhereTimeMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
        $this->assertSame('select * from `users` where time(`created_at`) >= ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function testWhereTimeOperatorOptionalMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '22:00');
        $this->assertSame('select * from `users` where time(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function testWhereTimeOperatorOptionalPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '22:00');
        $this->assertSame('select * from "users" where "created_at"::time = ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function testWhereTimeSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '22:00');
        $this->assertSame('select * from [users] where cast([created_at] as time) = ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', new CDatabase_Query_Expression('NOW()'));
        $this->assertSame('select * from [users] where cast([created_at] as time) = NOW()', $builder->toSql());
        $this->assertSame([], $builder->getBindings());
    }

    public function testOrWhereTimeMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '<=', '10:00')->orWhereTime('created_at', '>=', '22:00');
        $this->assertSame('select * from `users` where time(`created_at`) <= ? or time(`created_at`) >= ?', $builder->toSql());
        $this->assertEquals([0 => '10:00', 1 => '22:00'], $builder->getBindings());
    }

    public function testOrWhereTimePostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '<=', '10:00')->orWhereTime('created_at', '>=', '22:00');
        $this->assertSame('select * from "users" where "created_at"::time <= ? or "created_at"::time >= ?', $builder->toSql());
        $this->assertEquals([0 => '10:00', 1 => '22:00'], $builder->getBindings());
    }

    public function testOrWhereTimeSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '<=', '10:00')->orWhereTime('created_at', '>=', '22:00');
        $this->assertSame('select * from [users] where cast([created_at] as time) <= ? or cast([created_at] as time) >= ?', $builder->toSql());
        $this->assertEquals([0 => '10:00', 1 => '22:00'], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '<=', '10:00')->orWhereTime('created_at', new CDatabase_Query_Expression('NOW()'));
        $this->assertSame('select * from [users] where cast([created_at] as time) <= ? or cast([created_at] as time) = NOW()', $builder->toSql());
        $this->assertEquals([0 => '10:00'], $builder->getBindings());
    }

    public function testWhereDayPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
        $this->assertSame('select * from "users" where extract(day from "created_at") = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testWhereMonthPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
        $this->assertSame('select * from "users" where extract(month from "created_at") = ?', $builder->toSql());
        $this->assertEquals([0 => 5], $builder->getBindings());
    }

    public function testWhereYearPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
        $this->assertSame('select * from "users" where extract(year from "created_at") = ?', $builder->toSql());
        $this->assertEquals([0 => 2014], $builder->getBindings());
    }

    public function testWhereLikePostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('id', 'like', '1');
        $this->assertSame('select * from "users" where "id"::text like ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('id', 'LIKE', '1');
        $this->assertSame('select * from "users" where "id"::text LIKE ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('id', 'ilike', '1');
        $this->assertSame('select * from "users" where "id"::text ilike ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('id', 'not like', '1');
        $this->assertSame('select * from "users" where "id"::text not like ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('id', 'not ilike', '1');
        $this->assertSame('select * from "users" where "id"::text not ilike ?', $builder->toSql());
        $this->assertEquals([0 => '1'], $builder->getBindings());
    }

    public function testWhereDaySqlite() {
        $builder = $this->getSQLiteBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
        $this->assertSame('select * from "users" where strftime(\'%d\', "created_at") = cast(? as text)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testWhereMonthSqlite() {
        $builder = $this->getSQLiteBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
        $this->assertSame('select * from "users" where strftime(\'%m\', "created_at") = cast(? as text)', $builder->toSql());
        $this->assertEquals([0 => 5], $builder->getBindings());
    }

    public function testWhereYearSqlite() {
        $builder = $this->getSQLiteBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
        $this->assertSame('select * from "users" where strftime(\'%Y\', "created_at") = cast(? as text)', $builder->toSql());
        $this->assertEquals([0 => 2014], $builder->getBindings());
    }

    public function testWhereTimeSqlite() {
        $builder = $this->getSQLiteBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
        $this->assertSame('select * from "users" where strftime(\'%H:%M:%S\', "created_at") >= cast(? as text)', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function testWhereTimeOperatorOptionalSqlite() {
        $builder = $this->getSQLiteBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '22:00');
        $this->assertSame('select * from "users" where strftime(\'%H:%M:%S\', "created_at") = cast(? as text)', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function testWhereDateSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
        $this->assertSame('select * from [users] where cast([created_at] as date) = ?', $builder->toSql());
        $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', new CDatabase_Query_Expression('NOW()'));
        $this->assertSame('select * from [users] where cast([created_at] as date) = NOW()', $builder->toSql());
    }

    public function testWhereDaySqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
        $this->assertSame('select * from [users] where day([created_at]) = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testWhereMonthSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
        $this->assertSame('select * from [users] where month([created_at]) = ?', $builder->toSql());
        $this->assertEquals([0 => 5], $builder->getBindings());
    }

    public function testWhereYearSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
        $this->assertSame('select * from [users] where year([created_at]) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014], $builder->getBindings());
    }

    public function testOrWhereBetweenColumns() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 2)->orWhereBetweenColumns('id', ['users.created_at', 'users.updated_at']);
        $this->assertSame('select * from "users" where "id" = ? or "id" between "users"."created_at" and "users"."updated_at"', $builder->toSql());
        $this->assertEquals([0 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 2)->orWhereBetweenColumns('id', ['created_at', 'updated_at']);
        $this->assertSame('select * from "users" where "id" = ? or "id" between "created_at" and "updated_at"', $builder->toSql());
        $this->assertEquals([0 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 2)->orWhereBetweenColumns('id', [new CDatabase_Query_Expression(1), new CDatabase_Query_Expression(2)]);
        $this->assertSame('select * from "users" where "id" = ? or "id" between 1 and 2', $builder->toSql());
        $this->assertEquals([0 => 2], $builder->getBindings());
    }

    public function testOrWhereNotBetweenColumns() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 2)->orWhereNotBetweenColumns('id', ['users.created_at', 'users.updated_at']);
        $this->assertSame('select * from "users" where "id" = ? or "id" not between "users"."created_at" and "users"."updated_at"', $builder->toSql());
        $this->assertEquals([0 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 2)->orWhereNotBetweenColumns('id', ['created_at', 'updated_at']);
        $this->assertSame('select * from "users" where "id" = ? or "id" not between "created_at" and "updated_at"', $builder->toSql());
        $this->assertEquals([0 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 2)->orWhereNotBetweenColumns('id', [new CDatabase_Query_Expression(1), new CDatabase_Query_Expression(2)]);
        $this->assertSame('select * from "users" where "id" = ? or "id" not between 1 and 2', $builder->toSql());
        $this->assertEquals([0 => 2], $builder->getBindings());
    }

    public function testBasicOrWheres() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhere('email', '=', 'foo');
        $this->assertSame('select * from "users" where "id" = ? or "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testRawWheres() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereRaw('id = ? or email = ?', [1, 'foo']);
        $this->assertSame('select * from "users" where id = ? or email = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testRawOrWheres() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereRaw('email = ?', ['foo']);
        $this->assertSame('select * from "users" where "id" = ? or email = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testBasicWhereNotIns() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', [1, 2, 3]);
        $this->assertSame('select * from "users" where "id" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', [1, 2, 3]);
        $this->assertSame('select * from "users" where "id" = ? or "id" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function testRawWhereIns() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [new CDatabase_Query_Expression(1)]);
        $this->assertSame('select * from "users" where "id" in (1)', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [new CDatabase_Query_Expression(1)]);
        $this->assertSame('select * from "users" where "id" = ? or "id" in (1)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testOrWhereIntegerInRaw() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIntegerInRaw('id', ['1a', 2]);
        $this->assertSame('select * from "users" where "id" = ? or "id" in (1, 2)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testOrWhereIntegerNotInRaw() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIntegerNotInRaw('id', ['1a', 2]);
        $this->assertSame('select * from "users" where "id" = ? or "id" not in (1, 2)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testEmptyWhereIntegerInRaw() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerInRaw('id', []);
        $this->assertSame('select * from "users" where 0 = 1', $builder->toSql());
        $this->assertSame([], $builder->getBindings());
    }

    public function testEmptyWhereIntegerNotInRaw() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerNotInRaw('id', []);
        $this->assertSame('select * from "users" where 1 = 1', $builder->toSql());
        $this->assertSame([], $builder->getBindings());
    }

    public function testMultipleUnionAlls() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertSame('(select * from "users" where "id" = ?) union all select * from "users" where "id" = ? union all select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function testMySqlUnionLimitsAndOffsets() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users');
        $builder->union($this->getMySqlBuilder()->select('*')->from('dogs'));
        $builder->offset(5)->limit(10);
        $this->assertSame('(select * from `users`) union select * from `dogs` limit 10 offset 5', $builder->toSql());
    }

    public function testUnionAggregate() {
        $expected = 'select count(*) as aggregate from ((select * from `posts`) union select * from `videos`) as `temp_table`';
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('select')->with($expected, [], true);
        $builder->getProcessor()->expects('processSelect');
        $builder->from('posts')->union($this->getMySqlBuilder()->from('videos'))->count();

        $expected = 'select count(*) as aggregate from ((select `id` from `posts`) union select `id` from `videos`) as `temp_table`';
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('select')->with($expected, [], true);
        $builder->getProcessor()->expects('processSelect');
        $builder->from('posts')->select('id')->union($this->getMySqlBuilder()->from('videos')->select('id'))->count();

        $expected = 'select count(*) as aggregate from ((select * from "posts") union select * from "videos") as "temp_table"';
        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('select')->with($expected, [], true);
        $builder->getProcessor()->expects('processSelect');
        $builder->from('posts')->union($this->getPostgresBuilder()->from('videos'))->count();

        //grammar SQLite CF hanya membungkus query pertama dari union
        $expected = 'select count(*) as aggregate from (select * from (select * from "posts") union select * from "videos") as "temp_table"';
        $builder = $this->getSQLiteBuilder();
        $builder->getConnection()->expects('select')->with($expected, [], true);
        $builder->getProcessor()->expects('processSelect');
        $builder->from('posts')->union($this->getSQLiteBuilder()->from('videos'))->count();

        $expected = 'select count(*) as aggregate from (select * from (select * from [posts]) as [temp_table] union select * from [videos]) as [temp_table]';
        $builder = $this->getSqlServerBuilder();
        $builder->getConnection()->expects('select')->with($expected, [], true);
        $builder->getProcessor()->expects('processSelect');
        $builder->from('posts')->union($this->getSqlServerBuilder()->from('videos'))->count();
    }

    public function testHavingAggregate() {
        $expected = 'select count(*) as aggregate from (select (select `count(*)` from `videos` where `posts`.`id` = `videos`.`post_id`) as `videos_count` from `posts` having `videos_count` > ?) as `temp_table`';
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('select')->with($expected, [0 => 1], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $builder->from('posts')->selectSub(function ($query) {
            $query->from('videos')->select('count(*)')->whereColumn('posts.id', '=', 'videos.post_id');
        }, 'videos_count')->having('videos_count', '>', 1);
        $builder->count();
    }

    public function testSubSelectWhereIns() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->limit(3);
        });
        $this->assertSame('select * from "users" where "id" in (select "id" from "users" where "age" > ? limit 3)', $builder->toSql());
        $this->assertEquals([25], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->limit(3);
        });
        $this->assertSame('select * from "users" where "id" not in (select "id" from "users" where "age" > ? limit 3)', $builder->toSql());
        $this->assertEquals([25], $builder->getBindings());
    }

    public function testBasicWhereNulls() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull('id');
        $this->assertSame('select * from "users" where "id" is null', $builder->toSql());
        $this->assertSame([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull('id');
        $this->assertSame('select * from "users" where "id" = ? or "id" is null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testJsonWhereNullMysql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereNull('items->id');
        $this->assertSame('select * from `users` where (json_extract(`items`, \'$."id"\') is null OR json_type(json_extract(`items`, \'$."id"\')) = \'NULL\')', $builder->toSql());
    }

    public function testJsonWhereNotNullMysql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereNotNull('items->id');
        $this->assertSame('select * from `users` where (json_extract(`items`, \'$."id"\') is not null AND json_type(json_extract(`items`, \'$."id"\')) != \'NULL\')', $builder->toSql());
    }

    public function testJsonWhereNullExpressionMysql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereNull(new CDatabase_Query_Expression('items->id'));
        $this->assertSame('select * from `users` where (json_extract(`items`, \'$."id"\') is null OR json_type(json_extract(`items`, \'$."id"\')) = \'NULL\')', $builder->toSql());
    }

    public function testJsonWhereNotNullExpressionMysql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereNotNull(new CDatabase_Query_Expression('items->id'));
        $this->assertSame('select * from `users` where (json_extract(`items`, \'$."id"\') is not null AND json_type(json_extract(`items`, \'$."id"\')) != \'NULL\')', $builder->toSql());
    }

    public function testArrayWhereNulls() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull(['id', 'expires_at']);
        $this->assertSame('select * from "users" where "id" is null and "expires_at" is null', $builder->toSql());
        $this->assertSame([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull(['id', 'expires_at']);
        $this->assertSame('select * from "users" where "id" = ? or "id" is null or "expires_at" is null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testBasicWhereNotNulls() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotNull('id');
        $this->assertSame('select * from "users" where "id" is not null', $builder->toSql());
        $this->assertSame([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull('id');
        $this->assertSame('select * from "users" where "id" > ? or "id" is not null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testArrayWhereNotNulls() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotNull(['id', 'expires_at']);
        $this->assertSame('select * from "users" where "id" is not null and "expires_at" is not null', $builder->toSql());
        $this->assertSame([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull(['id', 'expires_at']);
        $this->assertSame('select * from "users" where "id" > ? or "id" is not null or "expires_at" is not null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testOrderBys() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
        $this->assertSame('select * from "users" order by "email" asc, "age" desc', $builder->toSql());

        $builder->orders = null;
        $this->assertSame('select * from "users"', $builder->toSql());

        $builder->orders = [];
        $this->assertSame('select * from "users"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderByRaw('"age" ? desc', ['foo']);
        $this->assertSame('select * from "users" order by "email" asc, "age" ? desc', $builder->toSql());
        $this->assertEquals(['foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderByDesc('name');
        $this->assertSame('select * from "users" order by "name" desc', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('posts')->where('public', 1)
            ->unionAll($this->getBuilder()->select('*')->from('videos')->where('public', 1))
            ->orderByRaw('field(category, ?, ?) asc', ['news', 'opinion']);
        $this->assertSame('(select * from "posts" where "public" = ?) union all select * from "videos" where "public" = ? order by field(category, ?, ?) asc', $builder->toSql());
        $this->assertEquals([1, 1, 'news', 'opinion'], $builder->getBindings());
    }

    public function testLatest() {
        //kolom bawaan latest()/oldest() di CF adalah kolom audit `created`, bukan created_at
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->latest();
        $this->assertSame('select * from "users" order by "created" desc', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->latest()->limit(1);
        $this->assertSame('select * from "users" order by "created" desc limit 1', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->latest('updated_at');
        $this->assertSame('select * from "users" order by "updated_at" desc', $builder->toSql());
    }

    public function testOldest() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->oldest();
        $this->assertSame('select * from "users" order by "created" asc', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->oldest()->limit(1);
        $this->assertSame('select * from "users" order by "created" asc limit 1', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->oldest('updated_at');
        $this->assertSame('select * from "users" order by "updated_at" asc', $builder->toSql());
    }

    public function testInRandomOrderMySqlGrammarWithoutSeed() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->inRandomOrder();
        $this->assertSame('select * from `users` order by RAND()', $builder->toSql());
    }

    public function testInRandomOrderMySqlGrammarWithSeed() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->inRandomOrder(123);
        $this->assertSame('select * from `users` order by RAND(123)', $builder->toSql());
    }

    public function testInRandomOrderSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->inRandomOrder();
        $this->assertSame('select * from [users] order by NEWID()', $builder->toSql());
    }

    public function testOrderBysSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
        $this->assertSame('select * from [users] order by [email] asc, [age] desc', $builder->toSql());

        $builder->orders = null;
        $this->assertSame('select * from [users]', $builder->toSql());

        $builder->orders = [];
        $this->assertSame('select * from [users]', $builder->toSql());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->orderBy('email');
        $this->assertSame('select * from [users] order by [email] asc', $builder->toSql());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->orderByDesc('name');
        $this->assertSame('select * from [users] order by [name] desc', $builder->toSql());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->orderByRaw('[age] asc');
        $this->assertSame('select * from [users] order by [age] asc', $builder->toSql());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderByRaw('[age] ? desc', ['foo']);
        $this->assertSame('select * from [users] order by [email] asc, [age] ? desc', $builder->toSql());
        $this->assertEquals(['foo'], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->offset(25)->limit(10)->orderByRaw('[email] desc');
        $this->assertSame('select * from [users] order by [email] desc offset 25 rows fetch next 10 rows only', $builder->toSql());
    }

    public function testOrderByInvalidDirectionParam() {
        $this->expectException(InvalidArgumentException::class);

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('age', 'asec');
    }

    public function testHavingShortcut() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('email', 1)->orHaving('email', 2);
        $this->assertSame('select * from "users" having "email" = ? or "email" = ?', $builder->toSql());
    }

    public function testHavingFollowedBySelectGet() {
        $builder = $this->getBuilder();
        $query = 'select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > ?';
        $builder->getConnection()->expects('select')->with($query, ['popular', 3], true)->andReturn([['category' => 'rock', 'total' => 5]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('item');
        $result = $builder->select(['category', new CDatabase_Query_Expression('count(*) as "total"')])->where('department', '=', 'popular')->groupBy('category')->having('total', '>', 3)->get();
        $this->assertEquals([['category' => 'rock', 'total' => 5]], $result->all());

        // Using \Raw value
        $builder = $this->getBuilder();
        $query = 'select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > 3';
        $builder->getConnection()->expects('select')->with($query, ['popular'], true)->andReturn([['category' => 'rock', 'total' => 5]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('item');
        $result = $builder->select(['category', new CDatabase_Query_Expression('count(*) as "total"')])->where('department', '=', 'popular')->groupBy('category')->having('total', '>', new CDatabase_Query_Expression('3'))->get();
        $this->assertEquals([['category' => 'rock', 'total' => 5]], $result->all());
    }

    public function testRawHavings() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->havingRaw('user_foo < user_bar');
        $this->assertSame('select * from "users" having user_foo < user_bar', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('baz', '=', 1)->orHavingRaw('user_foo < user_bar');
        $this->assertSame('select * from "users" having "baz" = ? or user_foo < user_bar', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->havingBetween('last_login_date', ['2018-11-16', '2018-12-16'])->orHavingRaw('user_foo < user_bar');
        $this->assertSame('select * from "users" having "last_login_date" between ? and ? or user_foo < user_bar', $builder->toSql());
    }

    public function testForPageBeforeId() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPageBeforeId(15, null);
        $this->assertSame('select * from "users" order by "id" desc limit 15', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPageBeforeId(15, 0);
        $this->assertSame('select * from "users" where "id" < ? order by "id" desc limit 15', $builder->toSql());
    }

    public function testForPageAfterId() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPageAfterId(15, null);
        $this->assertSame('select * from "users" order by "id" asc limit 15', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPageAfterId(15, 0);
        $this->assertSame('select * from "users" where "id" > ? order by "id" asc limit 15', $builder->toSql());
    }

    public function testGetCountForPaginationWithBindings() {
        $builder = $this->getBuilder();
        $builder->from('users')->selectSub(function ($q) {
            $q->select('body')->from('posts')->where('id', 4);
        }, 'post');

        $builder->getConnection()->expects('select')->with('select count(*) as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
        $this->assertEquals([4], $builder->getBindings());
    }

    public function testGetCountForPaginationWithColumnAliases() {
        $builder = $this->getBuilder();
        $columns = ['body as post_body', 'teaser', 'posts.created as published'];
        $builder->from('posts')->select($columns);

        $builder->getConnection()->expects('select')->with('select count("body", "teaser", "posts"."created") as aggregate from "posts"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination($columns);
        $this->assertEquals(1, $count);
    }

    public function testGetCountForPaginationWithUnion() {
        $builder = $this->getBuilder();
        $builder->from('posts')->select('id')->union($this->getBuilder()->from('videos')->select('id'));

        $builder->getConnection()->expects('select')->with('select count(*) as aggregate from ((select "id" from "posts") union select "id" from "videos") as "temp_table"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
    }

    public function testGetCountForPaginationWithUnionOrders() {
        $builder = $this->getBuilder();
        $builder->from('posts')->select('id')->union($this->getBuilder()->from('videos')->select('id'))->latest();

        $builder->getConnection()->expects('select')->with('select count(*) as aggregate from ((select "id" from "posts") union select "id" from "videos") as "temp_table"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
    }

    public function testGetCountForPaginationWithUnionLimitAndOffset() {
        $builder = $this->getBuilder();
        $builder->from('posts')->select('id')->union($this->getBuilder()->from('videos')->select('id'))->limit(15)->offset(1);

        $builder->getConnection()->expects('select')->with('select count(*) as aggregate from ((select "id" from "posts") union select "id" from "videos") as "temp_table"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
    }

    public function testOrWheresHaveConsistentResults() {
        $queries = [];
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhere(['foo' => 1, 'bar' => 2]);
        $queries[] = $builder->toSql();

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhere([['foo', 1], ['bar', 2]]);
        $queries[] = $builder->toSql();

        //kedua bentuk array mewarisi `or` pemanggilnya, juga daftar-pasangan [['foo', 1], ['bar', 2]]
        $this->assertSame([
            'select * from "users" where "xxxx" = ? or ("foo" = ? or "bar" = ?)',
            'select * from "users" where "xxxx" = ? or ("foo" = ? or "bar" = ?)',
        ], $queries);

        $queries = [];
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereColumn(['foo' => '_foo', 'bar' => '_bar']);
        $queries[] = $builder->toSql();

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhereColumn([['foo', '_foo'], ['bar', '_bar']]);
        $queries[] = $builder->toSql();

        $this->assertSame([
            'select * from "users" where "xxxx" = ? or ("foo" = "_foo" or "bar" = "_bar")',
            'select * from "users" where "xxxx" = ? or ("foo" = "_foo" or "bar" = "_bar")',
        ], $queries);
    }

    public function testListOfPairsWhereKeepsExplicitOperatorAndCallerBoolean() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('xxxx', 'xxxx')->orWhere([['foo', '>', 1], ['bar', 2]]);
        $this->assertSame('select * from "users" where "xxxx" = ? or ("foo" > ? or "bar" = ?)', $builder->toSql());
        $this->assertSame(['xxxx', 1, 2], $builder->getBindings());

        //where() biasa tetap `and` di dalam kurung
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where([['foo', 1], ['bar', null]]);
        $this->assertSame('select * from "users" where ("foo" = ? and "bar" is null)', $builder->toSql());
    }

    public function testNestedWhereBindings() {
        $builder = $this->getBuilder();
        $builder->where('email', '=', 'foo')->where(function ($q) {
            $q->selectRaw('?', ['ignore'])->where('name', '=', 'bar');
        });
        $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
    }

    public function testIncrementManyArgumentValidation1() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-numeric value passed as increment amount for column: \'col\'.');
        $builder = $this->getBuilder();
        $builder->from('users')->incrementEach(['col' => 'a']);
    }

    public function testIncrementManyArgumentValidation2() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-associative array passed to incrementEach method.');
        $builder = $this->getBuilder();
        $builder->from('users')->incrementEach([11 => 11]);
    }

    public function testCrossJoins() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('sizes')->crossJoin('colors');
        $this->assertSame('select * from "sizes" cross join "colors"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('tableB')->join('tableA', 'tableA.column1', '=', 'tableB.column2', 'cross');
        $this->assertSame('select * from "tableB" cross join "tableA" on "tableA"."column1" = "tableB"."column2"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('tableB')->crossJoin('tableA', 'tableA.column1', '=', 'tableB.column2');
        $this->assertSame('select * from "tableB" cross join "tableA" on "tableA"."column1" = "tableB"."column2"', $builder->toSql());
    }

    public function testCrossJoinSubs() {
        $builder = $this->getBuilder();
        $builder->selectRaw('(sale / overall.sales) * 100 AS percent_of_total')->from('sales')->crossJoinSub($this->getBuilder()->selectRaw('SUM(sale) AS sales')->from('sales'), 'overall');
        $this->assertSame('select (sale / overall.sales) * 100 AS percent_of_total from "sales" cross join (select SUM(sale) AS sales from "sales") as "overall"', $builder->toSql());
    }

    public function testJoinWhereNull() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at');
        });
        $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."deleted_at" is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNull('contacts.deleted_at');
        });
        $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."deleted_at" is null', $builder->toSql());
    }

    public function testJoinWhereNotNull() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotNull('contacts.deleted_at');
        });
        $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."deleted_at" is not null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNotNull('contacts.deleted_at');
        });
        $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."deleted_at" is not null', $builder->toSql());
    }

    public function testJoinWhereIn() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }

    public function testJoinWhereInSubquery() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $q = $this->getBuilder();
            $q->select('name')->from('contacts')->where('name', 'baz');
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', $q);
        });
        $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" in (select "name" from "contacts" where "name" = ?)', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $q = $this->getBuilder();
            $q->select('name')->from('contacts')->where('name', 'baz');
            $j->on('users.id', '=', 'contacts.id')->orWhereIn('contacts.name', $q);
        });
        $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" in (select "name" from "contacts" where "name" = ?)', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function testJoinWhereNotIn() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNotIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertSame('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }

    public function testJoinsWithSubqueryCondition() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->whereIn('contact_type_id', function ($q) {
                $q->select('id')->from('contact_types')
                    ->where('category_id', '1')
                    ->whereNull('deleted_at');
            });
        });
        $this->assertSame('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and "contact_type_id" in (select "id" from "contact_types" where "category_id" = ? and "deleted_at" is null)', $builder->toSql());
        $this->assertEquals(['1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->whereExists(function ($q) {
                $q->selectRaw('1')->from('contact_types')
                    ->whereRaw('contact_types.id = contacts.contact_type_id')
                    ->where('category_id', '1')
                    ->whereNull('deleted_at');
            });
        });
        $this->assertSame('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and exists (select 1 from "contact_types" where contact_types.id = contacts.contact_type_id and "category_id" = ? and "deleted_at" is null)', $builder->toSql());
        $this->assertEquals(['1'], $builder->getBindings());
    }

    public function testJoinsWithAdvancedSubqueryCondition() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->whereExists(function ($q) {
                $q->selectRaw('1')->from('contact_types')
                    ->whereRaw('contact_types.id = contacts.contact_type_id')
                    ->where('category_id', '1')
                    ->whereNull('deleted_at')
                    ->whereIn('level_id', function ($q) {
                        $q->select('id')->from('levels')
                            ->where('is_active', true);
                    });
            });
        });
        $this->assertSame('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and exists (select 1 from "contact_types" where contact_types.id = contacts.contact_type_id and "category_id" = ? and "deleted_at" is null and "level_id" in (select "id" from "levels" where "is_active" = ?))', $builder->toSql());
        $this->assertEquals(['1', true], $builder->getBindings());
    }

    public function testJoinsWithNestedJoins() {
        $builder = $this->getBuilder();
        $builder->select('users.id', 'contacts.id', 'contact_types.id')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id');
        });
        $this->assertSame('select "users"."id", "contacts"."id", "contact_types"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id") on "users"."id" = "contacts"."id"', $builder->toSql());
    }

    public function testJoinsWithMultipleNestedJoins() {
        $builder = $this->getBuilder();
        $builder->select('users.id', 'contacts.id', 'contact_types.id', 'countries.id', 'planets.id')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')
                ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id')
                ->leftJoin('countries', function ($q) {
                    $q->on('contacts.country', '=', 'countries.country')
                        ->join('planets', function ($q) {
                            $q->on('countries.planet_id', '=', 'planet.id')
                                ->where('planet.is_settled', '=', 1)
                                ->where('planet.population', '>=', 10000);
                        });
                });
        });
        $this->assertSame('select "users"."id", "contacts"."id", "contact_types"."id", "countries"."id", "planets"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id" left join ("countries" inner join "planets" on "countries"."planet_id" = "planet"."id" and "planet"."is_settled" = ? and "planet"."population" >= ?) on "contacts"."country" = "countries"."country") on "users"."id" = "contacts"."id"', $builder->toSql());
        $this->assertEquals(['1', 10000], $builder->getBindings());
    }

    public function testJoinsWithNestedJoinWithAdvancedSubqueryCondition() {
        $builder = $this->getBuilder();
        $builder->select('users.id', 'contacts.id', 'contact_types.id')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')
                ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id')
                ->whereExists(function ($q) {
                    $q->select('*')->from('countries')
                        ->whereColumn('contacts.country', '=', 'countries.country')
                        ->join('planets', function ($q) {
                            $q->on('countries.planet_id', '=', 'planet.id')
                                ->where('planet.is_settled', '=', 1);
                        })
                        ->where('planet.population', '>=', 10000);
                });
        });
        $this->assertSame('select "users"."id", "contacts"."id", "contact_types"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id") on "users"."id" = "contacts"."id" and exists (select * from "countries" inner join "planets" on "countries"."planet_id" = "planet"."id" and "planet"."is_settled" = ? where "contacts"."country" = "countries"."country" and "planet"."population" >= ?)', $builder->toSql());
        $this->assertEquals(['1', 10000], $builder->getBindings());
    }

    public function testJoinWithNestedOnCondition() {
        $builder = $this->getBuilder();
        $builder->select('users.id')->from('users')->join('contacts', function (CDatabase_Query_JoinClause $j) {
            return $j
                ->on('users.id', 'contacts.id')
                ->addNestedWhereQuery($this->getBuilder()->where('contacts.id', 1));
        });
        $this->assertSame('select "users"."id" from "users" inner join "contacts" on "users"."id" = "contacts"."id" and ("contacts"."id" = ?)', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testJoinSubWithPrefix() {
        $builder = $this->getBuilder('prefix_');
        $builder->from('users')->joinSub('select * from "contacts"', 'sub', 'users.id', '=', 'sub.id');
        $this->assertSame('select * from "prefix_users" inner join (select * from "contacts") as "prefix_sub" on "prefix_users"."id" = "prefix_sub"."id"', $builder->toSql());
    }

    public function testFindReturnsFirstResultByID() {
        //find() CF memakai <tabel>_id sebagai kunci, bukan id
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select * from "users" where "users_id" = ? limit 1', [1], true)->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()->expects('processSelect')->with($builder, [['foo' => 'bar']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->find(1);
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function testFirstMethodReturnsFirstResult() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select * from "users" where "id" = ? limit 1', [1], true)->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()->expects('processSelect')->with($builder, [['foo' => 'bar']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->first();
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function testFirstOrFailMethodReturnsFirstResult() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select * from "users" where "id" = ? limit 1', [1], true)->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()->expects('processSelect')->with($builder, [['foo' => 'bar']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->firstOrFail();
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function testFirstOrFailMethodThrowsRecordNotFoundException() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select * from "users" where "id" = ? limit 1', [1], true)->andReturn([]);

        $builder->getProcessor()->expects('processSelect')->with($builder, [])->andReturn([]);

        $this->expectException(CDatabase_Exception_RecordNotFoundException::class);
        $this->expectExceptionMessage('No record found for the given query.');

        $builder->from('users')->where('id', '=', 1)->firstOrFail();
    }

    public function testPluckMethodGetsCollectionOfColumnValues() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->expects('processSelect')->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo');
        $this->assertEquals(['bar', 'baz'], $results->all());

        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->andReturn([['id' => 1, 'foo' => 'bar'], ['id' => 10, 'foo' => 'baz']]);
        $builder->getProcessor()->expects('processSelect')->with($builder, [['id' => 1, 'foo' => 'bar'], ['id' => 10, 'foo' => 'baz']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo', 'id');
        $this->assertEquals([1 => 'bar', 10 => 'baz'], $results->all());
    }

    public function testPluckAvoidsDuplicateColumnSelection() {
        //CF tetap memilih kolom kunci walau sama dengan kolom nilai (hulu menyaringnya)
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select "foo", "foo" from "users" where "id" = ?', [1], true)->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()->expects('processSelect')->with($builder, [['foo' => 'bar']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo', 'foo');
        $this->assertEquals(['bar' => 'bar'], $results->all());
    }

    public function testImplode() {
        // Test without glue.
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->expects('processSelect')->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->implode('foo');
        $this->assertSame('barbaz', $results);

        // Test with glue.
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->expects('processSelect')->with($builder, [['foo' => 'bar'], ['foo' => 'baz']])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->implode('foo', ',');
        $this->assertSame('bar,baz', $results);
    }

    public function testValueMethodReturnsSingleColumn() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select "foo" from "users" where "id" = ? limit 1', [1], true)->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()->expects('processSelect')->with($builder, [['foo' => 'bar']])->andReturn([['foo' => 'bar']]);
        $results = $builder->from('users')->where('id', '=', 1)->value('foo');
        $this->assertSame('bar', $results);
    }

    public function testAggregateFunctions() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select count(*) as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->count();
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select exists(select * from "users") as "exists"', [], true)->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->exists();
        $this->assertTrue($results);

        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select exists(select * from "users") as "exists"', [], true)->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->doesntExist();
        $this->assertTrue($results);

        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select max("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->max('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select min("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->min('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select sum("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->sum('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select avg("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->avg('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select avg("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->average('id');
        $this->assertEquals(1, $results);
    }

    public function testSqlServerExists() {
        $builder = $this->getSqlServerBuilder();
        $builder->getConnection()->expects('select')->with('select top 1 1 [exists] from [users]', [], true)->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->exists();
        $this->assertTrue($results);
    }

    public function testExistsOr() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->doesntExistOr(function () {
            return 123;
        });
        $this->assertSame(123, $results);
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->doesntExistOr(function () {
            throw new RuntimeException;
        });
        $this->assertTrue($results);
    }

    public function testDoesntExistsOr() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->existsOr(function () {
            return 123;
        });
        $this->assertSame(123, $results);
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->existsOr(function () {
            throw new RuntimeException;
        });
        $this->assertTrue($results);
    }

    public function testAggregateResetFollowedByGet() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select count(*) as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getConnection()->expects('select')->with('select sum("id") as aggregate from "users"', [], true)->andReturn([['aggregate' => 2]]);
        $builder->getConnection()->expects('select')->with('select "column1", "column2" from "users"', [], true)->andReturn([['column1' => 'foo', 'column2' => 'bar']]);
        $builder->getProcessor()->expects('processSelect')->times(3)->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users')->select('column1', 'column2');
        $count = $builder->count();
        $this->assertEquals(1, $count);
        $sum = $builder->sum('id');
        $this->assertEquals(2, $sum);
        $result = $builder->get();
        $this->assertEquals([['column1' => 'foo', 'column2' => 'bar']], $result->all());
    }

    public function testAggregateResetFollowedBySelectGet() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select count("column1") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getConnection()->expects('select')->with('select "column2", "column3" from "users"', [], true)->andReturn([['column2' => 'foo', 'column3' => 'bar']]);
        $builder->getProcessor()->expects('processSelect')->times(2)->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users');
        $count = $builder->count('column1');
        $this->assertEquals(1, $count);
        $result = $builder->select('column2', 'column3')->get();
        $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result->all());
    }

    public function testAggregateResetFollowedByGetWithColumns() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select count("column1") as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getConnection()->expects('select')->with('select "column2", "column3" from "users"', [], true)->andReturn([['column2' => 'foo', 'column3' => 'bar']]);
        $builder->getProcessor()->expects('processSelect')->times(2)->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users');
        $count = $builder->count('column1');
        $this->assertEquals(1, $count);
        $result = $builder->get(['column2', 'column3']);
        $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result->all());
    }

    public function testAggregateWithSubSelect() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select count(*) as aggregate from "users"', [], true)->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->expects('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users')->selectSub(function ($query) {
            $query->from('posts')->select('foo', 'bar')->where('title', 'foo');
        }, 'post');
        $count = $builder->count();
        $this->assertEquals(1, $count);
        $this->assertSame('(select "foo", "bar" from "posts" where "title" = ?) as "post"', $builder->getGrammar()->getValue($builder->columns[0]));
        $this->assertEquals(['foo'], $builder->getBindings());
    }

    public function testSubqueriesBindings() {
        $builder = $this->getBuilder();
        $second = $this->getBuilder()->select('*')->from('users')->orderByRaw('id = ?', 2);
        $third = $this->getBuilder()->select('*')->from('users')->where('id', 3)->groupBy('id')->having('id', '!=', 4);
        $builder->groupBy('a')->having('a', '=', 1)->union($second)->union($third);
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3, 3 => 4], $builder->getBindings());

        $builder = $this->getBuilder()->select('*')->from('users')->where('email', '=', function ($q) {
            $q->select(new CDatabase_Query_Expression('max(id)'))
                ->from('users')->where('email', '=', 'bar')
                ->orderByRaw('email like ?', '%.com')
                ->groupBy('id')->having('id', '=', 4);
        })->orWhere('id', '=', 'foo')->groupBy('id')->having('id', '=', 5);
        $this->assertEquals([0 => 'bar', 1 => 4, 2 => '%.com', 3 => 'foo', 4 => 5], $builder->getBindings());
    }

    public function testInsertUsingWithEmptyColumns() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into "table1" select * from "table2" where "foreign_id" = ?', [5])->andReturn(1);

        $result = $builder->from('table1')->insertUsing(
            [],
            function (CDatabase_Query_Builder $query) {
                $query->from('table2')->where('foreign_id', '=', 5);
            }
        );

        $this->assertEquals(1, $result);
    }

    public function testInsertUsingInvalidSubquery() {
        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->from('table1')->insertUsing(['foo'], ['bar']);
    }

    public function testInsertOrIgnoreMethod() {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not support');
        $builder = $this->getBuilder();
        $builder->from('users')->insertOrIgnore(['email' => 'foo']);
    }

    public function testMySqlInsertOrIgnoreMethod() {
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert ignore into `users` (`email`) values (?)', ['foo'])->andReturn(1);
        $result = $builder->from('users')->insertOrIgnore(['email' => 'foo']);
        $this->assertEquals(1, $result);
    }

    public function testPostgresInsertOrIgnoreMethod() {
        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into "users" ("email") values (?) on conflict do nothing', ['foo'])->andReturn(1);
        $result = $builder->from('users')->insertOrIgnore(['email' => 'foo']);
        $this->assertEquals(1, $result);
    }

    public function testSQLiteInsertOrIgnoreMethod() {
        $builder = $this->getSQLiteBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert or ignore into "users" ("email") values (?)', ['foo'])->andReturn(1);
        $result = $builder->from('users')->insertOrIgnore(['email' => 'foo']);
        $this->assertEquals(1, $result);
    }

    public function testSqlServerInsertOrIgnoreMethod() {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not support');
        $builder = $this->getSqlServerBuilder();
        $builder->from('users')->insertOrIgnore(['email' => 'foo']);
    }

    public function testInsertGetIdMethod() {
        $builder = $this->getBuilder();
        $builder->getProcessor()->expects('processInsertGetId')->with($builder, 'insert into "users" ("email") values (?)', ['foo'], 'id')->andReturn(1);
        $result = $builder->from('users')->insertGetId(['email' => 'foo'], 'id');
        $this->assertEquals(1, $result);
    }

    public function testInsertGetIdMethodRemovesExpressions() {
        $builder = $this->getBuilder();
        $builder->getProcessor()->expects('processInsertGetId')->with($builder, 'insert into "users" ("email", "bar") values (?, bar)', ['foo'], 'id')->andReturn(1);
        $result = $builder->from('users')->insertGetId(['email' => 'foo', 'bar' => new CDatabase_Query_Expression('bar')], 'id');
        $this->assertEquals(1, $result);
    }

    public function testInsertGetIdWithEmptyValues() {
        $builder = $this->getMySqlBuilder();
        $builder->getProcessor()->expects('processInsertGetId')->with($builder, 'insert into `users` () values ()', [], null);
        $builder->from('users')->insertGetId([]);

        $builder = $this->getPostgresBuilder();
        $builder->getProcessor()->expects('processInsertGetId')->with($builder, 'insert into "users" default values returning "id"', [], null);
        $builder->from('users')->insertGetId([]);

        $builder = $this->getSQLiteBuilder();
        $builder->getProcessor()->expects('processInsertGetId')->with($builder, 'insert into "users" default values', [], null);
        $builder->from('users')->insertGetId([]);

        $builder = $this->getSqlServerBuilder();
        $builder->getProcessor()->expects('processInsertGetId')->with($builder, 'insert into [users] default values', [], null);
        $builder->from('users')->insertGetId([]);
    }

    public function testInsertMethodRespectsRawBindings() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('insertWithQuery')->with('insert into "users" ("email") values (CURRENT TIMESTAMP)', [])->andReturn(true);
        $result = $builder->from('users')->insert(['email' => new CDatabase_Query_Expression('CURRENT TIMESTAMP')]);
        $this->assertTrue($result);
    }

    public function testMultipleInsertsWithExpressionValues() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('insertWithQuery')->with('insert into "users" ("email") values (UPPER(\'Foo\')), (LOWER(\'Foo\'))', [])->andReturn(true);
        $result = $builder->from('users')->insert([['email' => new CDatabase_Query_Expression("UPPER('Foo')")], ['email' => new CDatabase_Query_Expression("LOWER('Foo')")]]);
        $this->assertTrue($result);
    }

    public function testUpsertMethod() {
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into `users` (`email`, `name`) values (?, ?), (?, ?) on duplicate key update `email` = values(`email`), `name` = values(`name`)', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
        $this->assertEquals(2, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into `users` (`email`, `name`) values (?, ?), (?, ?) on duplicate key update `email` = values(`email`), `name` = values(`name`)', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
        $this->assertEquals(2, $result);

        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into "users" ("email", "name") values (?, ?), (?, ?) on conflict ("email") do update set "email" = "excluded"."email", "name" = "excluded"."name"', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
        $this->assertEquals(2, $result);

        $builder = $this->getSQLiteBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into "users" ("email", "name") values (?, ?), (?, ?) on conflict ("email") do update set "email" = "excluded"."email", "name" = "excluded"."name"', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
        $this->assertEquals(2, $result);

        $builder = $this->getSqlServerBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('merge [users] using (values (?, ?), (?, ?)) [cf_source] ([email], [name]) on [cf_source].[email] = [users].[email] when matched then update set [email] = [cf_source].[email], [name] = [cf_source].[name] when not matched then insert ([email], [name]) values ([email], [name]);', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email');
        $this->assertEquals(2, $result);
    }

    public function testUpsertMethodWithUpdateColumns() {
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into `users` (`email`, `name`) values (?, ?), (?, ?) on duplicate key update `name` = values(`name`)', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
        $this->assertEquals(2, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into `users` (`email`, `name`) values (?, ?), (?, ?) on duplicate key update `name` = values(`name`)', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
        $this->assertEquals(2, $result);

        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into "users" ("email", "name") values (?, ?), (?, ?) on conflict ("email") do update set "name" = "excluded"."name"', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
        $this->assertEquals(2, $result);

        $builder = $this->getSQLiteBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into "users" ("email", "name") values (?, ?), (?, ?) on conflict ("email") do update set "name" = "excluded"."name"', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
        $this->assertEquals(2, $result);

        $builder = $this->getSqlServerBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('merge [users] using (values (?, ?), (?, ?)) [cf_source] ([email], [name]) on [cf_source].[email] = [users].[email] when matched then update set [name] = [cf_source].[name] when not matched then insert ([email], [name]) values ([email], [name]);', ['foo', 'bar', 'foo2', 'bar2'])->andReturn(2);
        $result = $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], 'email', ['name']);
        $this->assertEquals(2, $result);
    }

    public function testUpsertMethodWithEmptyUniqueByArray() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The unique columns must not be empty.');
        $builder = $this->getPostgresBuilder();
        $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar']], []);
    }

    public function testUpsertMethodWithEmptyUniqueByString() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The unique columns must not be empty.');
        $builder = $this->getPostgresBuilder();
        $builder->from('users')->upsert([['email' => 'foo', 'name' => 'bar']], '');
    }

    public function testUpdateMethodWithJoinsOnSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update [users] set [email] = ?, [name] = ? from [users] inner join [orders] on [users].[id] = [orders].[user_id] where [users].[id] = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getSqlServerBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update [users] set [email] = ?, [name] = ? from [users] inner join [orders] on [users].[id] = [orders].[user_id] and [users].[id] = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->join('orders', function ($join) {
            $join->on('users.id', '=', 'orders.user_id')
                ->where('users.id', '=', 1);
        })->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWithJoinsOnMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` set `email` = ?, `name` = ? where `users`.`id` = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` and `users`.`id` = ? set `email` = ?, `name` = ?', [1, 'foo', 'bar'])->andReturn(1);
        $result = $builder->from('users')->join('orders', function ($join) {
            $join->on('users.id', '=', 'orders.user_id')
                ->where('users.id', '=', 1);
        })->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWithJoinsOnSQLite() {
        $builder = $this->getSQLiteBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "email" = ?, "name" = ? where "rowid" in (select "users"."rowid" from "users" where "users"."id" > ? order by "id" asc limit 3)', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->where('users.id', '>', 1)->limit(3)->oldest('id')->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getSQLiteBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "email" = ?, "name" = ? where "rowid" in (select "users"."rowid" from "users" inner join "orders" on "users"."id" = "orders"."user_id" where "users"."id" = ?)', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getSQLiteBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "email" = ?, "name" = ? where "rowid" in (select "users"."rowid" from "users" inner join "orders" on "users"."id" = "orders"."user_id" and "users"."id" = ?)', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->join('orders', function ($join) {
            $join->on('users.id', '=', 'orders.user_id')
                ->where('users.id', '=', 1);
        })->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getSQLiteBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" as "u" set "email" = ?, "name" = ? where "rowid" in (select "u"."rowid" from "users" as "u" inner join "orders" as "o" on "u"."id" = "o"."user_id")', ['foo', 'bar'])->andReturn(1);
        $result = $builder->from('users as u')->join('orders as o', 'u.id', '=', 'o.user_id')->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWithJoinsAndAliasesOnSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update [u] set [email] = ?, [name] = ? from [users] as [u] inner join [orders] on [u].[id] = [orders].[user_id] where [u].[id] = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users as u')->join('orders', 'u.id', '=', 'orders.user_id')->where('u.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWithoutJoinsOnPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "email" = ?, "name" = ? where "id" = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['users.email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "email" = ?, "name" = ? where "id" = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->selectRaw('?', ['ignore'])->update(['users.email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users"."users" set "email" = ?, "name" = ? where "id" = ?', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users.users')->where('id', '=', 1)->selectRaw('?', ['ignore'])->update(['users.users.email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWithJoinsOnPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "email" = ?, "name" = ? where "ctid" in (select "users"."ctid" from "users" inner join "orders" on "users"."id" = "orders"."user_id" where "users"."id" = ?)', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->join('orders', 'users.id', '=', 'orders.user_id')->where('users.id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "email" = ?, "name" = ? where "ctid" in (select "users"."ctid" from "users" inner join "orders" on "users"."id" = "orders"."user_id" and "users"."id" = ?)', ['foo', 'bar', 1])->andReturn(1);
        $result = $builder->from('users')->join('orders', function ($join) {
            $join->on('users.id', '=', 'orders.user_id')
                ->where('users.id', '=', 1);
        })->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "email" = ?, "name" = ? where "ctid" in (select "users"."ctid" from "users" inner join "orders" on "users"."id" = "orders"."user_id" and "users"."id" = ? where "name" = ?)', ['foo', 'bar', 1, 'baz'])->andReturn(1);
        $result = $builder->from('users')
            ->join('orders', function ($join) {
                $join->on('users.id', '=', 'orders.user_id')
                    ->where('users.id', '=', 1);
            })->where('name', 'baz')
            ->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodRespectsRaw() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "email" = foo, "name" = ? where "id" = ?', ['bar', 1])->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['email' => new CDatabase_Query_Expression('foo'), 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWorksWithQueryAsValue() {
        $builder = $this->getBuilder();
        $subQueryBuilder = $this->getBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "credits" = (select sum(credits) from "transactions" where "transactions"."user_id" = "users"."id" and "type" = ?) where "id" = ?', ['foo', 1])->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['credits' => $subQueryBuilder->from('transactions')->selectRaw('sum(credits)')->whereColumn('transactions.user_id', 'users.id')->where('type', 'foo')]);
        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $subQueryBuilder = new CModel_Query($this->getBuilder());
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "credits" = (select sum(credits) from "transactions" where "transactions"."user_id" = "users"."id" and "type" = ?) where "id" = ?', ['foo', 1])->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['credits' => $subQueryBuilder->from('transactions')->selectRaw('sum(credits)')->whereColumn('transactions.user_id', 'users.id')->where('type', 'foo')]);
        $this->assertEquals(1, $result);
    }

    public function testUpdateOrInsertMethod() {
        $grammar = new CDatabase_Query_Grammar();
        $processor = m::mock(CDatabase_Query_Processor::class);
        $builder = m::mock(CDatabase_Query_Builder::class.'[where,exists,insert]', [$this->getConnection($grammar, $processor), $grammar, $processor]);

        $builder->expects('where')->with(['email' => 'foo'])->andReturn(m::self());
        $builder->expects('exists')->andReturn(false);
        $builder->expects('insert')->with(['email' => 'foo', 'name' => 'bar'])->andReturn(true);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));

        $grammar = new CDatabase_Query_Grammar();
        $processor = m::mock(CDatabase_Query_Processor::class);
        $builder = m::mock(CDatabase_Query_Builder::class.'[where,exists,update]', [$this->getConnection($grammar, $processor), $grammar, $processor]);

        $builder->expects('where')->with(['email' => 'foo'])->andReturn(m::self());
        $builder->expects('exists')->andReturn(true);
        $builder->expects('update')->with(['name' => 'bar'])->andReturn(1);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));
    }

    public function testTruncateMethodWithPrefix() {
        $builder = $this->getBuilder('prefix_');
        $connection = $builder->getConnection();
        $connection->expects('statement')->with('truncate "prefix_users"', []);
        $builder->from('users')->truncate();

        $builder = $this->getSQLiteBuilder('prefix_');
        $builder->from('users');
        //nama di sqlite_sequence memakai nama tabel mentah tanpa prefix (hulu: prefix_users) — divergensi dicatat
        $this->assertEquals([
            'delete from sqlite_sequence where name = ?' => ['users'],
            'delete from "prefix_users"' => [],
        ], $builder->getGrammar()->compileTruncate($builder));
    }

    public function testTruncateMethodWithPrefixAndSchema() {
        $builder = $this->getBuilder('prefix_');
        $connection = $builder->getConnection();
        $connection->expects('statement')->with('truncate "my_schema"."prefix_users"', []);
        $builder->from('my_schema.users')->truncate();

        //grammar SQLite CF belum memisahkan skema untuk sqlite_sequence (hulu: "my_schema".sqlite_sequence)
        $builder = $this->getSQLiteBuilder('prefix_');
        $builder->from('my_schema.users');
        $this->assertEquals([
            'delete from sqlite_sequence where name = ?' => ['my_schema.users'],
            'delete from "my_schema"."prefix_users"' => [],
        ], $builder->getGrammar()->compileTruncate($builder));
    }

    public function testPreserveAddsClosureToArray() {
        $builder = $this->getBuilder();
        $builder->beforeQuery(function () {
        });
        $this->assertCount(1, $builder->beforeQueryCallbacks);
        $this->assertInstanceOf(Closure::class, $builder->beforeQueryCallbacks[0]);
    }

    public function testApplyPreserveCleansArray() {
        $builder = $this->getBuilder();
        $builder->beforeQuery(function () {
        });
        $this->assertCount(1, $builder->beforeQueryCallbacks);
        $builder->applyBeforeQueryCallbacks();
        $this->assertCount(0, $builder->beforeQueryCallbacks);
    }

    public function testPreservedAreAppliedByToSql() {
        $builder = $this->getBuilder();
        $builder->beforeQuery(function ($builder) {
            $builder->where('foo', 'bar');
        });
        $this->assertSame('select * where "foo" = ?', $builder->toSql());
        $this->assertEquals(['bar'], $builder->getBindings());
    }

    public function testPreservedAreAppliedByInsert() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('insertWithQuery')->with('insert into "users" ("email") values (?)', ['foo']);
        $builder->beforeQuery(function ($builder) {
            $builder->from('users');
        });
        $builder->insert(['email' => 'foo']);
    }

    public function testPreservedAreAppliedByInsertGetId() {
        $this->called = false;
        $builder = $this->getBuilder();
        $builder->getProcessor()->expects('processInsertGetId')->with($builder, 'insert into "users" ("email") values (?)', ['foo'], 'id');
        $builder->beforeQuery(function ($builder) {
            $builder->from('users');
        });
        $builder->insertGetId(['email' => 'foo'], 'id');
    }

    public function testPreservedAreAppliedByInsertUsing() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into "users" ("email") select *', []);
        $builder->beforeQuery(function ($builder) {
            $builder->from('users');
        });
        $builder->insertUsing(['email'], $this->getBuilder());
    }

    public function testPreservedAreAppliedByUpsert() {
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into `users` (`email`) values (?) on duplicate key update `email` = values(`email`)', ['foo']);
        $builder->beforeQuery(function ($builder) {
            $builder->from('users');
        });
        $builder->upsert(['email' => 'foo'], 'id');

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('affectingStatement')->with('insert into `users` (`email`) values (?) on duplicate key update `email` = values(`email`)', ['foo']);
        $builder->beforeQuery(function ($builder) {
            $builder->from('users');
        });
        $builder->upsert(['email' => 'foo'], 'id');
    }

    public function testPreservedAreAppliedByUpdate() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update "users" set "email" = ? where "id" = ?', ['foo', 1]);
        $builder->from('users')->beforeQuery(function ($builder) {
            $builder->where('id', 1);
        });
        $builder->update(['email' => 'foo']);
    }

    public function testPreservedAreAppliedByDelete() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('deleteWithQuery')->with('delete from "users"', []);
        $builder->beforeQuery(function ($builder) {
            $builder->from('users');
        });
        $builder->delete();
    }

    public function testPreservedAreAppliedByTruncate() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('statement')->with('truncate "users"', []);
        $builder->beforeQuery(function ($builder) {
            $builder->from('users');
        });
        $builder->truncate();
    }

    public function testPreservedAreAppliedByExists() {
        $builder = $this->getBuilder();
        $builder->getConnection()->expects('select')->with('select exists(select * from "users") as "exists"', [], true);
        $builder->beforeQuery(function ($builder) {
            $builder->from('users');
        });
        $builder->exists();
    }

    public function testPostgresInsertGetId() {
        $builder = $this->getPostgresBuilder();
        $builder->getProcessor()->expects('processInsertGetId')->with($builder, 'insert into "users" ("email") values (?) returning "id"', ['foo'], 'id')->andReturn(1);
        $result = $builder->from('users')->insertGetId(['email' => 'foo'], 'id');
        $this->assertEquals(1, $result);
    }

    public function testMySqlWrapping() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users');
        $this->assertSame('select * from `users`', $builder->toSql());
    }

    public function testMySqlUpdateWrappingJson() {
        $grammar = new CDatabase_Query_Grammar_MySqlGrammar();
        $processor = m::mock(CDatabase_Query_Processor::class);
        $connection = $this->getConnection($grammar, $processor);

        $connection->shouldReceive('updateWithQuery')->once()->with(
                'update `users` set `name` = json_set(`name`, \'$."first_name"\', ?), `name` = json_set(`name`, \'$."last_name"\', ?) where `active` = ?',
                ['John', 'Doe', 1]
            );

        $builder = new CDatabase_Query_Builder($connection, $grammar, $processor);

        $builder->from('users')->where('active', '=', 1)->update(['name->first_name' => 'John', 'name->last_name' => 'Doe']);
    }

    public function testMySqlUpdateWrappingNestedJson() {
        $grammar = new CDatabase_Query_Grammar_MySqlGrammar();
        $processor = m::mock(CDatabase_Query_Processor::class);
        $connection = $this->getConnection($grammar, $processor);

        $connection->shouldReceive('updateWithQuery')->once()->with(
                'update `users` set `meta` = json_set(`meta`, \'$."name"."first_name"\', ?), `meta` = json_set(`meta`, \'$."name"."last_name"\', ?) where `active` = ?',
                ['John', 'Doe', 1]
            );

        $builder = new CDatabase_Query_Builder($connection, $grammar, $processor);

        $builder->from('users')->where('active', '=', 1)->update(['meta->name->first_name' => 'John', 'meta->name->last_name' => 'Doe']);
    }

    public function testMySqlUpdateWrappingJsonArray() {
        $grammar = new CDatabase_Query_Grammar_MySqlGrammar();
        $processor = m::mock(CDatabase_Query_Processor::class);
        $connection = $this->getConnection($grammar, $processor);

        $connection->shouldReceive('updateWithQuery')->once()->with(
                'update `users` set `options` = ?, `meta` = json_set(`meta`, \'$."tags"\', cast(? as json)), `group_id` = 45, `created_at` = ? where `active` = ?',
                [
                    json_encode(['2fa' => false, 'presets' => ['laravel', 'vue']]),
                    json_encode(['white', 'large']),
                    new DateTime('2019-08-06'),
                    1,
                ]
            );

        $builder = new CDatabase_Query_Builder($connection, $grammar, $processor);
        $builder->from('users')->where('active', 1)->update([
            'options' => ['2fa' => false, 'presets' => ['laravel', 'vue']],
            'meta->tags' => ['white', 'large'],
            'group_id' => new CDatabase_Query_Expression('45'),
            'created_at' => new DateTime('2019-08-06'),
        ]);
    }

    public function testMySqlUpdateWrappingJsonPathArrayIndex() {
        $grammar = new CDatabase_Query_Grammar_MySqlGrammar();
        $processor = m::mock(CDatabase_Query_Processor::class);
        $connection = $this->getConnection($grammar, $processor);

        $connection->shouldReceive('updateWithQuery')->once()->with(
                'update `users` set `options` = json_set(`options`, \'$[1]."2fa"\', false), `meta` = json_set(`meta`, \'$."tags"[0][2]\', ?) where `active` = ?',
                [
                    'large',
                    1,
                ]
            );

        $builder = new CDatabase_Query_Builder($connection, $grammar, $processor);
        $builder->from('users')->where('active', 1)->update([
            'options->[1]->2fa' => false,
            'meta->tags[0][2]' => 'large',
        ]);
    }

    public function testMySqlUpdateWithJsonPreparesBindingsCorrectly() {
        $grammar = new CDatabase_Query_Grammar_MySqlGrammar();
        $processor = m::mock(CDatabase_Query_Processor::class);
        $connection = $this->getConnection($grammar, $processor);

        $connection->expects('updateWithQuery')
            ->with(
                'update `users` set `options` = json_set(`options`, \'$."enable"\', false), `updated_at` = ? where `id` = ?',
                ['2015-05-26 22:02:06', 0]
            );
        $builder = new CDatabase_Query_Builder($connection, $grammar, $processor);
        $builder->from('users')->where('id', '=', 0)->update(['options->enable' => false, 'updated_at' => '2015-05-26 22:02:06']);

        $connection->expects('updateWithQuery')
            ->with(
                'update `users` set `options` = json_set(`options`, \'$."size"\', ?), `updated_at` = ? where `id` = ?',
                [45, '2015-05-26 22:02:06', 0]
            );
        $builder = new CDatabase_Query_Builder($connection, $grammar, $processor);
        $builder->from('users')->where('id', '=', 0)->update(['options->size' => 45, 'updated_at' => '2015-05-26 22:02:06']);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update `users` set `options` = json_set(`options`, \'$."size"\', ?)', [null]);
        $builder->from('users')->update(['options->size' => null]);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->expects('updateWithQuery')->with('update `users` set `options` = json_set(`options`, \'$."size"\', 45)', []);
        $builder->from('users')->update(['options->size' => new CDatabase_Query_Expression('45')]);
    }

    public function testPostgresUpdateWrappingJson() {
        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('updateWithQuery')
            ->with('update "users" set "options" = jsonb_set("options"::jsonb, \'{"name","first_name"}\', ?)', ['"John"']);
        $builder->from('users')->update(['users.options->name->first_name' => 'John']);

        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('updateWithQuery')
            ->with('update "users" set "options" = jsonb_set("options"::jsonb, \'{"language"}\', \'null\')', []);
        $builder->from('users')->update(['options->language' => new CDatabase_Query_Expression("'null'")]);
    }

    public function testPostgresUpdateWrappingJsonArray() {
        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('updateWithQuery')
            ->with('update "users" set "options" = ?, "meta" = jsonb_set("meta"::jsonb, \'{"tags"}\', ?), "group_id" = 45, "created_at" = ?', [
                json_encode(['2fa' => false, 'presets' => ['laravel', 'vue']]),
                json_encode(['white', 'large']),
                new DateTime('2019-08-06'),
            ]);

        $builder->from('users')->update([
            'options' => ['2fa' => false, 'presets' => ['laravel', 'vue']],
            'meta->tags' => ['white', 'large'],
            'group_id' => new CDatabase_Query_Expression('45'),
            'created_at' => new DateTime('2019-08-06'),
        ]);
    }

    public function testPostgresUpdateWrappingJsonPathArrayIndex() {
        $builder = $this->getPostgresBuilder();
        $builder->getConnection()->expects('updateWithQuery')
            ->with('update "users" set "options" = jsonb_set("options"::jsonb, \'{1,"2fa"}\', ?), "meta" = jsonb_set("meta"::jsonb, \'{"tags",0,2}\', ?) where ("options"->1->\'2fa\')::jsonb = \'true\'::jsonb', [
                'false',
                '"large"',
            ]);

        $builder->from('users')->where('options->[1]->2fa', true)->update([
            'options->[1]->2fa' => false,
            'meta->tags[0][2]' => 'large',
        ]);
    }

    public function testSQLiteUpdateWrappingJsonArray() {
        $builder = $this->getSQLiteBuilder();

        $builder->getConnection()->expects('updateWithQuery')
            ->with('update "users" set "options" = ?, "group_id" = 45, "created_at" = ?', [
                json_encode(['2fa' => false, 'presets' => ['laravel', 'vue']]),
                new DateTime('2019-08-06'),
            ]);

        $builder->from('users')->update([
            'options' => ['2fa' => false, 'presets' => ['laravel', 'vue']],
            'group_id' => new CDatabase_Query_Expression('45'),
            'created_at' => new DateTime('2019-08-06'),
        ]);
    }

    public function testSQLiteUpdateWrappingNestedJsonArray() {
        $builder = $this->getSQLiteBuilder();
        $builder->getConnection()->expects('updateWithQuery')
            ->with('update "users" set "group_id" = 45, "created_at" = ?, "options" = json_patch(ifnull("options", json(\'{}\')), json(?))', [
                new DateTime('2019-08-06'),
                json_encode(['name' => 'Taylor', 'security' => ['2fa' => false, 'presets' => ['laravel', 'vue']], 'sharing' => ['twitter' => 'username']]),
            ]);

        $builder->from('users')->update([
            'options->name' => 'Taylor',
            'group_id' => new CDatabase_Query_Expression('45'),
            'options->security' => ['2fa' => false, 'presets' => ['laravel', 'vue']],
            'options->sharing->twitter' => 'username',
            'created_at' => new DateTime('2019-08-06'),
        ]);
    }

    public function testMySqlWrappingJsonWithString() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->sku', '=', 'foo-bar');
        $this->assertSame('select * from `users` where json_unquote(json_extract(`items`, \'$."sku"\')) = ?', $builder->toSql());
        $this->assertCount(1, $builder->getRawBindings()['where']);
        $this->assertSame('foo-bar', $builder->getRawBindings()['where'][0]);
    }

    public function testMySqlWrappingJsonWithInteger() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price', '=', 1);
        $this->assertSame('select * from `users` where json_unquote(json_extract(`items`, \'$."price"\')) = ?', $builder->toSql());
    }

    public function testMySqlWrappingJsonWithDouble() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price', '=', 1.5);
        $this->assertSame('select * from `users` where json_unquote(json_extract(`items`, \'$."price"\')) = ?', $builder->toSql());
    }

    public function testMySqlWrappingJsonWithBoolean() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true);
        $this->assertSame('select * from `users` where json_extract(`items`, \'$."available"\') = true', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where(new CDatabase_Query_Expression("items->'$.available'"), '=', true);
        $this->assertSame("select * from `users` where items->'$.available' = true", $builder->toSql());
    }

    public function testMySqlWrappingJsonWithBooleanAndIntegerThatLooksLikeOne() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true)->where('items->active', '=', false)->where('items->number_available', '=', 0);
        $this->assertSame('select * from `users` where json_extract(`items`, \'$."available"\') = true and json_extract(`items`, \'$."active"\') = false and json_unquote(json_extract(`items`, \'$."number_available"\')) = ?', $builder->toSql());
    }

    public function testJsonPathEscaping() {
        $expectedWithJsonEscaped = <<<'SQL'
select json_unquote(json_extract(`json`, '$."''))#"'))
SQL;

        $builder = $this->getMySqlBuilder();
        $builder->select("json->'))#");
        $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select("json->\'))#");
        $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select("json->\\'))#");
        $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select("json->\\\'))#");
        $this->assertEquals($expectedWithJsonEscaped, $builder->toSql());
    }

    public function testMySqlWrappingJson() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereRaw('items->\'$."price"\' = 1');
        $this->assertSame('select * from `users` where items->\'$."price"\' = 1', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
        $this->assertSame('select json_unquote(json_extract(`items`, \'$."price"\')) from `users` where json_unquote(json_extract(`users`.`items`, \'$."price"\')) = ? order by json_unquote(json_extract(`items`, \'$."price"\')) asc', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
        $this->assertSame('select * from `users` where json_unquote(json_extract(`items`, \'$."price"."in_usd"\')) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
        $this->assertSame('select * from `users` where json_unquote(json_extract(`items`, \'$."price"."in_usd"\')) = ? and json_unquote(json_extract(`items`, \'$."age"\')) = ?', $builder->toSql());
    }

    public function testPostgresWrappingJson() {
        $builder = $this->getPostgresBuilder();
        $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
        $this->assertSame('select "items"->>\'price\' from "users" where "users"."items"->>\'price\' = ? order by "items"->>\'price\' asc', $builder->toSql());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
        $this->assertSame('select * from "users" where "items"->\'price\'->>\'in_usd\' = ?', $builder->toSql());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
        $this->assertSame('select * from "users" where "items"->\'price\'->>\'in_usd\' = ? and "items"->>\'age\' = ?', $builder->toSql());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('items->prices->0', '=', 1)->where('items->age', '=', 2);
        $this->assertSame('select * from "users" where "items"->\'prices\'->>0 = ? and "items"->>\'age\' = ?', $builder->toSql());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true);
        $this->assertSame('select * from "users" where ("items"->\'available\')::jsonb = \'true\'::jsonb', $builder->toSql());
    }

    public function testSqlServerWrappingJson() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
        $this->assertSame('select json_value([items], \'$."price"\') from [users] where json_value([users].[items], \'$."price"\') = ? order by json_value([items], \'$."price"\') asc', $builder->toSql());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
        $this->assertSame('select * from [users] where json_value([items], \'$."price"."in_usd"\') = ?', $builder->toSql());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
        $this->assertSame('select * from [users] where json_value([items], \'$."price"."in_usd"\') = ? and json_value([items], \'$."age"\') = ?', $builder->toSql());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true);
        $this->assertSame('select * from [users] where json_value([items], \'$."available"\') = \'true\'', $builder->toSql());
    }

    public function testSqliteWrappingJson() {
        $builder = $this->getSQLiteBuilder();
        $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
        $this->assertSame('select json_extract("items", \'$."price"\') from "users" where json_extract("users"."items", \'$."price"\') = ? order by json_extract("items", \'$."price"\') asc', $builder->toSql());

        $builder = $this->getSQLiteBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
        $this->assertSame('select * from "users" where json_extract("items", \'$."price"."in_usd"\') = ?', $builder->toSql());

        $builder = $this->getSQLiteBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
        $this->assertSame('select * from "users" where json_extract("items", \'$."price"."in_usd"\') = ? and json_extract("items", \'$."age"\') = ?', $builder->toSql());

        $builder = $this->getSQLiteBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true);
        $this->assertSame('select * from "users" where json_extract("items", \'$."available"\') = true', $builder->toSql());
    }

    public function testSQLiteOrderBy() {
        $builder = $this->getSQLiteBuilder();
        $builder->select('*')->from('users')->orderBy('email', 'desc');
        $this->assertSame('select * from "users" order by "email" desc', $builder->toSql());
    }

    public function testMySqlSoundsLikeOperator() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('name', 'sounds like', 'John Doe');
        $this->assertSame('select * from `users` where `name` sounds like ?', $builder->toSql());
        $this->assertEquals(['John Doe'], $builder->getBindings());
    }

    public function testMergeWheresCanMergeWheresAndBindings() {
        $builder = $this->getBuilder();
        $builder->wheres = ['foo'];
        $builder->mergeWheres(['wheres'], [12 => 'foo', 13 => 'bar']);
        $this->assertEquals(['foo', 'wheres'], $builder->wheres);
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function testPrepareValueAndOperator() {
        $builder = $this->getBuilder();
        [$value, $operator] = $builder->prepareValueAndOperator('>', '20');
        $this->assertSame('>', $value);
        $this->assertSame('20', $operator);

        $builder = $this->getBuilder();
        [$value, $operator] = $builder->prepareValueAndOperator('>', '20', true);
        $this->assertSame('20', $value);
        $this->assertSame('=', $operator);
    }

    public function testPrepareValueAndOperatorExpectException() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Illegal operator and value combination.');

        $builder = $this->getBuilder();
        $builder->prepareValueAndOperator(null, 'like');
    }

    public function testDynamicWhereIsNotGreedy() {
        $method = 'whereIosVersionAndAndroidVersionOrOrientation';
        $parameters = ['6.1', '4.2', 'Vertical'];
        $builder = m::mock(CDatabase_Query_Builder::class)->makePartial();

        $builder->expects('where')->with('ios_version', '=', '6.1', 'and')->andReturnSelf();
        $builder->expects('where')->with('android_version', '=', '4.2', 'and')->andReturnSelf();
        $builder->expects('where')->with('orientation', '=', 'Vertical', 'or')->andReturnSelf();

        $builder->dynamicWhere($method, $parameters);
    }

    public function testCallTriggersDynamicWhere() {
        $builder = $this->getBuilder();

        $this->assertEquals($builder, $builder->whereFooAndBar('baz', 'qux'));
        $this->assertCount(2, $builder->wheres);
    }

    public function testMySqlLock() {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
        $this->assertSame('select * from `foo` where `bar` = ? for update', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
        $this->assertSame('select * from `foo` where `bar` = ? lock in share mode', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock('lock in share mode');
        $this->assertSame('select * from `foo` where `bar` = ? lock in share mode', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function testPostgresLock() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
        $this->assertSame('select * from "foo" where "bar" = ? for update', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
        $this->assertSame('select * from "foo" where "bar" = ? for share', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock('for key share');
        $this->assertSame('select * from "foo" where "bar" = ? for key share', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function testSqlServerLock() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
        $this->assertSame('select * from [foo] with(rowlock,updlock,holdlock) where [bar] = ?', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
        $this->assertSame('select * from [foo] with(rowlock,holdlock) where [bar] = ?', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock('with(holdlock)');
        $this->assertSame('select * from [foo] with(holdlock) where [bar] = ?', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function testSelectWithLockUsesWritePdo() {
        $builder = $this->getMySqlBuilderWithProcessor();
        $builder->getConnection()->expects('select')
            ->with(m::any(), m::any(), false);
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock()->get();

        $builder = $this->getMySqlBuilderWithProcessor();
        $builder->getConnection()->expects('select')
            ->with(m::any(), m::any(), false);
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false)->get();
    }

    public function testAddBindingWithArrayMergesBindings() {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar']);
        $builder->addBinding(['baz']);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testAddBindingWithArrayMergesBindingsInCorrectOrder() {
        $builder = $this->getBuilder();
        $builder->addBinding(['bar', 'baz'], 'having');
        $builder->addBinding(['foo'], 'where');
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testMergeBuilders() {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar']);
        $otherBuilder = $this->getBuilder();
        $otherBuilder->addBinding(['baz']);
        $builder->mergeBindings($otherBuilder);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testMergeBuildersBindingOrder() {
        $builder = $this->getBuilder();
        $builder->addBinding('foo', 'where');
        $builder->addBinding('baz', 'having');
        $otherBuilder = $this->getBuilder();
        $otherBuilder->addBinding('bar', 'where');
        $builder->mergeBindings($otherBuilder);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testSubSelect() {
        $expectedSql = 'select "foo", "bar", (select "baz" from "two" where "subkey" = ?) as "sub" from "one" where "key" = ?';
        $expectedBindings = ['subval', 'val'];

        $builder = $this->getPostgresBuilder();
        $builder->from('one')->select(['foo', 'bar'])->where('key', '=', 'val');
        $builder->selectSub(function ($query) {
            $query->from('two')->select('baz')->where('subkey', '=', 'subval');
        }, 'sub');
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->from('one')->select(['foo', 'bar'])->where('key', '=', 'val');
        $subBuilder = $this->getPostgresBuilder();
        $subBuilder->from('two')->select('baz')->where('subkey', '=', 'subval');
        $builder->selectSub($subBuilder, 'sub');
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());

        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getPostgresBuilder();
        $builder->selectSub(['foo'], 'sub');
    }

    public function testSubSelectResetBindings() {
        $builder = $this->getPostgresBuilder();
        $builder->from('one')->selectSub(function ($query) {
            $query->from('two')->select('baz')->where('subkey', '=', 'subval');
        }, 'sub');

        $this->assertSame('select (select "baz" from "two" where "subkey" = ?) as "sub" from "one"', $builder->toSql());
        $this->assertEquals(['subval'], $builder->getBindings());

        $builder->select('*');

        $this->assertSame('select * from "one"', $builder->toSql());
        $this->assertSame([], $builder->getBindings());
    }

    public function testSelect() {
        $builder = $this->getBuilder();
        $builder->from('one')->select([
            'two',
            'three' => 'threee as threeee',
            'four' => $this->getBuilder()->from('tbl')->select('col'),
            'five' => new CDatabase_Query_Expression('1 + 1'),
        ]);

        $this->assertSame('select "two", "threee" as "threeee", (select "col" from "tbl") as "four", 1 + 1 from "one"', $builder->toSql());
    }

    public function testSqlServerWhereDate() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-09-23');
        $this->assertSame('select * from [users] where cast([created_at] as date) = ?', $builder->toSql());
        $this->assertEquals([0 => '2015-09-23'], $builder->getBindings());
    }

    public function testUppercaseLeadingBooleansAreRemoved() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'AND');
        $this->assertSame('select * from "users" where "name" = ?', $builder->toSql());
    }

    public function testLowercaseLeadingBooleansAreRemoved() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'and');
        $this->assertSame('select * from "users" where "name" = ?', $builder->toSql());
    }

    public function testCaseInsensitiveLeadingBooleansAreRemoved() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'And');
        $this->assertSame('select * from "users" where "name" = ?', $builder->toSql());
    }

    public function testTableValuedFunctionAsTableInSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users()');
        $this->assertSame('select * from [users]()', $builder->toSql());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users(1,2)');
        $this->assertSame('select * from [users](1,2)', $builder->toSql());
    }

    public function testChunkWithLastChunkComplete() {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = c::collect(['foo1', 'foo2']);
        $chunk2 = c::collect(['foo3', 'foo4']);
        $chunk3 = c::collect([]);

        $builder->expects('offset')->with(0)->andReturnSelf();
        $builder->expects('offset')->with(2)->andReturnSelf();
        $builder->expects('offset')->with(4)->andReturnSelf();
        $builder->expects('limit')->times(3)->with(2)->andReturnSelf();
        $builder->expects('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->expects('doSomething')->with($chunk1);
        $callbackAssertor->expects('doSomething')->with($chunk2);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function testChunkWithLastChunkPartial() {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = c::collect(['foo1', 'foo2']);
        $chunk2 = c::collect(['foo3']);

        $builder->expects('offset')->with(0)->andReturnSelf();
        $builder->expects('offset')->with(2)->andReturnSelf();
        $builder->expects('limit')->times(2)->with(2)->andReturnSelf();
        $builder->expects('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->expects('doSomething')->with($chunk1);
        $callbackAssertor->expects('doSomething')->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function testChunkCanBeStoppedByReturningFalse() {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = c::collect(['foo1', 'foo2']);
        $chunk2 = c::collect(['foo3']);
        $builder->expects('offset')->with(0)->andReturnSelf();
        $builder->expects('limit')->with(2)->andReturnSelf();
        $builder->expects('get')->times(1)->andReturn($chunk1);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->expects('doSomething')->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);

            return false;
        });
    }

    public function testChunkByIdOnArrays() {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = c::collect([['someIdField' => 1], ['someIdField' => 2]]);
        $chunk2 = c::collect([['someIdField' => 10], ['someIdField' => 11]]);
        $chunk3 = c::collect([]);
        $builder->expects('forPageAfterId')->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->expects('forPageAfterId')->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->expects('forPageAfterId')->with(2, 11, 'someIdField')->andReturnSelf();
        $builder->expects('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->expects('doSomething')->with($chunk1);
        $callbackAssertor->expects('doSomething')->with($chunk2);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testChunkPaginatesUsingIdWithLastChunkComplete() {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = c::collect([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
        $chunk2 = c::collect([(object) ['someIdField' => 10], (object) ['someIdField' => 11]]);
        $chunk3 = c::collect([]);
        $builder->expects('forPageAfterId')->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->expects('forPageAfterId')->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->expects('forPageAfterId')->with(2, 11, 'someIdField')->andReturnSelf();
        $builder->expects('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->expects('doSomething')->with($chunk1);
        $callbackAssertor->expects('doSomething')->with($chunk2);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testChunkPaginatesUsingIdWithLastChunkPartial() {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = c::collect([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
        $chunk2 = c::collect([(object) ['someIdField' => 10]]);
        $builder->expects('forPageAfterId')->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->expects('forPageAfterId')->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->expects('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->expects('doSomething')->with($chunk1);
        $callbackAssertor->expects('doSomething')->with($chunk2);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testChunkPaginatesUsingIdWithAlias() {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = c::collect([(object) ['table_id' => 1], (object) ['table_id' => 10]]);
        $chunk2 = c::collect([]);
        $builder->expects('forPageAfterId')->with(2, 0, 'table.id')->andReturnSelf();
        $builder->expects('forPageAfterId')->with(2, 10, 'table.id')->andReturnSelf();
        $builder->expects('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->expects('doSomething')->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'table.id', 'table_id');
    }

    public function testChunkPaginatesUsingIdDesc() {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'desc'];

        $chunk1 = c::collect([(object) ['someIdField' => 10], (object) ['someIdField' => 1]]);
        $chunk2 = c::collect([]);
        $builder->expects('forPageBeforeId')->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->expects('forPageBeforeId')->with(2, 1, 'someIdField')->andReturnSelf();
        $builder->expects('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = m::mock(stdClass::class);
        $callbackAssertor->expects('doSomething')->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunkByIdDesc(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testPaginate() {
        $perPage = 16;
        $columns = ['test'];
        $pageName = 'page-name';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = c::collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->expects('getCountForPagination')->andReturn(2);
        $builder->expects('forPage')->with($page, $perPage)->andReturnSelf();
        $builder->expects('get')->andReturn($results);

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->paginate($perPage, $columns, $pageName, $page);

        $this->assertEquals(new CPagination_LengthAwarePaginator($results, 2, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testPaginateWithDefaultArguments() {
        $perPage = 15;
        $pageName = 'page';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = c::collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->expects('getCountForPagination')->andReturn(2);
        $builder->expects('forPage')->with($page, $perPage)->andReturnSelf();
        $builder->expects('get')->andReturn($results);

        CPagination_AbstractPaginator::currentPageResolver(function () {
            return 1;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->paginate();

        $this->assertEquals(new CPagination_LengthAwarePaginator($results, 2, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testPaginateWhenNoResults() {
        $perPage = 15;
        $pageName = 'page';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = [];

        $builder->expects('getCountForPagination')->andReturn(0);
        $builder->shouldNotReceive('forPage');
        $builder->shouldNotReceive('get');

        CPagination_AbstractPaginator::currentPageResolver(function () {
            return 1;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->paginate();

        $this->assertEquals(new CPagination_LengthAwarePaginator($results, 0, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testPaginateWithSpecificColumns() {
        $perPage = 16;
        $columns = ['id', 'name'];
        $pageName = 'page-name';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = c::collect([['id' => 3, 'name' => 'Taylor'], ['id' => 5, 'name' => 'Mohamed']]);

        $builder->expects('getCountForPagination')->andReturn(2);
        $builder->expects('forPage')->with($page, $perPage)->andReturnSelf();
        $builder->expects('get')->andReturn($results);

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->paginate($perPage, $columns, $pageName, $page);

        $this->assertEquals(new CPagination_LengthAwarePaginator($results, 2, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testPaginateWithTotalOverride() {
        $perPage = 16;
        $columns = ['id', 'name'];
        $pageName = 'page-name';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = c::collect([['id' => 3, 'name' => 'Taylor'], ['id' => 5, 'name' => 'Mohamed']]);

        $builder->shouldReceive('getCountForPagination')->never();
        $builder->expects('forPage')->with($page, $perPage)->andReturnSelf();
        $builder->expects('get')->andReturn($results);

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->paginate($perPage, $columns, $pageName, $page, 10);

        $this->assertEquals(10, $result->total());
    }

    public function testCursorPaginate() {
        $perPage = 16;
        $columns = ['test'];
        $cursorName = 'cursor-name';
        $cursor = new CPagination_Cursor(['test' => 'bar']);
        $builder = $this->getMockQueryBuilder();
        $builder->from('foobar')->orderBy('test');
        $builder->expects('newQuery')->andReturnUsing(function () use ($builder) {
            return new CDatabase_Query_Builder($builder->connection, $builder->grammar, $builder->processor);
        });

        $path = 'http://foo.bar?cursor='.$cursor->encode();

        $results = c::collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->expects('get')->andReturnUsing(function () use ($builder, $results) {
            $this->assertSame(
                'select * from "foobar" where ("test" > ?) order by "test" asc limit 17',
                $builder->toSql());
            $this->assertEquals(['bar'], $builder->bindings['where']);

            return $results;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

        $this->assertEquals(new CPagination_CursorPaginator($results, $perPage, $cursor, [
            'path' => $path,
            'cursorName' => $cursorName,
            'parameters' => ['test'],
        ]), $result);
    }

    public function testCursorPaginateMultipleOrderColumns() {
        $perPage = 16;
        $columns = ['test', 'another'];
        $cursorName = 'cursor-name';
        $cursor = new CPagination_Cursor(['test' => 'bar', 'another' => 'foo']);
        $builder = $this->getMockQueryBuilder();
        $builder->from('foobar')->orderBy('test')->orderBy('another');
        $builder->expects('newQuery')->andReturnUsing(function () use ($builder) {
            return new CDatabase_Query_Builder($builder->connection, $builder->grammar, $builder->processor);
        });

        $path = 'http://foo.bar?cursor='.$cursor->encode();

        $results = c::collect([['test' => 'foo', 'another' => 1], ['test' => 'bar', 'another' => 2]]);

        $builder->expects('get')->andReturnUsing(function () use ($builder, $results) {
            $this->assertSame(
                'select * from "foobar" where ("test" > ? or ("test" = ? and ("another" > ?))) order by "test" asc, "another" asc limit 17',
                $builder->toSql()
            );
            $this->assertEquals(['bar', 'bar', 'foo'], $builder->bindings['where']);

            return $results;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

        $this->assertEquals(new CPagination_CursorPaginator($results, $perPage, $cursor, [
            'path' => $path,
            'cursorName' => $cursorName,
            'parameters' => ['test', 'another'],
        ]), $result);
    }

    public function testCursorPaginateWithDefaultArguments() {
        $perPage = 15;
        $cursorName = 'cursor';
        $cursor = new CPagination_Cursor(['test' => 'bar']);
        $builder = $this->getMockQueryBuilder();
        $builder->from('foobar')->orderBy('test');
        $builder->expects('newQuery')->andReturnUsing(function () use ($builder) {
            return new CDatabase_Query_Builder($builder->connection, $builder->grammar, $builder->processor);
        });

        $path = 'http://foo.bar?cursor='.$cursor->encode();

        $results = c::collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->expects('get')->andReturnUsing(function () use ($builder, $results) {
            $this->assertSame(
                'select * from "foobar" where ("test" > ?) order by "test" asc limit 16',
                $builder->toSql());
            $this->assertEquals(['bar'], $builder->bindings['where']);

            return $results;
        });

        CPagination_CursorPaginator::currentCursorResolver(function () use ($cursor) {
            return $cursor;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->cursorPaginate();

        $this->assertEquals(new CPagination_CursorPaginator($results, $perPage, $cursor, [
            'path' => $path,
            'cursorName' => $cursorName,
            'parameters' => ['test'],
        ]), $result);
    }

    public function testCursorPaginateWhenNoResults() {
        $perPage = 15;
        $cursorName = 'cursor';
        $builder = $this->getMockQueryBuilder()->orderBy('test');
        $path = 'http://foo.bar?cursor=3';

        $results = [];

        $builder->expects('get')->andReturn($results);

        CPagination_CursorPaginator::currentCursorResolver(function () {
            return null;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->cursorPaginate();

        $this->assertEquals(new CPagination_CursorPaginator($results, $perPage, null, [
            'path' => $path,
            'cursorName' => $cursorName,
            'parameters' => ['test'],
        ]), $result);
    }

    public function testCursorPaginateWithSpecificColumns() {
        $perPage = 16;
        $columns = ['id', 'name'];
        $cursorName = 'cursor-name';
        $cursor = new CPagination_Cursor(['id' => 2]);
        $builder = $this->getMockQueryBuilder();
        $builder->from('foobar')->orderBy('id');
        $builder->expects('newQuery')->andReturnUsing(function () use ($builder) {
            return new CDatabase_Query_Builder($builder->connection, $builder->grammar, $builder->processor);
        });

        $path = 'http://foo.bar?cursor=3';

        $results = c::collect([['id' => 3, 'name' => 'Taylor'], ['id' => 5, 'name' => 'Mohamed']]);

        $builder->expects('get')->andReturnUsing(function () use ($builder, $results) {
            $this->assertSame(
                'select * from "foobar" where ("id" > ?) order by "id" asc limit 17',
                $builder->toSql());
            $this->assertEquals([2], $builder->bindings['where']);

            return $results;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

        $this->assertEquals(new CPagination_CursorPaginator($results, $perPage, $cursor, [
            'path' => $path,
            'cursorName' => $cursorName,
            'parameters' => ['id'],
        ]), $result);
    }

    public function testCursorPaginateWithMixedOrders() {
        $perPage = 16;
        $columns = ['foo', 'bar', 'baz'];
        $cursorName = 'cursor-name';
        $cursor = new CPagination_Cursor(['foo' => 1, 'bar' => 2, 'baz' => 3]);
        $builder = $this->getMockQueryBuilder();
        $builder->from('foobar')->orderBy('foo')->orderByDesc('bar')->orderBy('baz');
        $builder->expects('newQuery')->andReturnUsing(function () use ($builder) {
            return new CDatabase_Query_Builder($builder->connection, $builder->grammar, $builder->processor);
        });

        $path = 'http://foo.bar?cursor='.$cursor->encode();

        $results = c::collect([['foo' => 1, 'bar' => 2, 'baz' => 4], ['foo' => 1, 'bar' => 1, 'baz' => 1]]);

        $builder->expects('get')->andReturnUsing(function () use ($builder, $results) {
            $this->assertSame(
                'select * from "foobar" where ("foo" > ? or ("foo" = ? and ("bar" < ? or ("bar" = ? and ("baz" > ?))))) order by "foo" asc, "bar" desc, "baz" asc limit 17',
                $builder->toSql()
            );
            $this->assertEquals([1, 1, 2, 2, 3], $builder->bindings['where']);

            return $results;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->cursorPaginate($perPage, $columns, $cursorName, $cursor);

        $this->assertEquals(new CPagination_CursorPaginator($results, $perPage, $cursor, [
            'path' => $path,
            'cursorName' => $cursorName,
            'parameters' => ['foo', 'bar', 'baz'],
        ]), $result);
    }

    public function testCursorPaginateWithDynamicColumnInSelectRaw() {
        $perPage = 15;
        $cursorName = 'cursor';
        $cursor = new CPagination_Cursor(['test' => 'bar']);
        $builder = $this->getMockQueryBuilder();
        $builder->from('foobar')->select('*')->selectRaw('(CONCAT(firstname, \' \', lastname)) as test')->orderBy('test');
        $builder->expects('newQuery')->andReturnUsing(function () use ($builder) {
            return new CDatabase_Query_Builder($builder->connection, $builder->grammar, $builder->processor);
        });

        $path = 'http://foo.bar?cursor='.$cursor->encode();

        $results = c::collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->expects('get')->andReturnUsing(function () use ($builder, $results) {
            $this->assertSame(
                'select *, (CONCAT(firstname, \' \', lastname)) as test from "foobar" where ((CONCAT(firstname, \' \', lastname)) > ?) order by "test" asc limit 16',
                $builder->toSql());
            $this->assertEquals(['bar'], $builder->bindings['where']);

            return $results;
        });

        CPagination_CursorPaginator::currentCursorResolver(function () use ($cursor) {
            return $cursor;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->cursorPaginate();

        $this->assertEquals(new CPagination_CursorPaginator($results, $perPage, $cursor, [
            'path' => $path,
            'cursorName' => $cursorName,
            'parameters' => ['test'],
        ]), $result);
    }

    public function testCursorPaginateWithDynamicColumnWithCastInSelectRaw() {
        $perPage = 15;
        $cursorName = 'cursor';
        $cursor = new CPagination_Cursor(['test' => 'bar']);
        $builder = $this->getMockQueryBuilder();
        $builder->from('foobar')->select('*')->selectRaw('(CAST(CONCAT(firstname, \' \', lastname) as VARCHAR)) as test')->orderBy('test');
        $builder->expects('newQuery')->andReturnUsing(function () use ($builder) {
            return new CDatabase_Query_Builder($builder->connection, $builder->grammar, $builder->processor);
        });

        $path = 'http://foo.bar?cursor='.$cursor->encode();

        $results = c::collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->expects('get')->andReturnUsing(function () use ($builder, $results) {
            $this->assertSame(
                'select *, (CAST(CONCAT(firstname, \' \', lastname) as VARCHAR)) as test from "foobar" where ((CAST(CONCAT(firstname, \' \', lastname) as VARCHAR)) > ?) order by "test" asc limit 16',
                $builder->toSql());
            $this->assertEquals(['bar'], $builder->bindings['where']);

            return $results;
        });

        CPagination_CursorPaginator::currentCursorResolver(function () use ($cursor) {
            return $cursor;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->cursorPaginate();

        $this->assertEquals(new CPagination_CursorPaginator($results, $perPage, $cursor, [
            'path' => $path,
            'cursorName' => $cursorName,
            'parameters' => ['test'],
        ]), $result);
    }

    public function testCursorPaginateWithDynamicColumnInSelectSub() {
        $perPage = 15;
        $cursorName = 'cursor';
        $cursor = new CPagination_Cursor(['test' => 'bar']);
        $builder = $this->getMockQueryBuilder();
        $builder->from('foobar')->select('*')->selectSub('CONCAT(firstname, \' \', lastname)', 'test')->orderBy('test');
        $builder->expects('newQuery')->andReturnUsing(function () use ($builder) {
            return new CDatabase_Query_Builder($builder->connection, $builder->grammar, $builder->processor);
        });

        $path = 'http://foo.bar?cursor='.$cursor->encode();

        $results = c::collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->expects('get')->andReturnUsing(function () use ($builder, $results) {
            $this->assertSame(
                'select *, (CONCAT(firstname, \' \', lastname)) as "test" from "foobar" where ((CONCAT(firstname, \' \', lastname)) > ?) order by "test" asc limit 16',
                $builder->toSql());
            $this->assertEquals(['bar'], $builder->bindings['where']);

            return $results;
        });

        CPagination_CursorPaginator::currentCursorResolver(function () use ($cursor) {
            return $cursor;
        });

        CPagination_AbstractPaginator::currentPathResolver(function () use ($path) {
            return $path;
        });

        $result = $builder->cursorPaginate();

        $this->assertEquals(new CPagination_CursorPaginator($results, $perPage, $cursor, [
            'path' => $path,
            'cursorName' => $cursorName,
            'parameters' => ['test'],
        ]), $result);
    }

    public function testWhereRowValuesArityMismatch() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The number of columns must match the number of values');

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereRowValues(['last_update'], '<', [1, 2]);
    }

    public function testWhereJsonContainsSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereJsonContains('options', true);
        $this->assertSame('select * from [users] where ? in (select [value] from openjson([options]))', $builder->toSql());
        $this->assertEquals(['true'], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereJsonContains('users.options->languages', 'en');
        $this->assertSame('select * from [users] where ? in (select [value] from openjson([users].[options], \'$."languages"\'))', $builder->toSql());
        $this->assertEquals(['en'], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContains('options->languages', new CDatabase_Query_Expression("'en'"));
        $this->assertSame('select * from [users] where [id] = ? or \'en\' in (select [value] from openjson([options], \'$."languages"\'))', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testWhereJsonDoesntContainPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereJsonDoesntContain('options->languages', ['en']);
        $this->assertSame('select * from "users" where not ("options"->\'languages\')::jsonb @> ?', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContain('options->languages', new CDatabase_Query_Expression("'[\"en\"]'"));
        $this->assertSame('select * from "users" where "id" = ? or not ("options"->\'languages\')::jsonb @> \'["en"]\'', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testWhereJsonDoesntContainSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereJsonDoesntContain('options->languages', 'en');
        $this->assertSame('select * from [users] where not ? in (select [value] from openjson([options], \'$."languages"\'))', $builder->toSql());
        $this->assertEquals(['en'], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContain('options->languages', new CDatabase_Query_Expression("'en'"));
        $this->assertSame('select * from [users] where [id] = ? or not \'en\' in (select [value] from openjson([options], \'$."languages"\'))', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testWhereJsonLengthPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereJsonLength('options', 0);
        $this->assertSame('select * from "users" where jsonb_array_length(("options")::jsonb) = ?', $builder->toSql());
        $this->assertEquals([0], $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->whereJsonLength('users.options->languages', '>', 0);
        $this->assertSame('select * from "users" where jsonb_array_length(("users"."options"->\'languages\')::jsonb) > ?', $builder->toSql());
        $this->assertEquals([0], $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', new CDatabase_Query_Expression('0'));
        $this->assertSame('select * from "users" where "id" = ? or jsonb_array_length(("options"->\'languages\')::jsonb) = 0', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', '>', new CDatabase_Query_Expression('0'));
        $this->assertSame('select * from "users" where "id" = ? or jsonb_array_length(("options"->\'languages\')::jsonb) > 0', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testWhereJsonLengthSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereJsonLength('options', 0);
        $this->assertSame('select * from [users] where (select count(*) from openjson([options])) = ?', $builder->toSql());
        $this->assertEquals([0], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->whereJsonLength('users.options->languages', '>', 0);
        $this->assertSame('select * from [users] where (select count(*) from openjson([users].[options], \'$."languages"\')) > ?', $builder->toSql());
        $this->assertEquals([0], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', new CDatabase_Query_Expression('0'));
        $this->assertSame('select * from [users] where [id] = ? or (select count(*) from openjson([options], \'$."languages"\')) = 0', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());

        $builder = $this->getSqlServerBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonLength('options->languages', '>', new CDatabase_Query_Expression('0'));
        $this->assertSame('select * from [users] where [id] = ? or (select count(*) from openjson([options], \'$."languages"\')) > 0', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testFrom() {
        $builder = $this->getBuilder();
        $builder->from($this->getBuilder()->from('users'), 'u');
        $this->assertSame('select * from (select * from "users") as "u"', $builder->toSql());

        $builder = $this->getBuilder();
        $eloquentBuilder = new CModel_Query($this->getBuilder());
        $builder->from($eloquentBuilder->from('users'), 'u');
        $this->assertSame('select * from (select * from "users") as "u"', $builder->toSql());
    }

    public function testFromSubWithPrefix() {
        $builder = $this->getBuilder('prefix_');
        $builder->fromSub(function ($query) {
            $query->select(new CDatabase_Query_Expression('max(last_seen_at) as last_seen_at'))->from('user_sessions')->where('foo', '=', '1');
        }, 'sessions')->where('bar', '<', '10');
        $this->assertSame('select * from (select max(last_seen_at) as last_seen_at from "prefix_user_sessions" where "foo" = ?) as "prefix_sessions" where "bar" < ?', $builder->toSql());
        $this->assertEquals(['1', '10'], $builder->getBindings());
    }

    public function testFromRawOnSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->fromRaw('dbo.[SomeNameWithRoundBrackets (test)]');
        $this->assertSame('select * from dbo.[SomeNameWithRoundBrackets (test)]', $builder->toSql());
    }

    public function testFromQuestionMarkOperatorOnPostgres() {
        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('roles', '?', 'superuser');
        $this->assertSame('select * from "users" where "roles" ?? ?', $builder->toSql());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('roles', '?|', 'superuser');
        $this->assertSame('select * from "users" where "roles" ??| ?', $builder->toSql());

        $builder = $this->getPostgresBuilder();
        $builder->select('*')->from('users')->where('roles', '?&', 'superuser');
        $this->assertSame('select * from "users" where "roles" ??& ?', $builder->toSql());
    }

    public function testUseIndexMySql() {
        $builder = $this->getMySqlBuilder();
        $builder->select('foo')->from('users')->useIndex('test_index');
        $this->assertSame('select `foo` from `users` use index(test_index)', $builder->toSql());
    }

    public function testUseIndexSqlite() {
        $builder = $this->getSQLiteBuilder();
        $builder->select('foo')->from('users')->useIndex('test_index');
        $this->assertSame('select "foo" from "users"', $builder->toSql());
    }

    public function testUseIndexSqlServer() {
        $builder = $this->getSqlServerBuilder();
        $builder->select('foo')->from('users')->useIndex('test_index');
        $this->assertSame('select [foo] from [users]', $builder->toSql());
    }

    public function testClone() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $clone = $builder->clone()->where('email', 'foo');

        $this->assertNotSame($builder, $clone);
        $this->assertSame('select * from "users"', $builder->toSql());
        $this->assertSame('select * from "users" where "email" = ?', $clone->toSql());
    }

    public function testCloneWithout() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', 'foo')->orderBy('email');
        $clone = $builder->cloneWithout(['orders']);

        $this->assertSame('select * from "users" where "email" = ? order by "email" asc', $builder->toSql());
        $this->assertSame('select * from "users" where "email" = ?', $clone->toSql());
    }

    public function testCloneWithoutBindings() {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', 'foo')->orderBy('email');
        $clone = $builder->cloneWithout(['wheres'])->cloneWithoutBindings(['where']);

        $this->assertSame('select * from "users" where "email" = ? order by "email" asc', $builder->toSql());
        $this->assertEquals([0 => 'foo'], $builder->getBindings());

        $this->assertSame('select * from "users" order by "email" asc', $clone->toSql());
        $this->assertSame([], $clone->getBindings());
    }
}
