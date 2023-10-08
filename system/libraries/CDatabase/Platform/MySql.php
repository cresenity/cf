<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Aug 18, 2018, 8:54:25 AM
 */

/**
 * The MySqlPlatform provides the behavior, features and SQL dialect of the
 * MySQL database platform. This platform represents a MySQL 5.0 or greater platform that
 * uses the InnoDB storage engine.
 */
class CDatabase_Platform_MySql extends CDatabase_Platform {
    const LENGTH_LIMIT_TINYTEXT = 255;

    const LENGTH_LIMIT_TEXT = 65535;

    const LENGTH_LIMIT_MEDIUMTEXT = 16777215;

    const LENGTH_LIMIT_TINYBLOB = 255;

    const LENGTH_LIMIT_BLOB = 65535;

    const LENGTH_LIMIT_MEDIUMBLOB = 16777215;

    /**
     * @inheritDoc
     */
    protected function doModifyLimitQuery($query, $limit, $offset) {
        if ($limit !== null) {
            $query .= sprintf(' LIMIT %d', $limit);

            if ($offset > 0) {
                $query .= sprintf(' OFFSET %d', $offset);
            }
        } elseif ($offset > 0) {
            // 2^64-1 is the maximum of unsigned BIGINT, the biggest limit possible
            $query .= sprintf(' LIMIT 18446744073709551615 OFFSET %d', $offset);
        }

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function getIdentifierQuoteCharacter() {
        return '`';
    }

    /**
     * @inheritDoc
     */
    public function getRegexpExpression() {
        return 'RLIKE';
    }

    /**
     * @inheritDoc
     *
     * @deprecated Use application-generated UUIDs instead
     */
    public function getGuidExpression() {
        return 'UUID()';
    }

    /**
     * @inheritDoc
     */
    public function getLocateExpression($str, $substr, $startPos = false) {
        if ($startPos == false) {
            return 'LOCATE(' . $substr . ', ' . $str . ')';
        }

        return 'LOCATE(' . $substr . ', ' . $str . ', ' . $startPos . ')';
    }

    /**
     * @inheritDoc
     */
    public function getConcatExpression() {
        return sprintf('CONCAT(%s)', implode(', ', func_get_args()));
    }

    /**
     * @inheritdoc
     */
    protected function getDateArithmeticIntervalExpression($date, $operator, $interval, $unit) {
        $function = $operator === '+' ? 'DATE_ADD' : 'DATE_SUB';

        return $function . '(' . $date . ', INTERVAL ' . $interval . ' ' . $unit . ')';
    }

    /**
     * @inheritDoc
     */
    public function getDateDiffExpression($date1, $date2) {
        return 'DATEDIFF(' . $date1 . ', ' . $date2 . ')';
    }

    public function getCurrentDatabaseExpression() {
        return 'DATABASE()';
    }

    /**
     * @inheritDoc
     */
    public function getLengthExpression($column) {
        return 'CHAR_LENGTH(' . $column . ')';
    }

    /**
     * @inheritDoc
     */
    public function getListDatabasesSQL() {
        return 'SHOW DATABASES';
    }

    /**
     * @inheritDoc
     */
    public function getListTableConstraintsSQL($table) {
        return 'SHOW INDEX FROM ' . $table;
    }

    /**
     * {@inheritDoc}
     *
     * Two approaches to listing the table indexes. The information_schema is
     * preferred, because it doesn't cause problems with SQL keywords such as "order" or "table".
     */
    public function getListTableIndexesSQL($table, $database = null) {
        if ($database !== null) {
            return 'SELECT NON_UNIQUE AS Non_Unique, INDEX_NAME AS Key_name, COLUMN_NAME AS Column_Name,'
                   . ' SUB_PART AS Sub_Part, INDEX_TYPE AS Index_Type'
                   . ' FROM information_schema.STATISTICS WHERE TABLE_NAME = ' . $this->quoteStringLiteral($table)
                   . ' AND TABLE_SCHEMA = ' . $this->quoteStringLiteral($database)
                   . ' ORDER BY SEQ_IN_INDEX ASC';
        }

        return 'SHOW INDEX FROM ' . $table;
    }

    /**
     * @inheritDoc
     */
    public function getListViewsSQL($database) {
        return 'SELECT * FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ' . $this->quoteStringLiteral($database);
    }

    /**
     * @inheritDoc
     */
    public function getListTableForeignKeysSQL($table, $database = null) {
        // The schema name is passed multiple times as a literal in the WHERE clause instead of using a JOIN condition
        // in order to avoid performance issues on MySQL older than 8.0 and the corresponding MariaDB versions
        // caused by https://bugs.mysql.com/bug.php?id=81347
        return 'SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, '
               . 'k.REFERENCED_COLUMN_NAME /*!50116 , c.UPDATE_RULE, c.DELETE_RULE */ '
               . 'FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k /*!50116 '
               . 'INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS c ON '
               . 'c.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND '
               . 'c.TABLE_NAME = k.TABLE_NAME */ '
               . 'WHERE k.TABLE_NAME = ' . $this->quoteStringLiteral($table) . ' '
               . 'AND k.TABLE_SCHEMA = ' . $this->getDatabaseNameSQL($database) . ' /*!50116 '
               . 'AND c.CONSTRAINT_SCHEMA = ' . $this->getDatabaseNameSQL($database) . ' */'
               . 'ORDER BY k.ORDINAL_POSITION';
    }

    /**
     * @inheritDoc
     */
    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed) {
        return $fixed ? ($length ? 'CHAR(' . $length . ')' : 'CHAR(255)') : ($length ? 'VARCHAR(' . $length . ')' : 'VARCHAR(255)');
    }

