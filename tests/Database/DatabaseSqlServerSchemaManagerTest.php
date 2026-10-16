<?php
use PHPUnit\Framework\TestCase;

/**
 * CDatabase_Connection_Pdo_SqlServerConnection::usingNativeSchemaOperations()/getSchemaManager()/
 * getDatabasePlatform() - tidak ada server/ekstensi sqlsrv di lingkungan test ini, jadi dites
 * lewat PDO sqlite palsu yang hanya dipakai utk memenuhi constructor (sama seperti Postgres).
 */
class DatabaseSqlServerSchemaManagerTest extends TestCase {
    /**
     * @return CDatabase_Connection_Pdo_SqlServerConnection
     */
    protected function connection() {
        return new CDatabase_Connection_Pdo_SqlServerConnection(new PDO('sqlite::memory:'), 'uji_db', '', ['driver' => 'sqlsrv']);
    }

    public function testUsingNativeSchemaOperationsIsTrue() {
        $this->assertTrue($this->connection()->usingNativeSchemaOperations());
    }

    public function testGetSchemaManagerAndPlatformDelegateToDoctrine() {
        $conn = $this->connection();

        $this->assertInstanceOf(CDatabase_Schema_Manager_Doctrine::class, $conn->getSchemaManager());
        $platform = $conn->getDatabasePlatform();
        $this->assertInstanceOf(CDatabase_Platform_Doctrine::class, $platform);
        $this->assertSame('mssql', $platform->getName());
    }

    public function testCompileRenameColumnUsesNativeSpRename() {
        $conn = $this->connection();
        $conn->getSchemaBuilder();
        $grammar = $conn->getSchemaGrammar();

        $blueprint = new CDatabase_Schema_Blueprint('uji_sqlsrv');
        $blueprint->renameColumn('lama', 'baru');

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertSame(["sp_rename '[uji_sqlsrv].[lama]', [baru], 'COLUMN'"], $statements);
    }

    public function testCompileChangeUsesNativeAlterColumnNotThePreviouslyAlwaysThrowingFallback() {
        $conn = $this->connection();
        $conn->getSchemaBuilder();
        $grammar = $conn->getSchemaGrammar();

        $blueprint = new CDatabase_Schema_Blueprint('uji_sqlsrv');
        $blueprint->string('nama', 100)->nullable()->change();

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertSame('alter table [uji_sqlsrv] alter column [nama] nvarchar(100) null', $statements[1]);
    }
}
