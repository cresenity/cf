<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * SQLite dialect: introspection SQL (PRAGMA-based) and DDL generation used by schema-diffing
 * commands (`change()`/`renameColumn()` migration grammar, CDatabase_Fixer) - see #-21866.
 *
 * SQLite has no native `CHANGE`/`MODIFY COLUMN`, so a column type/nullable/default change goes
 * through the "rebuild" recipe SQLite itself documents: create a new table with the desired
 * shape, copy the data across, drop the old table, rename the new one into place, then recreate
 * whatever indexes survive. `getAlterTableSQL()` below only takes that path when the diff
 * actually contains a changed column - a pure add/drop/rename stays a single native statement.
 */
class CDatabase_Platform_Sqlite extends CDatabase_Platform {
    /**
     * @inheritdoc
     */
    public function getName() {
        return 'sqlite';
    }

    /**
     * @inheritdoc
     */
    public function getIdentifierQuoteCharacter() {
        return '"';
    }

    /**
     * @inheritdoc
     */
    public function getRegexpExpression() {
        return 'REGEXP';
    }

    /**
     * @inheritdoc
     */
    public function getConcatExpression() {
        return implode(' || ', func_get_args());
    }

    /**
     * @inheritdoc
     */
    public function getListDatabasesSQL() {
        return 'PRAGMA database_list';
    }

    /**
     * @inheritdoc
     */
    public function getListTablesSQL() {
        return "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' "
            . "AND name != 'geometry_columns' AND name != 'spatial_ref_sys' "
            . 'UNION ALL SELECT name FROM sqlite_temp_master '
            . "WHERE type = 'table' ORDER BY name";
    }

    /**
     * @inheritdoc
     */
    public function getListViewsSQL($database) {
        return "SELECT name, sql FROM sqlite_master WHERE type = 'view' AND sql NOT NULL";
    }

    /**
     * PRAGMA tidak menerima parameter terikat - nama tabel diselipkan langsung (quoted sebagai
     * string literal, bukan identifier) ke teks pragma itu sendiri.
     *
     * @inheritdoc
     */
    public function getListTableColumnsSQL($table, $database = null) {
        return 'PRAGMA table_info (' . $this->quoteStringLiteral($table) . ')';
    }

    /**
     * @inheritdoc
     */
    public function getListTableIndexesSQL($table, $database = null) {
        return 'PRAGMA index_list (' . $this->quoteStringLiteral($table) . ')';
    }

    /**
     * @inheritdoc
     */
    public function getListTableForeignKeysSQL($table, $database = null) {
        return 'PRAGMA foreign_key_list (' . $this->quoteStringLiteral($table) . ')';
    }