    /**
     * @inheritdoc
     */
    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed) {
        return $fixed ? 'BINARY(' . ($length ?: 255) . ')' : 'VARBINARY(' . ($length ?: 255) . ')';
    }

    /**
     * Gets the SQL snippet used to declare a CLOB column type.
     *     TINYTEXT   : 2 ^  8 - 1 = 255
     *     TEXT       : 2 ^ 16 - 1 = 65535
     *     MEDIUMTEXT : 2 ^ 24 - 1 = 16777215
     *     LONGTEXT   : 2 ^ 32 - 1 = 4294967295.
     *
     * @param array $field
     *
     * @return string
     */
    public function getClobTypeDeclarationSQL(array $field) {
        if (!empty($field['length']) && is_numeric($field['length'])) {
            $length = $field['length'];

            if ($length <= static::LENGTH_LIMIT_TINYTEXT) {
                return 'TINYTEXT';
            }

            if ($length <= static::LENGTH_LIMIT_TEXT) {
                return 'TEXT';
            }

            if ($length <= static::LENGTH_LIMIT_MEDIUMTEXT) {
                return 'MEDIUMTEXT';
            }
        }

        return 'LONGTEXT';
    }

    /**
     * @inheritDoc
     */
    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration) {
        if (isset($fieldDeclaration['version']) && $fieldDeclaration['version'] == true) {
            return 'TIMESTAMP';
        }

        return 'DATETIME';
    }

    /**
     * @inheritDoc
     */
    public function getDateTypeDeclarationSQL(array $fieldDeclaration) {
        return 'DATE';
    }

    /**
     * @inheritDoc
     */
    public function getTimeTypeDeclarationSQL(array $fieldDeclaration) {
        return 'TIME';
    }

    /**
     * @inheritDoc
     */
    public function getCreateViewSQL($name, $sql) {
        return 'CREATE VIEW ' . $name . ' AS ' . $sql;
    }

    /**
     * @inheritDoc
     */
    public function getDropViewSQL($name) {
        return 'DROP VIEW ' . $name;
    }

    /**
     * @inheritDoc
     */
    public function getBooleanTypeDeclarationSQL(array $field) {
        $unsigned = carr::get($field, 'unsigned', false);
        $unsignedStr = $unsigned ? ' UNSIGNED' : '';

        return 'TINYINT(1)' . $unsignedStr;
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set the COLLATION
     * of a field declaration to be used in statements like CREATE TABLE.
     *
     * @param string $collation name of the collation
     *
     * @return string DBMS specific SQL code portion needed to set the COLLATION
     *                of a field declaration
     *
     * @deprecated Deprecated since version 2.5, Use {@link self::getColumnCollationDeclarationSQL()} instead.
     */
    public function getCollationFieldDeclaration($collation) {
        return $this->getColumnCollationDeclarationSQL($collation);
    }

    /**
     * {@inheritDoc}
     *
     * MySql prefers "autoincrement" identity columns since sequences can only
     * be emulated with a table.
     */
    public function prefersIdentityColumns() {
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * MySql supports this through AUTO_INCREMENT columns.
     */
    public function supportsIdentityColumns() {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function supportsInlineColumnComments() {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function supportsColumnCollation() {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getListTablesSQL() {
        return "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'";
    }

    /**
     * @inheritDoc
     */
    public function getListTableColumnsSQL($table, $database = null) {
        $table = $this->quoteStringLiteral($table);

        if ($database) {
            $database = $this->quoteStringLiteral($database);
        } else {
            $database = 'DATABASE()';
        }

        return 'SELECT COLUMN_NAME AS Field, COLUMN_TYPE AS Type, IS_NULLABLE AS `Null`, '
                . 'COLUMN_KEY AS `Key`, COLUMN_DEFAULT AS `Default`, EXTRA AS Extra, COLUMN_COMMENT AS Comment, '
                . 'CHARACTER_SET_NAME AS CharacterSet, COLLATION_NAME AS Collation '
                . 'FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ' . $database . ' AND TABLE_NAME = ' . $table
                . ' ORDER BY ORDINAL_POSITION ASC';
    }

    public function getListTableMetadataSQL($table, $database = null) {
        return sprintf(
            <<<'SQL'
SELECT ENGINE, AUTO_INCREMENT, TABLE_COLLATION, TABLE_COMMENT, CREATE_OPTIONS
FROM information_schema.TABLES
WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = %s AND TABLE_NAME = %s
SQL
                ,
            $database ? $this->quoteStringLiteral($database) : 'DATABASE()',
            $this->quoteStringLiteral($table)
        );
    }

    /**
     * @inheritDoc
     */
    public function getCreateDatabaseSQL($name) {
        return 'CREATE DATABASE ' . $name;
    }

    /**
     * @inheritDoc
     */
    public function getDropDatabaseSQL($name) {
        return 'DROP DATABASE ' . $name;
    }

    /**
     * @inheritDoc
     */
    protected function protectedGetCreateTableSQL($tableName, array $columns, array $options = []) {
        $queryFields = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && !empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $index => $definition) {
                $queryFields .= ', ' . $this->getUniqueConstraintDeclarationSQL($index, $definition);
            }
        }

        // add all indexes
        if (isset($options['indexes']) && !empty($options['indexes'])) {
            foreach ($options['indexes'] as $index => $definition) {
                $queryFields .= ', ' . $this->getIndexDeclarationSQL($index, $definition);
            }
        }

        // attach all primary keys
        if (isset($options['primary']) && !empty($options['primary'])) {
            $keyColumns = array_unique(array_values($options['primary']));
            $queryFields .= ', PRIMARY KEY(' . implode(', ', $keyColumns) . ')';
        }

        $query = 'CREATE ';

        if (!empty($options['temporary'])) {
            $query .= 'TEMPORARY ';
        }

        $query .= 'TABLE ' . $tableName . ' (' . $queryFields . ') ';
        $query .= $this->buildTableOptions($options);
        $query .= $this->buildPartitionOptions($options);

        $sql = [$query];
        $engine = 'INNODB';

        if (isset($options['engine'])) {
            $engine = strtoupper(trim($options['engine']));
        }

        // Propagate foreign key constraints only for InnoDB.
        if (isset($options['foreignKeys']) && $engine === 'INNODB') {
            foreach ((array) $options['foreignKeys'] as $definition) {
                $sql[] = $this->getCreateForeignKeySQL($definition, $tableName);
            }
        }

        return $sql;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultValueDeclarationSQL($field) {
        // Unset the default value if the given field definition does not allow default values.
        if ($field['type'] instanceof CDatabase_Type_TextType || $field['type'] instanceof CDatabase_Type_BlobType) {
            $field['default'] = null;
        }

        return parent::getDefaultValueDeclarationSQL($field);
    }

    /**
     * Build SQL for table options.
     *
     * @param array $options
     *
     * @return string
     */
    private function buildTableOptions(array $options) {
        if (isset($options['table_options'])) {
            return $options['table_options'];
        }

        $tableOptions = [];

        // Charset
        if (!isset($options['charset'])) {
            $options['charset'] = 'utf8mb4';
        }

        $tableOptions[] = sprintf('DEFAULT CHARACTER SET %s', $options['charset']);

        // Collate
        if (!isset($options['collate'])) {
            $options['collate'] = $options['charset'] . '_unicode_ci';
        }

        $tableOptions[] = sprintf('COLLATE %s', $options['collate']);

        // Engine
        if (!isset($options['engine'])) {
            $options['engine'] = 'InnoDB';
        }

        $tableOptions[] = sprintf('ENGINE = %s', $options['engine']);

        // Auto increment
        if (isset($options['auto_increment'])) {
            $tableOptions[] = sprintf('AUTO_INCREMENT = %s', $options['auto_increment']);
        }

        // Comment
        if (isset($options['comment'])) {
            $comment = trim($options['comment'], " '");

            $tableOptions[] = sprintf('COMMENT = %s ', $this->quoteStringLiteral($comment));
        }

        // Row format
        if (isset($options['row_format'])) {
            $tableOptions[] = sprintf('ROW_FORMAT = %s', $options['row_format']);
        }

        return implode(' ', $tableOptions);
    }

    /**
     * Build SQL for partition options.
     *
     * @param array $options
     *
     * @return string
     */
    private function buildPartitionOptions(array $options) {
        return (isset($options['partition_options'])) ? ' ' . $options['partition_options'] : '';
    }

    /**
     * @inheritDoc
     */
    public function getAlterTableSQL(CDatabase_Schema_Table_Diff $diff) {
        $columnSql = [];
        $queryParts = [];
        $newName = $diff->getNewName();
        if ($newName !== false) {
            $queryParts[] = 'RENAME TO ' . $newName->getQuotedName($this);
        }

        foreach ($diff->addedColumns as $column) {
            if ($this->onSchemaAlterTableAddColumn($column, $diff, $columnSql)) {
                continue;
            }

            $columnArray = $column->toArray();
            $columnArray['comment'] = $this->getColumnComment($column);
            $queryParts[] = 'ADD ' . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
        }

        foreach ($diff->removedColumns as $column) {
            if ($this->onSchemaAlterTableRemoveColumn($column, $diff, $columnSql)) {
                continue;
            }

            $queryParts[] = 'DROP ' . $column->getQuotedName($this);
        }

        foreach ($diff->changedColumns as $columnDiff) {
            if ($this->onSchemaAlterTableChangeColumn($columnDiff, $diff, $columnSql)) {
                continue;
            }

            /* @var $columnDiff CDatabase_Schema_Column_Diff */
            $column = $columnDiff->column;
            $columnArray = $column->toArray();

            // Don't propagate default value changes for unsupported column types.
            if ($columnDiff->hasChanged('default') && count($columnDiff->changedProperties) === 1 && ($columnArray['type'] instanceof CDatabase_Type_TextType || $columnArray['type'] instanceof CDatabase_Type_BlobType)) {
                continue;
            }

            $columnArray['comment'] = $this->getColumnComment($column);
            $queryParts[] = 'CHANGE ' . ($columnDiff->getOldColumnName()->getQuotedName($this)) . ' '
                    . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
        }

        foreach ($diff->renamedColumns as $oldColumnName => $column) {
            if ($this->onSchemaAlterTableRenameColumn($oldColumnName, $column, $diff, $columnSql)) {
                continue;
            }

            $oldColumnName = new CDatabase_Schema_Identifier($oldColumnName);
            $columnArray = $column->toArray();
            $columnArray['comment'] = $this->getColumnComment($column);
            $queryParts[] = 'CHANGE ' . $oldColumnName->getQuotedName($this) . ' '
                    . $this->getColumnDeclarationSQL($column->getQuotedName($this), $columnArray);
        }

        if (isset($diff->addedIndexes['primary'])) {
            $keyColumns = array_unique(array_values($diff->addedIndexes['primary']->getColumns()));
            $queryParts[] = 'ADD PRIMARY KEY (' . implode(', ', $keyColumns) . ')';
            unset($diff->addedIndexes['primary']);
        } elseif (isset($diff->changedIndexes['primary'])) {
            // Necessary in case the new primary key includes a new auto_increment column
            foreach ($diff->changedIndexes['primary']->getColumns() as $columnName) {
                if (isset($diff->addedColumns[$columnName]) && $diff->addedColumns[$columnName]->getAutoincrement()) {
                    $keyColumns = array_unique(array_values($diff->changedIndexes['primary']->getColumns()));
                    $queryParts[] = 'DROP PRIMARY KEY';
                    $queryParts[] = 'ADD PRIMARY KEY (' . implode(', ', $keyColumns) . ')';
                    unset($diff->changedIndexes['primary']);

                    break;
                }
            }
        }

        $sql = [];
        $tableSql = [];

        if (!$this->onSchemaAlterTable($diff, $tableSql)) {
            if (count($queryParts) > 0) {
                $sql[] = 'ALTER TABLE ' . $diff->getName($this)->getQuotedName($this) . ' ' . implode(', ', $queryParts);
            }
            $sql = array_merge(
                $this->getPreAlterTableIndexForeignKeySQL($diff),
                $sql,
                $this->getPostAlterTableIndexForeignKeySQL($diff)
            );
        }

        return array_merge($sql, $tableSql, $columnSql);
    }

    /**
     * @inheritDoc
     */
    protected function getPreAlterTableIndexForeignKeySQL(CDatabase_Schema_Table_Diff $diff) {
        $sql = [];
        $table = $diff->getName($this)->getQuotedName($this);

        foreach ($diff->changedIndexes as $changedIndex) {
            $sql = array_merge($sql, $this->getPreAlterTableAlterPrimaryKeySQL($diff, $changedIndex));
        }

        foreach ($diff->removedIndexes as $remKey => $remIndex) {
            $sql = array_merge($sql, $this->getPreAlterTableAlterPrimaryKeySQL($diff, $remIndex));

            foreach ($diff->addedIndexes as $addKey => $addIndex) {
                if ($remIndex->getColumns() == $addIndex->getColumns()) {
                    $indexClause = 'INDEX ' . $addIndex->getName();

                    if ($addIndex->isPrimary()) {
                        $indexClause = 'PRIMARY KEY';
                    } elseif ($addIndex->isUnique()) {
                        $indexClause = 'UNIQUE INDEX ' . $addIndex->getName();
                    }

                    $query = 'ALTER TABLE ' . $table . ' DROP INDEX ' . $remIndex->getName() . ', ';
                    $query .= 'ADD ' . $indexClause;
                    $query .= ' (' . $this->getIndexFieldDeclarationListSQL($addIndex) . ')';

                    $sql[] = $query;

                    unset($diff->removedIndexes[$remKey], $diff->addedIndexes[$addKey]);

                    break;
                }
            }
        }

        $engine = 'INNODB';

        if ($diff->fromTable instanceof CDatabase_Schema_Table && $diff->fromTable->hasOption('engine')) {
            $engine = strtoupper(trim($diff->fromTable->getOption('engine')));
        }

        // Suppress foreign key constraint propagation on non-supporting engines.
        if ('INNODB' !== $engine) {
            $diff->addedForeignKeys = [];
            $diff->changedForeignKeys = [];
            $diff->removedForeignKeys = [];
        }

        $sql = array_merge(
            $sql,
            $this->getPreAlterTableAlterIndexForeignKeySQL($diff),
            parent::getPreAlterTableIndexForeignKeySQL($diff),
            $this->getPreAlterTableRenameIndexForeignKeySQL($diff)
        );

        return $sql;
    }

    /**
     * @param CDatabase_Schema_Table_Diff $diff
     * @param CDatabase_Schema_Index      $index
     *
     * @return string[]
     */
    private function getPreAlterTableAlterPrimaryKeySQL(CDatabase_Schema_Table_Diff $diff, CDatabase_Schema_Index $index) {
        $sql = [];

        if (!$index->isPrimary() || !$diff->fromTable instanceof CDatabase_Schema_Table) {
            return $sql;
        }

        $tableName = $diff->getName($this)->getQuotedName($this);

        // Dropping primary keys requires to unset autoincrement attribute on the particular column first.
        foreach ($index->getColumns() as $columnName) {
            if (!$diff->fromTable->hasColumn($columnName)) {
                continue;
            }

            $column = $diff->fromTable->getColumn($columnName);
            if ($column->getAutoincrement() !== true) {
                continue;
            }

            $column->setAutoincrement(false);

            $sql[] = 'ALTER TABLE ' . $tableName . ' MODIFY '
                    . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());

            // original autoincrement information might be needed later on by other parts of the table alteration
            $column->setAutoincrement(true);
        }

        return $sql;
    }

    /**
     * @param CDatabase_Schema_Table_Diff $diff the table diff to gather the SQL for
     *
     * @return array
     */
    private function getPreAlterTableAlterIndexForeignKeySQL(CDatabase_Schema_Table_Diff $diff) {
        $sql = [];
        $table = $diff->getName($this)->getQuotedName($this);

        foreach ($diff->changedIndexes as $changedIndex) {
            // Changed primary key
            if (!$changedIndex->isPrimary() || !($diff->fromTable instanceof CDatabase_Schema_Table)) {
                continue;
            }

            foreach ($diff->fromTable->getPrimaryKeyColumns() as $columnName) {
                $column = $diff->fromTable->getColumn($columnName);

                // Check if an autoincrement column was dropped from the primary key.
                if (!$column->getAutoincrement() || in_array($columnName, $changedIndex->getColumns())) {
                    continue;
                }

                // The autoincrement attribute needs to be removed from the dropped column
                // before we can drop and recreate the primary key.
                $column->setAutoincrement(false);

                $sql[] = 'ALTER TABLE ' . $table . ' MODIFY '
                        . $this->getColumnDeclarationSQL($column->getQuotedName($this), $column->toArray());

                // Restore the autoincrement attribute as it might be needed later on
                // by other parts of the table alteration.
                $column->setAutoincrement(true);
            }
        }

        return $sql;
    }

    /**
     * @param CDatabase_Schema_Table_Diff $diff the table diff to gather the SQL for
     *
     * @return array
     */
    protected function getPreAlterTableRenameIndexForeignKeySQL(CDatabase_Schema_Table_Diff $diff) {
        $sql = [];
        $tableName = $diff->getName($this)->getQuotedName($this);

        foreach ($this->getRemainingForeignKeyConstraintsRequiringRenamedIndexes($diff) as $foreignKey) {
            if (in_array($foreignKey, $diff->changedForeignKeys, true)) {
                continue;
            }

            $sql[] = $this->getDropForeignKeySQL($foreignKey, $tableName);
        }

        return $sql;
    }

    /**
     * Returns the remaining foreign key constraints that require one of the renamed indexes.
     *
     * "Remaining" here refers to the diff between the foreign keys currently defined in the associated
     * table and the foreign keys to be removed.
     *
     * @param CDatabase_Schema_Table_Diff $diff the table diff to evaluate
     *
     * @return CDatabase_Schema_ForeignKeyConstraint[]
     */
    private function getRemainingForeignKeyConstraintsRequiringRenamedIndexes(CDatabase_Schema_Table_Diff $diff) {
        if (empty($diff->renamedIndexes) || !$diff->fromTable instanceof CDatabase_Schema_Table) {
            return [];
        }

        $foreignKeys = [];
        /** @var CDatabase_Schema_ForeignKeyConstraint[] $remainingForeignKeys */
        $remainingForeignKeys = array_diff_key(
            $diff->fromTable->getForeignKeys(),
            $diff->removedForeignKeys
        );

        foreach ($remainingForeignKeys as $foreignKey) {
            foreach ($diff->renamedIndexes as $index) {
                if ($foreignKey->intersectsIndexColumns($index)) {
                    $foreignKeys[] = $foreignKey;

                    break;
                }
            }
        }

        return $foreignKeys;
    }

    /**
     * @inheritdoc
     */
    protected function getPostAlterTableIndexForeignKeySQL(CDatabase_Schema_Table_Diff $diff) {
        return array_merge(
            parent::getPostAlterTableIndexForeignKeySQL($diff),
            $this->getPostAlterTableRenameIndexForeignKeySQL($diff)
        );
    }

    /**
     * @param CDatabase_Schema_Table_Diff $diff the table diff to gather the SQL for
     *
     * @return array
     */
    protected function getPostAlterTableRenameIndexForeignKeySQL(CDatabase_Schema_Table_Diff $diff) {
        $sql = [];
        $newName = $diff->getNewName();
        $tableName = null;
        if ($newName !== false) {
            $tableName = $newName->getQuotedName($this);
        } else {
            $tableName = $diff->getName($this)->getQuotedName($this);
        }

        foreach ($this->getRemainingForeignKeyConstraintsRequiringRenamedIndexes($diff) as $foreignKey) {
            if (in_array($foreignKey, $diff->changedForeignKeys, true)) {
                continue;
            }
            $sql[] = $this->getCreateForeignKeySQL($foreignKey, $tableName);
        }

        return $sql;
    }

    /**
     * @inheritDoc
     */
    protected function getCreateIndexSQLFlags(CDatabase_Schema_Index $index) {
        $type = '';
        if ($index->isUnique()) {
            $type .= 'UNIQUE ';
        } elseif ($index->hasFlag('fulltext')) {
            $type .= 'FULLTEXT ';
        } elseif ($index->hasFlag('spatial')) {
            $type .= 'SPATIAL ';
        }

        return $type;
    }

    /**
     * @inheritDoc
     */
    public function getIntegerTypeDeclarationSQL(array $field) {
        return 'INT' . $this->getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * @inheritDoc
     */
    public function getBigIntTypeDeclarationSQL(array $field) {
        return 'BIGINT' . $this->getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * @inheritDoc
     */
    public function getSmallIntTypeDeclarationSQL(array $field) {
        return 'SMALLINT' . $this->getCommonIntegerTypeDeclarationSQL($field);
    }

    /**
     * @inheritdoc
     */
    public function getFloatDeclarationSQL(array $field) {
        return 'DOUBLE PRECISION' . $this->getUnsignedDeclaration($field);
    }

    /**
     * @inheritdoc
     */
    public function getDecimalTypeDeclarationSQL(array $columnDef) {
        return parent::getDecimalTypeDeclarationSQL($columnDef) . $this->getUnsignedDeclaration($columnDef);
    }

    /**
     * Get unsigned declaration for a column.
     *
     * @param array $columnDef
     *
     * @return string
     */
    private function getUnsignedDeclaration(array $columnDef) {
        return !empty($columnDef['unsigned']) ? ' UNSIGNED' : '';
    }

    /**
     * @inheritDoc
     */
    protected function getCommonIntegerTypeDeclarationSQL(array $columnDef) {
        $autoinc = '';
        if (!empty($columnDef['autoincrement'])) {
            $autoinc = ' AUTO_INCREMENT';
        }

        return $this->getUnsignedDeclaration($columnDef) . $autoinc;
    }

    /**
     * @inheritDoc
     */
    public function getColumnCharsetDeclarationSQL($charset) {
        return 'CHARACTER SET ' . $charset;
    }

    /**
     * @inheritDoc
     */
    public function getAdvancedForeignKeyOptionsSQL(CDatabase_Schema_ForeignKeyConstraint $foreignKey) {
        $query = '';
        if ($foreignKey->hasOption('match')) {
            $query .= ' MATCH ' . $foreignKey->getOption('match');
        }
        $query .= parent::getAdvancedForeignKeyOptionsSQL($foreignKey);

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function getDropIndexSQL($index, $table = null) {
        if ($index instanceof CDatabase_Schema_Index) {
            $indexName = $index->getQuotedName($this);
        } elseif (is_string($index)) {
            $indexName = $index;
        } else {
            throw new \InvalidArgumentException('CDatabase_Platform_MySql::getDropIndexSQL() expects $index parameter to be string or \Doctrine\DBAL\Schema\Index.');
        }

        if ($table instanceof CDatabase_Schema_Table) {
            $table = $table->getQuotedName($this);
        } elseif (!is_string($table)) {
            throw new \InvalidArgumentException('CDatabase_Platform_MySql::getDropIndexSQL() expects $table parameter to be string or \Doctrine\DBAL\Schema\Table.');
        }

        if ($index instanceof CDatabase_Schema_Index && $index->isPrimary()) {
            // mysql primary keys are always named "PRIMARY",
            // so we cannot use them in statements because of them being keyword.
            return $this->getDropPrimaryKeySQL($table);
        }

        return 'DROP INDEX ' . $indexName . ' ON ' . $table;
    }

    /**
     * @param string $table
     *
     * @return string
     */
    protected function getDropPrimaryKeySQL($table) {
        return 'ALTER TABLE ' . $table . ' DROP PRIMARY KEY';
    }

    /**
     * @inheritDoc
     */
    public function getSetTransactionIsolationSQL($level) {
        return 'SET SESSION TRANSACTION ISOLATION LEVEL ' . $this->getTransactionIsolationLevelSQL($level);
    }

    /**
     * @inheritDoc
     */
    public function getName() {
        return 'mysql';
    }

    /**
     * @inheritDoc
     */
    public function getReadLockSQL() {
        return 'LOCK IN SHARE MODE';
    }

    /**
     * @inheritDoc
     */
    protected function initializeDoctrineTypeMappings() {
        $this->doctrineTypeMapping = [
            'bigint' => 'bigint',
            'binary' => 'binary',
            'bit' => 'boolean',
            'blob' => 'blob',
            'char' => 'string',
            'date' => 'date',
            'datetime' => 'datetime',
            'decimal' => 'decimal',
            'double' => 'float',
            'enum' => 'enum',
            'float' => 'float',
            'int' => 'integer',
            'integer' => 'integer',
            'longblob' => 'blob',
            'longtext' => 'text',
            'mediumblob' => 'blob',
            'mediumint' => 'integer',
            'mediumtext' => 'text',
            'numeric' => 'decimal',
            'real' => 'float',
            'set' => 'simple_array',
            'smallint' => 'smallint',
            'string' => 'string',
            'text' => 'text',
            'time' => 'time',
            'timestamp' => 'datetime',
            'tinyblob' => 'blob',
            'tinyint' => 'boolean',
            'tinytext' => 'text',
            'varbinary' => 'binary',
            'varchar' => 'string',
            'year' => 'date',
            'polygon' => 'polygon',
            'multipolygon' => 'multipolygon',

        ];
    }

    /**
     * @inheritDoc
     */
    public function getVarcharMaxLength() {
        return 65535;
    }

    /**
     * @inheritdoc
     */
    public function getBinaryMaxLength() {
        return 65535;
    }

    /**
     * @inheritDoc
     */
    protected function getReservedKeywordsClass() {
        return CDatabase_Platform_Keywords_MySql::class;
    }

    /**
     * {@inheritDoc}
     *
     * MySQL commits a transaction implicitly when DROP TABLE is executed, however not
     * if DROP TEMPORARY TABLE is executed.
     */
    public function getDropTemporaryTableSQL($table) {
        if ($table instanceof CDatabase_Schema_Table) {
            $table = $table->getQuotedName($this);
        } elseif (!is_string($table)) {
            throw new \InvalidArgumentException('getDropTemporaryTableSQL() expects $table parameter to be string or \Doctrine\DBAL\Schema\Table.');
        }

        return 'DROP TEMPORARY TABLE ' . $table;
    }

    /**
     * Gets the SQL Snippet used to declare a BLOB column type.
     *     TINYBLOB   : 2 ^  8 - 1 = 255
     *     BLOB       : 2 ^ 16 - 1 = 65535
     *     MEDIUMBLOB : 2 ^ 24 - 1 = 16777215
     *     LONGBLOB   : 2 ^ 32 - 1 = 4294967295.
     *
     * @param array $field
     *
     * @return string
     */
    public function getBlobTypeDeclarationSQL(array $field) {
        if (!empty($field['length']) && is_numeric($field['length'])) {
            $length = $field['length'];

            if ($length <= static::LENGTH_LIMIT_TINYBLOB) {
                return 'TINYBLOB';
            }

            if ($length <= static::LENGTH_LIMIT_BLOB) {
                return 'BLOB';
            }

            if ($length <= static::LENGTH_LIMIT_MEDIUMBLOB) {
                return 'MEDIUMBLOB';
            }
        }

        return 'LONGBLOB';
    }

    /**
     * @return bool
     */
    public function supportsColumnLengthIndexes() {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function quoteStringLiteral($str) {
        $str = str_replace('\\', '\\\\', $str); // MySQL requires backslashes to be escaped aswell.

        return parent::quoteStringLiteral($str);
    }

    /**
     * @inheritdoc
     */
    public function getDefaultTransactionIsolationLevel() {
        return CDatabase_TransactionIsolationLevel::REPEATABLE_READ;
    }

    /**
     * @param null|string $databaseName
     *
     * @return string
     */
    private function getDatabaseNameSQL($databaseName = null) {
        if ($databaseName !== null) {
            return $this->quoteStringLiteral($databaseName);
        }

        return $this->getCurrentDatabaseExpression();
    }
}
