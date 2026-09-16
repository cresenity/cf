<?php

use Carbon\Carbon;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DatabaseConnectionTest extends TestCase {
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void {
        // properti statis, jadi wajib dikembalikan supaya tidak bocor ke test lain
        CDatabase_Connection::$compileQueryLog = true;
        m::close();
    }

    public function testSettingDefaultCallsGetDefaultGrammar() {
        $connection = $this->getMockConnection();
        $mock = m::mock(stdClass::class);
        $connection->expects($this->once())->method('getDefaultQueryGrammar')->willReturn($mock);
        $connection->useDefaultQueryGrammar();
        $this->assertEquals($mock, $connection->getQueryGrammar());
    }

    public function testSettingDefaultCallsGetDefaultPostProcessor() {
        $connection = $this->getMockConnection();
        $mock = m::mock(stdClass::class);
        $connection->expects($this->once())->method('getDefaultPostProcessor')->willReturn($mock);
        $connection->useDefaultPostProcessor();
        $this->assertEquals($mock, $connection->getPostProcessor());
    }

    public function testSelectOneCallsSelectAndReturnsSingleResult() {
        $connection = $this->getMockConnection(['select']);
        $connection->expects($this->once())->method('select')->with('foo', ['bar' => 'baz'])->willReturn(['foo']);
        $this->assertSame('foo', $connection->selectOne('foo', ['bar' => 'baz']));
    }

    public function testScalarCallsSelectOneAndReturnsSingleResult() {
        $connection = $this->getMockConnection(['selectOne']);
        $connection->expects($this->once())->method('selectOne')->with('select count(*) from tbl')->willReturn((object) ['count(*)' => 5]);
        $this->assertSame(5, $connection->scalar('select count(*) from tbl'));
    }

    public function testScalarThrowsExceptionIfMultipleColumnsAreSelected() {
        $connection = $this->getMockConnection(['selectOne']);
        $connection->expects($this->once())->method('selectOne')->with('select a, b from tbl')->willReturn((object) ['a' => 'a', 'b' => 'b']);
        $this->expectException(CDatabase_Exception_MultipleColumnsSelectedException::class);
        $connection->scalar('select a, b from tbl');
    }

    public function testScalarReturnsNullIfUnderlyingSelectReturnsNoRows() {
        $connection = $this->getMockConnection(['selectOne']);
        $connection->expects($this->once())->method('selectOne')->with('select foo from tbl where 0=1')->willReturn(null);
        $this->assertNull($connection->scalar('select foo from tbl where 0=1'));
    }

    public function testSelectProperlyCallsPDO() {
        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
        $writePdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
        $writePdo->expects($this->never())->method('prepare');
        $statement = $this->getMockBuilder('PDOStatement')
            ->onlyMethods(['setFetchMode', 'execute', 'fetchAll', 'bindValue'])
            ->getMock();
        $statement->expects($this->once())->method('setFetchMode');
        $statement->expects($this->once())->method('bindValue')->with('foo', 'bar', 2);
        $statement->expects($this->once())->method('execute');
        $statement->expects($this->once())->method('fetchAll')->willReturn(['boom']);
        $pdo->expects($this->once())->method('prepare')->with('foo')->willReturn($statement);
        $mock = $this->getMockConnection(['prepareBindings'], $writePdo);
        $mock->setReadPdo($pdo);
        $mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo' => 'bar']))->willReturn(['foo' => 'bar']);
        $results = $mock->select('foo', ['foo' => 'bar']);
        $this->assertEquals(['boom'], $results);
        $log = $mock->getQueryLog();
        $this->assertSame('foo', $log[0]['query']);
        $this->assertEquals(['foo' => 'bar'], $log[0]['bindings']);
        $this->assertIsNumeric($log[0]['time']);
    }

    /**
     * escapeString() used to always quote the query-log 'compiled' entry's
     * bindings through the write PDO (getPdo()), even for a select() that
     * ran entirely on the read connection - so merely logging a read query
     * could open a write connection just to format its log line. Now the
     * compiled entry is quoted against whichever connection actually ran
     * the query.
     */
    public function testSelectLogsItsCompiledEntryAgainstTheReadConnectionNotTheWriteOne() {
        $readPdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare', 'quote'])->getMock();
        $writePdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare', 'quote'])->getMock();
        $writePdo->expects($this->never())->method('quote');
        $readPdo->expects($this->once())->method('quote')->with('bar')->willReturn("'bar'");

        $statement = $this->getMockBuilder('PDOStatement')
            ->onlyMethods(['setFetchMode', 'execute', 'fetchAll', 'bindValue'])
            ->getMock();
        $readPdo->method('prepare')->willReturn($statement);

        $mock = $this->getMockConnection(['prepareBindings'], $writePdo);
        $mock->setReadPdo($readPdo);
        $mock->method('prepareBindings')->willReturn(['bar']);

        $mock->select('select * from tbl where x = ?', ['bar']);

        $log = $mock->getQueryLog();
        $this->assertSame("select * from tbl where x = 'bar'", $log[0]['compiled']);
    }

    /**
     * A write query's compiled log entry must keep quoting against the
     * write connection - only select()/query()/selectResultSets()/cursor()
     * (the read-capable paths) pass $useReadPdo through.
     */
    public function testStatementStillLogsItsCompiledEntryAgainstTheWriteConnection() {
        $writePdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare', 'quote'])->getMock();
        $writePdo->expects($this->once())->method('quote')->with('bar')->willReturn("'bar'");

        $statement = $this->getMockBuilder('PDOStatement')
            ->onlyMethods(['execute', 'bindValue'])
            ->getMock();
        $writePdo->method('prepare')->willReturn($statement);

        $mock = $this->getMockConnection(['prepareBindings'], $writePdo);
        $mock->method('prepareBindings')->willReturn(['bar']);

        $mock->statement('update tbl set x = ?', ['bar']);

        $log = $mock->getQueryLog();
        $this->assertSame("update tbl set x = 'bar'", $log[0]['compiled']);
    }

    public function testSelectResultsetsReturnsMultipleRowset() {
        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
        // Kunci `compiled` di query log menyulih tiap bind lewat PDO::quote pada
        // koneksi tulis, yang di sini sengaja tidak hidup.
        CDatabase_Connection::$compileQueryLog = false;
        $writePdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
        $writePdo->expects($this->never())->method('prepare');
        $statement = $this->getMockBuilder('PDOStatement')
            ->onlyMethods(['setFetchMode', 'execute', 'fetchAll', 'bindValue', 'nextRowset'])
            ->getMock();
        $statement->expects($this->once())->method('setFetchMode');
        $statement->expects($this->once())->method('bindValue')->with(1, 'foo', 2);
        $statement->expects($this->once())->method('execute');
        $statement->expects($this->atLeastOnce())->method('fetchAll')->willReturn(['boom']);
        $statement->expects($this->atLeastOnce())->method('nextRowset')->will($this->returnCallback(function () {
            static $i = 1;

            return ++$i <= 2;
        }));
        $pdo->expects($this->once())->method('prepare')->with('CALL a_procedure(?)')->willReturn($statement);
        $mock = $this->getMockConnection(['prepareBindings'], $writePdo);
        $mock->setReadPdo($pdo);
        $mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo']))->willReturn(['foo']);
        $results = $mock->selectResultsets('CALL a_procedure(?)', ['foo']);
        $this->assertEquals([['boom'], ['boom']], $results);
        $log = $mock->getQueryLog();
        $this->assertSame('CALL a_procedure(?)', $log[0]['query']);
        $this->assertEquals(['foo'], $log[0]['bindings']);
        $this->assertIsNumeric($log[0]['time']);
    }

    public function testInsertCallsTheStatementMethod() {
        $connection = $this->getMockConnection(['statement']);
        $connection->expects($this->once())->method('statement')->with($this->equalTo('foo'), $this->equalTo(['bar']))->willReturn('baz');
        $results = $connection->insertWithQuery('foo', ['bar']);
        $this->assertSame('baz', $results);
    }

    public function testUpdateCallsTheAffectingStatementMethod() {
        $connection = $this->getMockConnection(['affectingStatement']);
        $connection->expects($this->once())->method('affectingStatement')->with($this->equalTo('foo'), $this->equalTo(['bar']))->willReturn('baz');
        $results = $connection->updateWithQuery('foo', ['bar']);
        $this->assertSame('baz', $results);
    }

    public function testDeleteCallsTheAffectingStatementMethod() {
        $connection = $this->getMockConnection(['affectingStatement']);
        $connection->expects($this->once())->method('affectingStatement')->with($this->equalTo('foo'), $this->equalTo(['bar']))->willReturn(true);
        $results = $connection->deleteWithQuery('foo', ['bar']);
        $this->assertTrue($results);
    }

    public function testStatementProperlyCallsPDO() {
        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
        $statement = $this->getMockBuilder('PDOStatement')->onlyMethods(['execute', 'bindValue'])->getMock();
        $statement->expects($this->once())->method('bindValue')->with(1, 'bar', 2);
        $statement->expects($this->once())->method('execute')->willReturn(true);
        $pdo->expects($this->once())->method('prepare')->with($this->equalTo('foo'))->willReturn($statement);
        $mock = $this->getMockConnection(['prepareBindings'], $pdo);
        $mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['bar']))->willReturn(['bar']);
        $results = $mock->statement('foo', ['bar']);
        $this->assertTrue($results);
        $log = $mock->getQueryLog();
        $this->assertSame('foo', $log[0]['query']);
        $this->assertEquals(['bar'], $log[0]['bindings']);
        $this->assertIsNumeric($log[0]['time']);
    }

    public function testAffectingStatementProperlyCallsPDO() {
        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['prepare'])->getMock();
        $statement = $this->getMockBuilder('PDOStatement')->onlyMethods(['execute', 'rowCount', 'bindValue'])->getMock();
        $statement->expects($this->once())->method('bindValue')->with('foo', 'bar', 2);
        $statement->expects($this->once())->method('execute');
        $statement->expects($this->once())->method('rowCount')->willReturn(42);
        $pdo->expects($this->once())->method('prepare')->with('foo')->willReturn($statement);
        $mock = $this->getMockConnection(['prepareBindings'], $pdo);
        $mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo' => 'bar']))->willReturn(['foo' => 'bar']);
        $results = $mock->updateWithQuery('foo', ['foo' => 'bar']);
        $this->assertSame(42, $results);
        $log = $mock->getQueryLog();
        $this->assertSame('foo', $log[0]['query']);
        $this->assertEquals(['foo' => 'bar'], $log[0]['bindings']);
        $this->assertIsNumeric($log[0]['time']);
    }

    public function testTransactionLevelNotIncrementedOnTransactionException() {
        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        /** @var \Mockery\MockInterface|DatabaseConnectionTestMockPDO $pdo */
        $pdo->expects($this->once())->method('beginTransaction')->will($this->throwException(new Exception()));
        $connection = $this->getMockConnection([], $pdo);

        try {
            $connection->beginTransaction();
        } catch (Exception $e) {
            $this->assertEquals(0, $connection->transactionLevel());
        }
    }

    public function testBeginTransactionMethodRetriesOnFailure() {
        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        /** @var \Mockery\MockInterface|\Mockery\Mock|DatabaseConnectionTestMockPDO $pdo */
        $pdo->method('beginTransaction')
            ->willReturnOnConsecutiveCalls($this->throwException(new ErrorException('server has gone away')), true);
        $connection = $this->getMockConnection(['reconnect'], $pdo);
        $connection->expects($this->once())->method('reconnect');
        $connection->beginTransaction();
        $this->assertEquals(1, $connection->transactionLevel());
    }

    public function testBeginTransactionMethodReconnectsMissingConnection() {
        $connection = $this->getMockConnection();
        $connection->setReconnector(function ($connection) {
            $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
            $connection->setPdo($pdo);
        });
        $connection->disconnect();
        $connection->beginTransaction();
        $this->assertEquals(1, $connection->transactionLevel());
    }

    public function testBeginTransactionMethodNeverRetriesIfWithinTransaction() {
        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        /** @var \Mockery\MockInterface|DatabaseConnectionTestMockPDO $pdo */
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('exec')->will($this->throwException(new Exception()));
        $connection = $this->getMockConnection(['reconnect'], $pdo);
        $queryGrammar = $this->createMock(CDatabase_Query_Grammar::class);
        /** @var \Mockery\MockInterface|CDatabase_Query_Grammar $queryGrammar */
        $queryGrammar->expects($this->once())->method('compileSavepoint')->willReturn('trans1');
        $queryGrammar->expects($this->once())->method('supportsSavepoints')->willReturn(true);
        $connection->setQueryGrammar($queryGrammar);
        $connection->expects($this->never())->method('reconnect');
        $connection->beginTransaction();
        $this->assertEquals(1, $connection->transactionLevel());

        try {
            $connection->beginTransaction();
        } catch (Exception $e) {
            $this->assertEquals(1, $connection->transactionLevel());
        }
    }

    public function testSwapPDOWithOpenTransactionResetsTransactionLevel() {
        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        /** @var \Mockery\MockInterface|DatabaseConnectionTestMockPDO $pdo */
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $connection = $this->getMockConnection([], $pdo);
        $connection->beginTransaction();
        $connection->disconnect();
        $this->assertEquals(0, $connection->transactionLevel());
    }

    public function testBeganTransactionFiresEventsIfSet() {
        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        $connection = $this->getMockConnection(['getName'], $pdo);

        $connection->expects($this->any())->method('getName')->willReturn('name');
        $connection->setEventDispatcher($events = m::mock(CEvent_Dispatcher::class));
        /** @var \Mockery\MockInterface|CEvent_Dispatcher $events */
        $events->shouldReceive('dispatch')->once()->with(m::type(CDatabase_Event_Transaction_Beginning::class));
        $connection->beginTransaction();
    }

    public function testCommittedFiresEventsIfSet() {
        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        $connection = $this->getMockConnection(['getName'], $pdo);
        $connection->expects($this->any())->method('getName')->willReturn('name');
        $connection->setEventDispatcher($events = m::mock(CEvent_Dispatcher::class));
        /** @var \Mockery\MockInterface|CEvent_Dispatcher $events */
        $events->shouldReceive('dispatch')->once()->with(m::type(CDatabase_Event_Transaction_Committed::class));
        $connection->commit();
    }

    public function testCommittingFiresEventsIfSet() {
        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        $connection = $this->getMockConnection(['getName', 'transactionLevel'], $pdo);
        $connection->expects($this->any())->method('getName')->willReturn('name');
        $connection->expects($this->any())->method('transactionLevel')->willReturn(1);
        $connection->setEventDispatcher($events = m::mock(CEvent_Dispatcher::class));
        /** @var \Mockery\MockInterface|CEvent_Dispatcher $events */
        $events->shouldReceive('dispatch')->once()->with(m::type(CDatabase_Event_Transaction_Committing::class));
        $events->shouldReceive('dispatch')->once()->with(m::type(CDatabase_Event_Transaction_Committed::class));
        $connection->commit();
    }

    public function testRollBackedFiresEventsIfSet() {
        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        $connection = $this->getMockConnection(['getName'], $pdo);
        $connection->expects($this->any())->method('getName')->willReturn('name');
        $connection->beginTransaction();
        $connection->setEventDispatcher($events = m::mock(CEvent_Dispatcher::class));
        /** @var \Mockery\MockInterface|CEvent_Dispatcher $events */
        $events->shouldReceive('dispatch')->once()->with(m::type(CDatabase_Event_Transaction_RolledBack::class));
        $connection->rollBack();
    }

    public function testRedundantRollBackFiresNoEvent() {
        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        $connection = $this->getMockConnection(['getName'], $pdo);
        $connection->expects($this->any())->method('getName')->willReturn('name');
        $connection->setEventDispatcher($events = m::mock(CEvent_Dispatcher::class));
        /** @var \Mockery\MockInterface|CEvent_Dispatcher $events */
        $events->shouldNotReceive('dispatch');
        $connection->rollBack();
    }

    public function testTransactionMethodRunsSuccessfully() {
        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['beginTransaction', 'commit'])->getMock();
        $mock = $this->getMockConnection([], $pdo);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');
        $result = $mock->transaction(function ($db) {
            return $db;
        });
        $this->assertEquals($mock, $result);
    }

    public function testTransactionRetriesOnSerializationFailure() {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Serialization failure');

        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['beginTransaction', 'commit', 'rollBack'])->getMock();
        $mock = $this->getMockConnection([], $pdo);
        $pdo->expects($this->exactly(3))->method('commit')->will($this->throwException(new DatabaseConnectionTestMockPDOException('Serialization failure', '40001')));
        $pdo->expects($this->exactly(3))->method('beginTransaction');
        $pdo->expects($this->never())->method('rollBack');
        $mock->transaction(function () {
        }, 3);
    }

    public function testTransactionMethodRetriesOnDeadlock() {
        $this->expectException(CDatabase_Exception_QueryException::class);
        $this->expectExceptionMessage('Deadlock found when trying to get lock (Connection: conn, SQL: )');

        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['inTransaction', 'beginTransaction', 'commit', 'rollBack'])->getMock();
        $mock = $this->getMockConnection([], $pdo);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->exactly(3))->method('beginTransaction');
        $pdo->expects($this->exactly(3))->method('rollBack');
        $pdo->expects($this->never())->method('commit');
        $mock->transaction(function () {
            throw new CDatabase_Exception_QueryException('conn', '', [], new Exception('Deadlock found when trying to get lock'));
        }, 3);
    }

    public function testTransactionMethodRollsbackAndThrows() {
        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['inTransaction', 'beginTransaction', 'commit', 'rollBack'])->getMock();
        $mock = $this->getMockConnection([], $pdo);
        // $pdo->expects($this->once())->method('inTransaction');
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');
        $pdo->expects($this->never())->method('commit');

        try {
            $mock->transaction(function () {
                throw new Exception('foo');
            });
        } catch (Exception $e) {
            $this->assertSame('foo', $e->getMessage());
        }
    }

    public function testOnLostConnectionPDOIsNotSwappedWithinATransaction() {
        $this->expectException(CDatabase_Exception_QueryException::class);
        $this->expectExceptionMessage('server has gone away (Connection: , SQL: foo)');

        $pdo = m::mock(PDO::class);
        /** @var \Mockery\MockInterface|PDO $pdo */
        $pdo->shouldReceive('beginTransaction')->once();
        $statement = m::mock(PDOStatement::class);
        /** @var \Mockery\MockInterface|PDOStatement $statement */
        $pdo->shouldReceive('prepare')->once()->andReturn($statement);
        $statement->shouldReceive('execute')->once()->andThrow(new PDOException('server has gone away'));

        $connection = new CDatabase_Connection($pdo);
        $connection->beginTransaction();
        $connection->statement('foo');
    }

    public function testOnLostConnectionPDOIsSwappedOutsideTransaction() {
        $pdo = m::mock(PDO::class);
        /** @var \Mockery\MockInterface|PDO $pdo */
        $statement = m::mock(PDOStatement::class);
        /** @var \Mockery\MockInterface|PDOStatement $statement */
        $statement->shouldReceive('execute')->once()->andThrow(new PDOException('server has gone away'));
        $statement->shouldReceive('execute')->once()->andReturn(true);

        $pdo->shouldReceive('prepare')->twice()->andReturn($statement);

        $connection = new CDatabase_Connection($pdo);

        $called = false;

        $connection->setReconnector(function ($connection) use (&$called) {
            $called = true;
        });

        $this->assertTrue($connection->statement('foo'));

        $this->assertTrue($called);
    }

    public function testRunMethodRetriesOnFailure() {
        $method = (new ReflectionClass(CDatabase_Connection::class))->getMethod('run');
        $method->setAccessible(true);

        $pdo = $this->createMock(DatabaseConnectionTestMockPDO::class);
        $mock = $this->getMockConnection(['tryAgainIfCausedByLostConnection'], $pdo);
        $mock->expects($this->once())->method('tryAgainIfCausedByLostConnection');

        $method->invokeArgs($mock, ['', [], function () {
            throw new CDatabase_Exception_QueryException('', '', [], new Exception());
        }]);
    }

    public function testRunMethodNeverRetriesIfWithinTransaction() {
        $this->expectException(CDatabase_Exception_QueryException::class);
        $this->expectExceptionMessage('(Connection: conn, SQL: ) (Connection: , SQL: )');

        $method = (new ReflectionClass(CDatabase_Connection::class))->getMethod('run');
        $method->setAccessible(true);

        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['beginTransaction'])->getMock();
        $mock = $this->getMockConnection(['tryAgainIfCausedByLostConnection'], $pdo);
        $pdo->expects($this->once())->method('beginTransaction');
        $mock->expects($this->never())->method('tryAgainIfCausedByLostConnection');
        $mock->beginTransaction();

        $method->invokeArgs($mock, ['', [], function () {
            throw new CDatabase_Exception_QueryException('conn', '', [], new Exception());
        }]);
    }

    public function testFromCreatesNewQueryBuilder() {
        $conn = $this->getMockConnection();
        $conn->setQueryGrammar(m::mock(CDatabase_Query_Grammar::class));
        $conn->setPostProcessor(m::mock(CDatabase_Query_Processor::class));
        $builder = $conn->table('users');
        $this->assertInstanceOf(CDatabase_Query_Builder::class, $builder);
        $this->assertSame('users', $builder->from);
    }

    public function testPrepareBindings() {
        $date = m::mock(DateTime::class);
        /** @var \Mockery\MockInterface|DateTime $date */
        $date->shouldReceive('format')->once()->with('foo')->andReturn('bar');
        $bindings = ['test' => $date];
        $conn = $this->getMockConnection();
        $grammar = m::mock(CDatabase_Query_Grammar::class);
        /** @var \Mockery\MockInterface|CDatabase_Query_Grammar $grammar */
        $grammar->shouldReceive('getDateFormat')->once()->andReturn('foo');
        $conn->setQueryGrammar($grammar);
        $result = $conn->prepareBindings($bindings);
        $this->assertEquals(['test' => 'bar'], $result);
    }

    public function testLogQueryFiresEventsIfSet() {
        $connection = $this->getMockConnection();
        $connection->logQuery('foo', [], time());
        $connection->setEventDispatcher($events = m::mock(CEvent_Dispatcher::class));
        /** @var \Mockery\MockInterface|CEvent_Dispatcher $events */
        $events->shouldReceive('dispatch')->once()->with(m::type(CDatabase_Event_QueryExecuted::class));
        $connection->logQuery('foo', [], null);
    }

    public function testBeforeExecutingHooksCanBeRegistered() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The callback was fired');

        $connection = $this->getMockConnection();
        $connection->beforeExecuting(function () {
            throw new Exception('The callback was fired');
        });
        $connection->select('foo bar', ['baz']);
    }

    public function testPretendOnlyLogsQueries() {
        $connection = $this->getMockConnection();
        $queries = $connection->pretend(function ($connection) {
            $connection->select('foo bar', ['baz']);
        });
        $this->assertSame('foo bar', $queries[0]['query']);
        $this->assertEquals(['baz'], $queries[0]['bindings']);
    }

    /**
     * A Carbon instance used to reach the PDO quote() call only via an implicit
     * __toString() cast inside cstr::contains()/preg_match() - fragile, and dependent
     * on Carbon::setToStringFormat() never being changed process-wide. escape() now
     * converts it explicitly up front, the same conversion compileBinds() already
     * applies to Carbon bind values (#-13850).
     */
    public function testEscapeConvertsCarbonToItsStringFormBeforeQuoting() {
        $carbon = Carbon::create(2026, 9, 16, 10, 30, 0);
        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['quote'])->getMock();
        $pdo->expects($this->once())->method('quote')->with('2026-09-16 10:30:00')->willReturn("'2026-09-16 10:30:00'");
        $connection = $this->getMockConnection([], $pdo);

        $this->assertSame("'2026-09-16 10:30:00'", $connection->escape($carbon));
    }

    /**
     * Plain DateTime/DateTimeImmutable don't define __toString() (unlike Carbon), so
     * before this fix passing one straight to escape() fataled with "Object of class
     * DateTime could not be converted to string" instead of escaping it. escape() now
     * falls back to format('Y-m-d H:i:s') for these.
     */
    public function testEscapeConvertsPlainDateTimeToItsStringFormBeforeQuoting() {
        $date = new DateTime('2026-09-16 10:30:00');
        $pdo = $this->getMockBuilder(DatabaseConnectionTestMockPDO::class)->onlyMethods(['quote'])->getMock();
        $pdo->expects($this->once())->method('quote')->with('2026-09-16 10:30:00')->willReturn("'2026-09-16 10:30:00'");
        $connection = $this->getMockConnection([], $pdo);

        $this->assertSame("'2026-09-16 10:30:00'", $connection->escape($date));
    }

    public function testSchemaBuilderCanBeCreated() {
        $connection = $this->getMockConnection();
        $schema = $connection->getSchemaBuilder();
        $this->assertInstanceOf(CDatabase_Schema_Builder::class, $schema);
        $this->assertSame($connection, $schema->getConnection());
    }

    /**
     * @param array $methods
     * @param [type] $pdo
     *
     * @return \Mockery\MockInterface|CDatabase_Connection $connection
     */
    protected function getMockConnection($methods = [], $pdo = null) {
        $pdo = $pdo ?: new DatabaseConnectionTestMockPDO();
        $defaults = ['getDefaultQueryGrammar', 'getDefaultPostProcessor', 'getDefaultSchemaGrammar'];
        $connection = $this->getMockBuilder(CDatabase_Connection::class)->onlyMethods(array_merge($defaults, $methods))->setConstructorArgs([$pdo])->getMock();
        $connection->enableQueryLog();

        return $connection;
    }
}
//@codingStandardsIgnoreStart
class DatabaseConnectionTestMockPDO extends PDO {
    public function __construct() {
    }
}

class DatabaseConnectionTestMockPDOException extends PDOException {
    /**
     * Overrides Exception::__construct, which casts $code to integer, so that we can create
     * an exception with a string $code consistent with the real PDOException behavior.
     *
     * @param null|string $message
     * @param null|string $code
     *
     * @return void
     */
    public function __construct($message = null, $code = null) {
        $this->message = $message;
        $this->code = $code;
    }
}