    /**
     * @inheritdoc
     */
    public function supportsForeignKeyConstraints() {
        //enforcement-nya opt-in lewat PRAGMA foreign_keys (lihat SqliteConnection yang sudah
        //ada) - secara skema (yang dipedulikan platform ini) FK didukung
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsColumnCollation() {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsInlineColumnComments() {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function prefersIdentityColumns() {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsIdentityColumns() {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getBooleanTypeDeclarationSQL(array $field) {
        return 'BOOLEAN';
    }

    /**
     * @inheritdoc
     */
    public function getIntegerTypeDeclarationSQL(array $field) {
        return 'INTEGER' . $this->getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * @inheritdoc
     */
    public function getBigIntTypeDeclarationSQL(array $field) {
        //semua integer di SQLite memakai affinity yang sama (storage class dinamis) -
        //hanya INTEGER PRIMARY KEY yang berperilaku khusus sebagai alias rowid
        return 'BIGINT' . $this->getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * @inheritdoc
     */
    public function getSmallIntTypeDeclarationSQL(array $field) {
        return 'SMALLINT' . $this->getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * Rowid-alias (AUTOINCREMENT) SQLite HANYA berlaku pada kolom yang dideklarasikan
     * `INTEGER PRIMARY KEY` inline pada satu kolom - bukan `PRIMARY KEY(col)` sebagai
     * constraint tabel terpisah. protectedGetCreateTableSQL() di bawah yang menjamin kolom
     * ini tidak ikut diberi PRIMARY KEY(...) terpisah lagi saat baris ini sudah inline.
     *
     * @inheritdoc
     */
    protected function getCommonIntegerTypeDeclarationSQL(array $columnDef) {
        if (!empty($columnDef['autoincrement']) && !empty($columnDef['primary'])) {
            return ' PRIMARY KEY AUTOINCREMENT';
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function getClobTypeDeclarationSQL(array $field) {
        return 'CLOB';
    }

    /**
     * @inheritdoc
     */
    public function getBlobTypeDeclarationSQL(array $field) {
        return 'BLOB';
    }

    /**
     * @inheritdoc
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed) {
        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(255)') : ($length ? 'VARCHAR(' . $length . ')' : 'VARCHAR(255)');
    }

    /**
     * @inheritdoc
     */
    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed) {
        return 'BLOB';
    }

    /**
     * @inheritdoc
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration) {
        return 'DATETIME';
    }

    /**
     * @inheritdoc
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration) {
        return 'DATE';
    }

    /**
     * @inheritdoc
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration) {
        return 'TIME';
    }

    /**
     * @inheritdoc
     */
    public function getCreateViewSQL($name, $sql) {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    /**
     * @inheritdoc
     */
    public function getDropViewSQL($name) {
        return 'DROP VIEW ' . $name;
    }

    /**
     * @inheritdoc
     */
    public function getDropIndexSQL($index, $table = null) {
        if ($index instanceof CDatabase_Schema_Index) {
            $index = $index->getQuotedName($this);
        } elseif (!is_string($index)) {
            throw new InvalidArgumentException('CDatabase_Platform_Sqlite::getDropIndexSQL() expects $index parameter to be string or CDatabase_Schema_Index.');
        }

        return 'DROP INDEX ' . $index;
    }

    /**
     * Tabel baru (rebuild)/sementara dari getAlterTableSQL() menimpa nama index lama persis -
     * SQLite mengikat index ke nama tabel saat ini, bukan OID, jadi urutan DROP TABLE lama lalu
     * RENAME tabel baru cukup; index dibuat ulang terhadap nama akhir yang sudah benar.
     *
     * {@inheritdoc}
     *
     * Satu-satunya jalur DDL di framework ini yang benar-benar perlu membedakan "kolom baru
     * saja" (statement native, murah) dari "kolom berubah" (rebuild tabel penuh, satu-satunya
     * cara SQLite mengganti tipe/nullable/default/constraint kolom yang sudah ada).
     */
    public function getAlterTableSQL(CDatabase_Schema_Table_Diff $diff) {
        //add/remove/change foreign key TIDAK ditangani di jalur rebuild di bawah (fromTable's
        //foreign key dibawa apa adanya) - keterbatasan yang disadari, bukan celah tersembunyi.
        //Dalam praktiknya ChangeColumn/RenameColumn (satu-satunya pemanggil saat ini) selalu
        //menghasilkan diff dengan FK tak berubah, karena diff-nya dibangun dari clone tabel yang
        //cuma kolomnya diubah (lihat Schema/Grammar/ChangeColumn.php::getChangedDiff()).
        //
        //removedColumns ikut memicu rebuild, bukan `ALTER TABLE ... DROP COLUMN` native -
        //dukungan DROP COLUMN baru masuk SQLite 3.35 (2021); dibuktikan langsung berbeda dari
        //dugaan (PDO sqlite di box ini masih 3.31.1, "syntax error near DROP") - rebuild selalu
        //benar di versi berapa pun, native cuma penghematan di versi yang cukup baru.
        if (!empty($diff->changedColumns) || !empty($diff->removedColumns)) {
            return $this->getAlterTableRebuildSQL($diff);
        }

        $table = $diff->getName($this)->getQuotedName($this);
        $sql = [];

        foreach ($diff->addedColumns as $column) {
            $columnArray = $column->toArray();
            $columnArray['comment'] = $this->getColumnComment($column);
            $sql[] = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
        }

        //removedColumns tidak pernah tiba di sini - selalu lewat rebuild di atas (lihat komentar
        //getAlterTableSQL() soal DROP COLUMN baru ada sejak SQLite 3.35)

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            $oldColumnName = new CDatabase_Schema_Identifier($oldColumnName);
            $sql[] = 'ALTER TABLE ' . $table . ' RENAME COLUMN ' . $oldColumnName->getQuotedName($this) . ' TO ' . $column->getQuotedName($this);
        }

        foreach ($diff->removedIndexes as $index) {
            $sql[] = $this->getDropIndexSQL($index);
        }

        $newName = $diff->getNewName();
        if ($newName !== false) {
            $sql[] = 'ALTER TABLE ' . $table . ' RENAME TO ' . $newName->getQuotedName($this);
            $table = $newName->getQuotedName($this);
        }

        foreach ($diff->addedIndexes as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $table);
        }
        foreach ($diff->changedIndexes as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $table);
        }

        return $sql;
    }

    /**
     * Resep rebuild resmi SQLite (dokumentasi sqlite.org "Making Other Kinds Of Table
     * Schema Changes"): tabel baru dengan bentuk akhir, salin data, hapus tabel lama,
     * ganti nama tabel baru, buat ulang index yang bertahan.
     *
     * Keterbatasan yang disadari (bukan diam-diam diabaikan): trigger dan view yang
     * bergantung pada tabel ini tidak dibuat ulang - sama seperti keterbatasan historis
     * Doctrine DBAL SqlitePlatform sendiri untuk kasus ini.
     *
     * @return string[]
     */
    private function getAlterTableRebuildSQL(CDatabase_Schema_Table_Diff $diff) {
        if (!$diff->fromTable instanceof CDatabase_Schema_Table) {
            throw new CDatabase_Exception('CDatabase_Platform_Sqlite::getAlterTableSQL() butuh $diff->fromTable untuk rebuild kolom yang berubah.');
        }

        $oldTableName = $diff->getName($this)->getQuotedName($this);
        $newTableNameIdentifier = $diff->getNewName();
        $finalTableName = $newTableNameIdentifier !== false ? $newTableNameIdentifier->getQuotedName($this) : $oldTableName;
        $tempTableName = '__temp__' . $diff->fromTable->getName();

        $changedByOldName = [];
        foreach ($diff->changedColumns as $columnDiff) {
            $changedByOldName[strtolower($columnDiff->oldColumnName)] = $columnDiff->column;
        }
        $removedNames = [];
        foreach ($diff->removedColumns as $column) {
            $removedNames[strtolower($column->getName())] = true;
        }
        $renamedByOldName = [];
        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            $renamedByOldName[strtolower($oldColumnName)] = $column;
        }

        //pasangan [kolom tabel baru, ekspresi SELECT dari tabel lama] - urutan sumber
        //kolom lama dipertahankan, kolom baru (addedColumns) ditambahkan di akhir
        $newColumns = [];
        $selectExpressions = [];
        foreach ($diff->fromTable->getColumns() as $oldColumn) {
            $oldName = $oldColumn->getName();
            $key = strtolower($oldName);
            if (isset($removedNames[$key])) {
                continue;
            }
            if (isset($changedByOldName[$key])) {
                $newColumns[] = $changedByOldName[$key];
                $selectExpressions[] = $oldColumn->getQuotedName($this) . ' AS ' . $changedByOldName[$key]->getQuotedName($this);

                continue;
            }
            if (isset($renamedByOldName[$key])) {
                $newColumns[] = $renamedByOldName[$key];
                $selectExpressions[] = $oldColumn->getQuotedName($this) . ' AS ' . $renamedByOldName[$key]->getQuotedName($this);

                continue;
            }
            $newColumns[] = $oldColumn;
            $selectExpressions[] = $oldColumn->getQuotedName($this);
        }
        foreach ($diff->addedColumns as $column) {
            $newColumns[] = $column;
            //kolom baru tidak punya sumber data - diisi default/null lewat INSERT tanpa
            //menyebut kolom ini sama sekali (lihat getInsertColumnListSQL di bawah)
        }

        //foreign key fromTable dibawa apa adanya ke tabel baru (lihat catatan keterbatasan di
        //getAlterTableSQL()) - SQLite mewajibkan FK inline di CREATE TABLE, jadi CREATE_FOREIGNKEYS
        //wajib menyala di sini meski rebuild ini tidak mengubah FK itu sendiri
        $newTable = new CDatabase_Schema_Table($tempTableName, $newColumns, [], $diff->fromTable->getForeignKeys(), false, $diff->fromTable->getOptions());
        //index yang bertahan (bukan di-drop/diganti) dibuat ulang terhadap nama akhir, bukan
        //terhadap nama sementara - hanya baris INSERT/RENAME ini yang perlu tempTableName
        $createTableSql = $this->getCreateTableSQL($newTable, self::CREATE_FOREIGNKEYS);

        $insertColumnList = [];
        foreach ($newColumns as $column) {
            if (!in_array($column, $diff->addedColumns, true)) {
                $insertColumnList[] = $column->getQuotedName($this);
            }
        }

        $sql = $createTableSql;
        if (!empty($insertColumnList)) {
            $sql[] = 'INSERT INTO ' . $tempTableName . ' (' . implode(', ', $insertColumnList) . ') '
                . 'SELECT ' . implode(', ', $selectExpressions) . ' FROM ' . $oldTableName;
        }
        $sql[] = 'DROP TABLE ' . $oldTableName;
        $sql[] = 'ALTER TABLE ' . $tempTableName . ' RENAME TO ' . $finalTableName;

        $keptIndexNames = [];
        foreach ($diff->removedIndexes as $removed) {
            $keptIndexNames[strtolower($removed->getName())] = false;
        }
        foreach ($diff->changedIndexes as $changed) {
            $keptIndexNames[strtolower($changed->getName())] = false;
        }
        foreach ($diff->fromTable->getIndexes() as $index) {
            if ($index->isPrimary()) {
                //primary key SQLite tunggal sudah inline lewat AUTOINCREMENT/kolom; bentuk
                //lain (composite) sudah ikut terbawa sebagai constraint tabel di CREATE TABLE
                continue;
            }
            if (array_key_exists(strtolower($index->getName()), $keptIndexNames)) {
                continue;
            }
            //lewati index yang menyebut kolom yang baru dihapus - tidak ada lagi kolomnya
            $stillExists = true;
            foreach ($index->getColumns() as $columnName) {
                if (isset($removedNames[strtolower($columnName)])) {
                    $stillExists = false;

                    break;
                }
            }
            if ($stillExists) {
                $sql[] = $this->getCreateIndexSQL($index, $finalTableName);
            }
        }
        foreach ($diff->addedIndexes as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $finalTableName);
        }
        foreach ($diff->changedIndexes as $index) {
            $sql[] = $this->getCreateIndexSQL($index, $finalTableName);
        }

        return $sql;
    }

    /**
     * CREATE TABLE sendiri (bukan delegasi ke generic ANSI base): index sekunder SQLite tidak
     * bisa inline (`INDEX nama (...)` di dalam CREATE TABLE adalah sintaks MySQL), jadi dibuat
     * sebagai statement CREATE INDEX terpisah; primary key auto-increment tunggal di-inline-kan
     * lewat getCommonIntegerTypeDeclarationSQL() di atas, bukan diulang sebagai PRIMARY KEY(...)
     * terpisah (SQLite menolak dua deklarasi primary key pada tabel yang sama).
     *
     * @inheritdoc
     */
    protected function protectedGetCreateTableSQL($tableName, array $columns, array $options = []) {
        $primaryKeys = isset($options['primary']) ? array_values(array_unique($options['primary'])) : [];
        $inlinedPrimaryKey = null;
        if (count($primaryKeys) === 1 && isset($columns[$primaryKeys[0]]) && !empty($columns[$primaryKeys[0]]['autoincrement'])) {
            $inlinedPrimaryKey = $primaryKeys[0];
        }

        $declarations = [];
        foreach ($columns as $name => $column) {
            $declarations[] = $this->getColumnDeclarationSQL($name, $column);
        }
        $columnListSql = implode(', ', $declarations);

        if (!empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $name => $definition) {
                $columnListSql .= ', ' . $this->getUniqueConstraintDeclarationSQL($name, $definition);
            }
        }

        if ($inlinedPrimaryKey === null && !empty($primaryKeys)) {
            $columnListSql .= ', PRIMARY KEY(' . implode(', ', $primaryKeys) . ')';
        }

        if (!empty($options['foreignKeys'])) {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $columnListSql .= ', ' . $this->getForeignKeyDeclarationSQL($definition);
            }
        }

        $check = $this->getCheckDeclarationSQL($columns);
        if (!empty($check)) {
            $columnListSql .= ', ' . $check;
        }

        $sql = ['CREATE TABLE ' . $tableName . ' (' . $columnListSql . ')'];

        //index sekunder: statement terpisah, tidak pernah inline (lihat docblock)
        if (!empty($options['indexes'])) {
            foreach ($options['indexes'] as $index => $definition) {
                $sql[] = $this->getCreateIndexSQL($definition, $tableName);
            }
        }

        return $sql;
    }

    /**
     * @inheritdoc
     */
    protected function getReservedKeywordsClass() {
        return CDatabase_Platform_Keywords_Sqlite::class;
    }

    /**
     * @inheritdoc
     */
    protected function initializeDoctrineTypeMappings() {
        $this->doctrineTypeMapping = [
            'boolean' => 'boolean',
            'tinyint' => 'boolean',
            'smallint' => 'smallint',
            'mediumint' => 'integer',
            'int' => 'integer',
            'integer' => 'integer',
            'bigint' => 'bigint',
            'char' => 'string',
            'character' => 'string',
            'varchar' => 'string',
            'varying character' => 'string',
            'nchar' => 'string',
            'native character' => 'string',
            'nvarchar' => 'string',
            'clob' => 'text',
            'text' => 'text',
            'blob' => 'blob',
            'binary' => 'binary',
            'varbinary' => 'binary',
            'date' => 'date',
            'datetime' => 'datetime',
            'timestamp' => 'datetime',
            'time' => 'time',
            'float' => 'float',
            'double' => 'float',
            'double precision' => 'float',
            'real' => 'float',
            'decimal' => 'decimal',
            'numeric' => 'decimal',
        ];
    }

    /**
     * Tipe kolom mentah SQLite (tipe apa pun boleh ditulis - "type affinity", bukan
     * ditegakkan) kadang bukan salah satu literal di atas (mis. hasil dari grammar CF sendiri
     * yang menulis nama tipe lain, atau kolom hasil ALTER TABLE lama). Dipakai Schema Manager
     * sebagai jatuhan terakhir sebelum literal lookup di atas gagal - meniru aturan affinity
     * resmi SQLite (bagian 3 dokumentasi "Datatypes In SQLite").
     *
     * @param string $declaredType
     *
     * @return string nama tipe CDatabase_Type
     */
    public function affinityTypeMapping($declaredType) {
        $declaredType = strtolower(trim((string) $declaredType));
        if ($declaredType === '') {
            return 'blob';
        }
        if (strpos($declaredType, 'int') !== false) {
            return 'bigint';
        }
        if (strpos($declaredType, 'char') !== false || strpos($declaredType, 'clob') !== false || strpos($declaredType, 'text') !== false) {
            return 'text';
        }
        if (strpos($declaredType, 'blob') !== false) {
            return 'blob';
        }
        if (strpos($declaredType, 'real') !== false || strpos($declaredType, 'floa') !== false || strpos($declaredType, 'doub') !== false) {
            return 'float';
        }

        return 'decimal';
    }
}
