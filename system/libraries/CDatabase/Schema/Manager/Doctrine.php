<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Adapter generik: membungkus `Doctrine\DBAL\Schema\AbstractSchemaManager` ASLI supaya memenuhi
 * type-hint `CDatabase_Schema_Manager` milik CF, mendelegasikan semua introspeksi ke Doctrine.
 *
 * @internal
 */
final class CDatabase_Schema_Manager_Doctrine extends CDatabase_Schema_Manager {
    /**
     * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    private $doctrineManager;

    public function __construct(CDatabase_Connection $conn, \Doctrine\DBAL\Schema\AbstractSchemaManager $doctrineManager, CDatabase_Platform $platform = null) {
        $this->db = $conn;
        $this->doctrineManager = $doctrineManager;
        $this->platform = $platform ?: $conn->getDatabasePlatform();
    }

    /**
     * @inheritdoc
     */
    public function listDatabases() {
        return $this->doctrineManager->listDatabases();
    }

    /**
     * @inheritdoc
     */
    public function listTableNames() {
        return $this->filterAssetNames($this->doctrineManager->listTableNames());
    }

    /**
     * @inheritdoc
     */
    public function listTableColumns($table, $database = null) {
        $result = [];
        foreach ($this->doctrineManager->listTableColumns($table, $database) as $name => $column) {
            $result[strtolower((string) $name)] = CDatabase_Schema_DoctrineBridge::columnFromDoctrine($column);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function listTableIndexes($table) {
        $result = [];
        foreach ($this->doctrineManager->listTableIndexes($table) as $name => $index) {
            $result[strtolower((string) $name)] = CDatabase_Schema_DoctrineBridge::indexFromDoctrine($index);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function listTableForeignKeys($table, $database = null) {
        $result = [];
        foreach ($this->doctrineManager->listTableForeignKeys($table, $database) as $fk) {
            $result[] = CDatabase_Schema_DoctrineBridge::foreignKeyFromDoctrine($fk);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function listViews() {
        $result = [];
        foreach ($this->doctrineManager->listViews() as $name => $view) {
            $result[$name] = new CDatabase_Schema_View($view->getName(), $view->getSql());
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function listTableDetails($tableName) {
        return CDatabase_Schema_DoctrineBridge::tableFromDoctrine($this->doctrineManager->introspectTable($tableName));
    }

    /**
     * @inheritdoc
     */
    public function createSchema() {
        $tables = [];
        foreach ($this->doctrineManager->introspectSchema()->getTables() as $table) {
            $tables[] = CDatabase_Schema_DoctrineBridge::tableFromDoctrine($table);
        }

        return new CDatabase_Schema($tables, [], $this->createSchemaConfig(), []);
    }

    /**
     * Tidak pernah dipanggil - method publik yang memakainya sudah dioverride di atas.
     *
     * @inheritdoc
     */
    protected function getPortableTableColumnDefinition($tableColumn) {
        throw new CDatabase_Exception(__METHOD__ . ' tidak dipakai.');
    }
}
