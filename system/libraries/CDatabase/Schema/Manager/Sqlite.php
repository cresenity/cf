<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Schema manager SQLite: mem-parsing hasil `PRAGMA table_info/index_list/index_info/
 * foreign_key_list/database_list` (bentuknya sama sekali berbeda dari `information_schema`
 * MySQL, lihat CDatabase_Schema_Manager_Mysql untuk perbandingan) - lihat #-21866.
 */
class CDatabase_Schema_Manager_Sqlite extends CDatabase_Schema_Manager {
    /**
     * `$this->db->fetchAll()` (dipakai semua method list*() di kelas dasar) lewat
     * CDatabase_ResultData, yang count()-nya bersandar pada PDOStatement::rowCount() - selalu 0
     * untuk SELECT/PRAGMA di SQLite (bug terpisah yang sudah tercatat, #-14721, scope-nya lebih
     * luas dari kelas ini). Query pragma di kelas ini lewat PDO langsung supaya tidak bergantung
     * pada perbaikan #-14721 lebih dulu - lihat juga methods list*() yang di-override di bawah.
     *
     * @param string $sql
     *
     * @return array
     */
    private function rawFetchAll($sql) {
        return $this->db->getPdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @inheritdoc
     */
    public function listDatabases() {
        return $this->getPortableDatabasesList($this->rawFetchAll($this->platform->getListDatabasesSQL()));
    }

    /**
     * @inheritdoc
     */
    public function listTableNames() {
        $tableNames = $this->getPortableTablesList($this->rawFetchAll($this->platform->getListTablesSQL()));

        return $this->filterAssetNames($tableNames);
    }

    /**
     * @inheritdoc
     */
    public function listTableColumns($table, $database = null) {
        if (!$database) {
            $database = $this->db->getDatabaseName();
        }
        $tableColumns = $this->rawFetchAll($this->platform->getListTableColumnsSQL($table, $database));

        return $this->getPortableTableColumnList($table, $database, $tableColumns);
    }

    /**
     * @inheritdoc
     */
    public function listTableIndexes($table) {
        $tableIndexes = $this->rawFetchAll($this->platform->getListTableIndexesSQL($table, $this->db->getDatabaseName()));

        return $this->getPortableTableIndexesList($tableIndexes, $table);
    }

    /**
     * @inheritdoc
     */
    public function listTableForeignKeys($table, $database = null) {
        if ($database === null) {
            $database = $this->db->getDatabaseName();
        }
        $tableForeignKeys = $this->rawFetchAll($this->platform->getListTableForeignKeysSQL($table, $database));

        return $this->getPortableTableForeignKeysList($tableForeignKeys);
    }

    /**
     * @inheritdoc
     */
    public function listViews() {
        $views = $this->rawFetchAll($this->platform->getListViewsSQL($this->db->getDatabaseName()));

        return $this->getPortableViewsList($views);
    }

    /**
     * Satu-satunya kolom INTEGER PRIMARY KEY tunggal berperilaku sebagai alias rowid -
     * auto-increment secara fungsional terlepas dari kata kunci AUTOINCREMENT ditulis atau
     * tidak. `PRAGMA table_info` tidak membawa info itu per kolom, jadi dua tahap: bangun
     * daftar kolom generik dulu, baru tandai satu-satunya kolom PK bertipe integer (bila PK-nya
     * memang tunggal) sebagai autoincrement.
     *
     * @inheritdoc
     */
    protected function getPortableTableColumnList($table, $database, $tableColumns) {
        $list = parent::getPortableTableColumnList($table, $database, $tableColumns);

        $pkColumns = array_filter($tableColumns, function ($row) {
            return (int) carr::get($row, 'pk', 0) > 0;
        });
        if (count($pkColumns) === 1) {
            $pkRow = array_values($pkColumns)[0];
            $name = strtolower((string) carr::get($pkRow, 'name'));
            if (isset($list[$name]) && in_array($list[$name]->getType()->getName(), [CDatabase_Type::INTEGER, CDatabase_Type::BIGINT], true)) {
                $list[$name]->setAutoincrement(true);
            }
        }

        return $list;
    }

    /**
     * @inheritdoc
     */
    protected function getPortableTableColumnDefinition($tableColumn) {
        $parts = explode('(', str_replace(')', '', (string) carr::get($tableColumn, 'type')));
        $dbType = strtolower(trim($parts[0]));
        $length = isset($parts[1]) ? trim($parts[1]) : null;
        $precision = null;
        $scale = null;
        $fixed = false;

        if (strpos($dbType, 'char') !== false || $dbType === 'character') {
            $fixed = $dbType === 'char' || $dbType === 'character';
        }
        if ($length !== null && strpos($length, ',') !== false) {
            list($precision, $scale) = array_map('trim', explode(',', $length, 2));
            $length = null;
        }

        try {
            $type = $this->platform->getDoctrineTypeMapping($dbType);
        } catch (CDatabase_Exception $e) {
            //tipe affinity bebas - lihat CDatabase_Platform_Sqlite::affinityTypeMapping()
            $type = $this->platform instanceof CDatabase_Platform_Sqlite
                ? $this->platform->affinityTypeMapping($dbType)
                : CDatabase_Type::TEXT;
        }

        $default = carr::get($tableColumn, 'dflt_value');
        if ($default !== null && preg_match("/^'(.*)'$/s", $default, $matches) === 1) {
            $default = str_replace("''", "'", $matches[1]);
        } elseif ($default === 'NULL') {
            $default = null;
        }

        $options = [
            'length' => $length !== null ? (int) $length : null,
            'unsigned' => false,
            'fixed' => $fixed,
            'notnull' => (bool) carr::get($tableColumn, 'notnull'),
            'default' => $default,
            'precision' => $precision !== null ? (int) $precision : null,
            'scale' => $scale !== null ? (int) $scale : null,
            'autoincrement' => false,
        ];

        return new CDatabase_Schema_Column(carr::get($tableColumn, 'name'), CDatabase_Type::getType($type), $options);
    }

    /**
     * `PRAGMA index_list` tidak membawa daftar kolom - satu panggilan `PRAGMA index_info`
     * tambahan per index yang namanya tidak diawali `sqlite_autoindex_` (index implisit dari
     * UNIQUE/PRIMARY KEY inline, bukan index yang dibuat manual lewat CREATE INDEX).
     *
     * @inheritdoc
     */
    protected function getPortableTableIndexesList($tableIndexRows, $tableName = null) {
        $result = [];
        foreach ($tableIndexRows as $row) {
            $indexName = carr::get($row, 'name');
            if (strpos($indexName, 'sqlite_autoindex_') === 0) {
                //index implisit dari UNIQUE/PRIMARY KEY inline - sudah tercermin lewat
                //properti kolom/primary key-nya sendiri, tidak perlu didaftarkan lagi di sini
                continue;
            }

            $columns = [];
            foreach ($this->rawFetchAll('PRAGMA index_info (' . $this->platform->quoteStringLiteral($indexName) . ')') as $infoRow) {
                $columns[(int) carr::get($infoRow, 'seqno')] = carr::get($infoRow, 'name');
            }
            ksort($columns);

            $result[strtolower($indexName)] = new CDatabase_Schema_Index(
                $indexName,
                array_values($columns),
                (bool) carr::get($row, 'unique'),
                false
            );
        }

        $primaryColumns = [];
        foreach ($this->rawFetchAll('PRAGMA table_info (' . $this->platform->quoteStringLiteral($tableName) . ')') as $column) {
            $pk = (int) carr::get($column, 'pk', 0);
            if ($pk > 0) {
                $primaryColumns[$pk] = carr::get($column, 'name');
            }
        }
        if (!empty($primaryColumns)) {
            ksort($primaryColumns);
            $result['primary'] = new CDatabase_Schema_Index('primary', array_values($primaryColumns), true, true);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function getPortableTableForeignKeysList($tableForeignKeys) {
        $byId = [];
        foreach ($tableForeignKeys as $row) {
            $id = carr::get($row, 'id');
            if (!isset($byId[$id])) {
                $byId[$id] = [
                    'name' => 'fk_' . $id,
                    'table' => carr::get($row, 'table'),
                    'local' => [],
                    'foreign' => [],
                    'onUpdate' => carr::get($row, 'on_update'),
                    'onDelete' => carr::get($row, 'on_delete'),
                ];
            }
            $byId[$id]['local'][(int) carr::get($row, 'seq')] = carr::get($row, 'from');
            $byId[$id]['foreign'][(int) carr::get($row, 'seq')] = carr::get($row, 'to');
        }

        $list = [];
        foreach ($byId as $id => $fk) {
            ksort($fk['local']);
            ksort($fk['foreign']);
            $list[] = new CDatabase_Schema_ForeignKeyConstraint(
                array_values($fk['local']),
                $fk['table'],
                array_values($fk['foreign']),
                $fk['name'],
                ['onUpdate' => $fk['onUpdate'], 'onDelete' => $fk['onDelete']]
            );
        }

        return $list;
    }

    /**
     * @inheritdoc
     */
    protected function getPortableDatabaseDefinition($database) {
        return carr::get($database, 'name');
    }

    /**
     * @inheritdoc
     */
    protected function getPortableTableDefinition($table) {
        return carr::get($table, 'name');
    }

    /**
     * @inheritdoc
     */
    protected function getPortableViewDefinition($view) {
        return new CDatabase_Schema_View(carr::get($view, 'name'), carr::get($view, 'sql'));
    }
}
