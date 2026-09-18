<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * CDatabase_Connection transaksi - padanan suite hulu DatabaseTransactionsTest, dijalankan pada
 * SQLite in-memory (tanpa menyentuh basis data mana pun): tingkat transaksi, savepoint bersarang,
 * rollback saat exception, dan pencatatan ke CDatabase_TransactionManager.
 */
class DatabaseTransactionsTest extends TestCase {
    /**
     * @var CDatabase_Connection
     */
    protected $connection;

    protected function setUp(): void {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('butuh ekstensi pdo_sqlite');
        }
        $this->connection = new CDatabase_Connection_Pdo_SqliteConnection(new PDO('sqlite::memory:'), ':memory:', '', ['name' => 'uji', 'driver' => 'sqlite']);
        $this->connection->statement('create table cf_test_transactions (id integer primary key autoincrement, name varchar(50) null, value varchar(50) null)');
    }

    protected function tearDown(): void {
        $this->connection->disconnect();
        m::close();
    }

    /**
     * @return CDatabase_Query_Builder
     */
    protected function table() {
        return $this->connection->table('cf_test_transactions');
    }

    public function testTransactionIsRecordedAndCommitted() {
        $manager = m::mock(new CDatabase_TransactionManager());
        $manager->shouldReceive('begin')->once()->with($this->connection->getName(), 1);
        $manager->shouldReceive('commit')->once()->with($this->connection->getName());
        $this->connection->setTransactionManager($manager);
        $this->table()->insert(['name' => 'zain', 'value' => '1']);

        $result = $this->connection->transaction(function ($connection) {
            $this->assertSame($this->connection, $connection);
            $this->assertSame(1, $connection->transactionLevel());
            $this->table()->where('name', 'zain')->update(['value' => '2']);

            return 'hasil';
        });

        $this->assertSame('hasil', $result, 'transaction() mengembalikan nilai closure');
        $this->assertSame(0, $this->connection->transactionLevel());
        $this->assertSame('2', (string) $this->table()->where('name', 'zain')->value('value'));
    }

    public function testTransactionIsRecordedAndCommittedUsingTheSeparateMethods() {
        $manager = m::mock(new CDatabase_TransactionManager());
        $manager->shouldReceive('begin')->once()->with($this->connection->getName(), 1);
        $manager->shouldReceive('commit')->once()->with($this->connection->getName());
        $this->connection->setTransactionManager($manager);

        $this->connection->beginTransaction();
        $this->assertSame(1, $this->connection->transactionLevel());
        $this->assertTrue($this->connection->inTransaction());
        $this->table()->insert(['name' => 'zain', 'value' => '1']);
        $this->connection->commit();

        $this->assertSame(0, $this->connection->transactionLevel());
        $this->assertFalse($this->connection->inTransaction());
        $this->assertSame(1, $this->table()->count());
    }

    public function testNestedTransactionIsRecordedAndCommitted() {
        $manager = m::mock(new CDatabase_TransactionManager());
        $manager->shouldReceive('begin')->once()->with($this->connection->getName(), 1);
        $manager->shouldReceive('begin')->once()->with($this->connection->getName(), 2);
        $manager->shouldReceive('commit')->once()->with($this->connection->getName());
        $this->connection->setTransactionManager($manager);

        $this->connection->transaction(function () {
            $this->table()->insert(['name' => 'luar', 'value' => '1']);
            $this->connection->transaction(function () {
                $this->assertSame(2, $this->connection->transactionLevel(), 'savepoint bersarang menaikkan tingkat');
                $this->table()->insert(['name' => 'dalam', 'value' => '1']);
            });
            $this->assertSame(1, $this->connection->transactionLevel());
        });

        $this->assertSame(2, $this->table()->count());
    }

    public function testTransactionIsRolledBackOnException() {
        $manager = m::mock(new CDatabase_TransactionManager());
        $manager->shouldReceive('begin')->once()->with($this->connection->getName(), 1);
        $manager->shouldReceive('rollback')->once()->with($this->connection->getName(), 0);
        $this->connection->setTransactionManager($manager);
        $this->table()->insert(['name' => 'zain', 'value' => '1']);

        try {
            $this->connection->transaction(function () {
                $this->table()->where('name', 'zain')->update(['value' => '2']);

                throw new RuntimeException('batal');
            });
            $this->fail('exception harus diteruskan ke pemanggil');
        } catch (RuntimeException $e) {
            $this->assertSame('batal', $e->getMessage());
        }

        $this->assertSame(0, $this->connection->transactionLevel());
        $this->assertSame('1', (string) $this->table()->where('name', 'zain')->value('value'), 'perubahan di dalam transaksi dibatalkan');
    }

    public function testTransactionIsRolledBackUsingSeparateMethods() {
        $this->connection->beginTransaction();
        $this->table()->insert(['name' => 'zain', 'value' => '1']);
        $this->assertSame(1, $this->table()->count());

        $this->connection->rollback();

        $this->assertSame(0, $this->connection->transactionLevel());
        $this->assertSame(0, $this->table()->count());
    }

    public function testNestedTransactionsAreRolledBackToTheSavepoint() {
        $this->connection->beginTransaction();
        $this->table()->insert(['name' => 'luar', 'value' => '1']);

        $this->connection->beginTransaction();
        $this->table()->insert(['name' => 'dalam', 'value' => '1']);
        $this->assertSame(2, $this->connection->transactionLevel());
        $this->connection->rollback();

        $this->assertSame(1, $this->connection->transactionLevel());
        $this->assertSame(['luar'], $this->table()->pluck('name')->all(), 'hanya savepoint dalam yang dibatalkan');

        $this->connection->commit();
        $this->assertSame(1, $this->table()->count());
    }

    public function testRollbackToALevel() {
        $this->connection->beginTransaction();
        $this->connection->beginTransaction();
        $this->connection->beginTransaction();
        $this->assertSame(3, $this->connection->transactionLevel());

        $this->connection->rollback(1);
        $this->assertSame(1, $this->connection->transactionLevel());

        $this->connection->rollback(0);
        $this->assertSame(0, $this->connection->transactionLevel());
    }

    public function testAfterCommitCallbacksRunWhenTheOutermostTransactionCommits() {
        $manager = new CDatabase_TransactionManager();
        $this->connection->setTransactionManager($manager);
        $ran = [];

        $this->connection->transaction(function () use (&$ran) {
            $this->connection->afterCommit(function () use (&$ran) {
                $ran[] = 'luar';
            });
            $this->connection->transaction(function () use (&$ran) {
                $this->connection->afterCommit(function () use (&$ran) {
                    $ran[] = 'dalam';
                });
            });
            $this->assertSame([], $ran, 'belum jalan selama transaksi luar terbuka');
        });

        $this->assertSame(['luar', 'dalam'], $ran);
    }

    public function testAfterCommitCallbacksAreDroppedOnRollback() {
        $manager = new CDatabase_TransactionManager();
        $this->connection->setTransactionManager($manager);
        $ran = false;

        try {
            $this->connection->transaction(function () use (&$ran) {
                $this->connection->afterCommit(function () use (&$ran) {
                    $ran = true;
                });

                throw new RuntimeException('batal');
            });
        } catch (RuntimeException $e) {
        }

        $this->assertFalse($ran);
        $this->assertCount(0, $manager->getTransactions());
    }

    public function testAfterCommitWithoutAManagerThrows() {
        $this->connection->setTransactionManager(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transactions Manager has not been set.');
        $this->connection->afterCommit(function () {
        });
    }
}
