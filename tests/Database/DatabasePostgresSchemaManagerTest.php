<?php
use PHPUnit\Framework\TestCase;

/**
 * CDatabase_Connection_Pdo_PostgresConnection::usingNativeSchemaOperations()/getSchemaManager()/
 * getDatabasePlatform() - tidak ada server Postgres di lingkungan test ini (beda dari SQLite,
 * yang bisa `:memory:` tanpa server), jadi dites lewat PDO sqlite palsu yang hanya dipakai utk
 * memenuhi constructor - cukup untuk menguji pembangkitan SQL (Grammar) dan wiring kelas
 * adapter, tidak untuk introspeksi sungguhan terhadap database Postgres nyata.
 */
class DatabasePostgresSchemaManagerTest extends TestCase {
    /**
     * @return CDatabase_Connection_Pdo_PostgresConnection
     */
    protected function connection() {
        return new CDatabase_Connection_Pdo_PostgresConnection(new PDO('sqlite::memory:'), 'uji_db', '', ['driver' => 'pgsql']);
    }

    public function testUsingNativeSchemaOperationsIsTrue() {
        $this->assertTrue($this->connection()->usingNativeSchemaOperations());
    }

    public function testGetSchemaManagerAndPlatformDelegateToDoctrine() {
        $conn = $this->connection();

        $this->assertInstanceOf(CDatabase_Schema_Manager_Doctrine::class, $conn->getSchemaManager());
        $platform = $conn->getDatabasePlatform();
        $this->assertInstanceOf(CDatabase_Platform_Doctrine::class, $platform);
        $this->assertSame('postgresql', $platform->getName());
    }

    public function testCompileChangeUsesNativeAlterColumnNotThePreviouslyAlwaysThrowingFallback() {
        $conn = $this->connection();
        $conn->getSchemaBuilder();
        $grammar = $conn->getSchemaGrammar();

        $blueprint = new CDatabase_Schema_Blueprint('uji_pg');
        $blueprint->string('nama', 100)->nullable()->change();

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertSame(
            ['alter table "uji_pg" alter column "nama" type varchar(100), alter column "nama" drop not null, alter column "nama" drop default, alter column "nama" drop identity if exists'],
            $statements
        );
    }

    public function testCompileRenameColumnUsesNativeSql() {
        $conn = $this->connection();
        $conn->getSchemaBuilder();
        $grammar = $conn->getSchemaGrammar();

        $blueprint = new CDatabase_Schema_Blueprint('uji_pg');
        $blueprint->renameColumn('lama', 'baru');

        $statements = $blueprint->toSql($conn, $grammar);

        $this->assertSame(['alter table "uji_pg" rename column "lama" to "baru"'], $statements);
    }
}
