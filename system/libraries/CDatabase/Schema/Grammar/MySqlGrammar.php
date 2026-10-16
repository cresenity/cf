<?php

class CDatabase_Schema_Grammar_MySqlGrammar extends CDatabase_Schema_Grammar {
    /**
     * The possible column modifiers.
     *
     * @var string[]
     */
    protected $modifiers = [
        'Unsigned', 'Charset', 'Collate', 'VirtualAs', 'StoredAs', 'Nullable', 'Invisible',
        'Srid', 'Default', 'Increment', 'Comment', 'After', 'First',
    ];

    /**
     * The possible column serials.
     *
     * @var string[]
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * Compile a create database command.
     *
     * @param string               $name
     * @param CDatabase_Connection $connection
     *
     * @return string
     */
    public function compileCreateDatabase($name, $connection) {
        $charset = $connection->getConfig('charset');
        $collation = $connection->getConfig('collation');
        if (!$charset || !$collation) {
            return sprintf(
                'create database %s',
                $this->wrapValue($name),
            );
        }

        return sprintf(
            'create database %s default character set %s default collate %s',
            $this->wrapValue($name),
            $this->wrapValue($charset),
            $this->wrapValue($collation),
        );
    }

    /**
     * Compile a drop database if exists command.
     *
     * @param string $name
     *
     * @return string
     */
    public function compileDropDatabaseIfExists($name) {
        return sprintf(
            'drop database if exists %s',
            $this->wrapValue($name)
        );
    }

    /**
     * Compile the query to determine the tables.
     *
     * @param string $database
     *
     * @return string
     */
    public function compileTables($database) {
        return sprintf(
            'select table_name as `name`, (data_length + index_length) as `size`, '
            . 'table_comment as `comment`, engine as `engine`, table_collation as `collation` '
            . "from information_schema.tables where table_schema = %s and table_type in ('BASE TABLE', 'SYSTEM VERSIONED') "
            . 'order by table_name',
            $this->quoteString($database)
        );
    }

    /**
     * Compile the query to determine the views.
     *
     * @param string $database
     *
     * @return string
     */
    public function compileViews($database) {
        return sprintf(
            'select table_name as `name`, view_definition as `definition` '
            . 'from information_schema.views where table_schema = %s '
            . 'order by table_name',
            $this->quoteString($database)
        );
    }

    /**
     * Compile the query to determine the columns.
     *
     * @param string $database
     * @param string $table
     *
     * @return string
     */
    public function compileColumns($database, $table) {
        return sprintf(
            'select column_name as `name`, data_type as `type_name`, column_type as `type`, '
            . 'collation_name as `collation`, is_nullable as `nullable`, '
            . 'column_default as `default`, column_comment as `comment`, '
            // . 'generation_expression as `expression`, extra as `extra` '
            . 'null as `expression`, extra as `extra` '
            . 'from information_schema.columns where table_schema = %s and table_name = %s '
            . 'order by ordinal_position asc',
            $this->quoteString($database),
            $this->quoteString($table)
        );
    }

    /**
     * Compile the query to determine the indexes.
     *
     * @param string $database
     * @param string $table
     *
     * @return string
     */
    public function compileIndexes($database, $table) {
        return sprintf(
            'select index_name as `name`, group_concat(column_name order by seq_in_index) as `columns`, '
            . 'index_type as `type`, not non_unique as `unique` '
            . 'from information_schema.statistics where table_schema = %s and table_name = %s '
            . 'group by index_name, index_type, non_unique',
            $this->quoteString($database),
            $this->quoteString($table)
        );
    }

    /**
     * Compile the query to determine the foreign keys.
     *
     * @param string $database
     * @param string $table
     *
     * @return string
     */
    public function compileForeignKeys($database, $table) {
        return sprintf(
            'select kc.constraint_name as `name`, '
            . 'group_concat(kc.column_name order by kc.ordinal_position) as `columns`, '
            . 'kc.referenced_table_schema as `foreign_schema`, '
            . 'kc.referenced_table_name as `foreign_table`, '
            . 'group_concat(kc.referenced_column_name order by kc.ordinal_position) as `foreign_columns`, '
            . 'rc.update_rule as `on_update`, '
            . 'rc.delete_rule as `on_delete` '
            . 'from information_schema.key_column_usage kc join information_schema.referential_constraints rc '
            . 'on kc.constraint_schema = rc.constraint_schema and kc.constraint_name = rc.constraint_name '
            . 'where kc.table_schema = %s and kc.table_name = %s and kc.referenced_table_name is not null '
            . 'group by kc.constraint_name, kc.referenced_table_schema, kc.referenced_table_name, rc.update_rule, rc.delete_rule',
            $this->quoteString($database),
            $this->quoteString($table)
        );
    }

    /**
     * Compile a create table command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     * @param CDatabase_Connection       $connection
     *
     * @return array
     */
    public function compileCreate(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command, CDatabase_Connection $connection) {
        $sql = $this->compileCreateTable(
            $blueprint,
            $command,
            $connection
        );

        // Once we have the primary SQL, we can add the encoding option to the SQL for
        // the table.  Then, we can check if a storage engine has been supplied for
        // the table. If so, we will add the engine declaration to the SQL query.
        $sql = $this->compileCreateEncoding(
            $sql,
            $connection,
            $blueprint
        );

        // Finally, we will append the engine configuration onto this SQL statement as
        // the final thing we do before returning this finished SQL. Once this gets
        // added the query will be ready to execute against the real connections.
        return $this->compileCreateEngine($sql, $connection, $blueprint);
    }

    /**
     * Create the main create table clause.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     * @param CDatabase_Connection       $connection
     *
     * @return array
     */
    protected function compileCreateTable($blueprint, $command, $connection) {
        $tableStructure = $this->getColumns($blueprint);

        if ($primaryKey = $this->getCommandByName($blueprint, 'primary')) {
            $tableStructure[] = sprintf(
                'primary key %s(%s)',
                $primaryKey->algorithm ? 'using ' . $primaryKey->algorithm : '',
                $this->columnize($primaryKey->columns)
            );

            $primaryKey->shouldBeSkipped = true;
        }

        return sprintf(
            '%s table %s (%s)',
            $blueprint->temporary ? 'create temporary' : 'create',
            $this->wrapTable($blueprint),
            implode(', ', $tableStructure)
        );
    }

    /**
     * Append the character set specifications to a command.
     *
     * @param string                     $sql
     * @param CDatabase_Connection       $connection
     * @param CDatabase_Schema_Blueprint $blueprint
     *
     * @return string
     */
    protected function compileCreateEncoding($sql, CDatabase_Connection $connection, CDatabase_Schema_Blueprint $blueprint) {
        // First we will set the character set if one has been set on either the create
        // blueprint itself or on the root configuration for the connection that the
        // table is being created on. We will add these to the create table query.
        if (isset($blueprint->charset)) {
            $sql .= ' default character set ' . $blueprint->charset;
        } elseif (!is_null($charset = $connection->getConfig('charset'))) {
            $sql .= ' default character set ' . $charset;
        }

        // Next we will add the collation to the create table statement if one has been
        // added to either this create table blueprint or the configuration for this
        // connection that the query is targeting. We'll add it to this SQL query.
        if (isset($blueprint->collation)) {
            $sql .= " collate '{$blueprint->collation}'";
        } elseif (!is_null($collation = $connection->getConfig('collation'))) {
            $sql .= " collate '{$collation}'";
        }

        return $sql;
    }

    /**
     * Append the engine specifications to a command.
     *
     * @param string                     $sql
     * @param CDatabase_Connection       $connection
     * @param CDatabase_Schema_Blueprint $blueprint
     *
     * @return string
     */
    protected function compileCreateEngine($sql, CDatabase_Connection $connection, CDatabase_Schema_Blueprint $blueprint) {
        if (isset($blueprint->engine)) {
            return $sql . ' engine = ' . $blueprint->engine;
        } elseif (!is_null($engine = $connection->getConfig('engine'))) {
            return $sql . ' engine = ' . $engine;
        }

        return $sql;
    }

    /**
     * Compile an add column command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return array
     */
    public function compileAdd(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $columns = $this->prefixArray('add', $this->getColumns($blueprint));

        return 'alter table ' . $this->wrapTable($blueprint) . ' ' . implode(', ', $columns);
    }

    /**
     * Compile the auto-incrementing column starting values.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileAutoIncrementStartingValues(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        if ($command->column->autoIncrement
            && $value = $command->column->get('startingValue', $command->column->get('from'))
        ) {
            return 'alter table ' . $this->wrapTable($blueprint) . ' auto_increment = ' . $value;
        }
    }

    /**
     * Compile a rename column command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     * @param null|CDatabase_Connection  $connection
     *
     * @return array|string
     */
    public function compileRenameColumn(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command, ?CDatabase_Connection $connection = null) {
        if ($connection !== null && !$connection->usingNativeSchemaOperations()) {
            return parent::compileRenameColumn($blueprint, $command);
        }

        return sprintf(
            'alter table %s rename column %s to %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile a change column command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return array
     */
    public function compileChange(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $columns = [];
        foreach ($blueprint->getChangedColumns() as $column) {
            $columns[] = $this->getColumn($blueprint, $column);
        }

        return 'alter table ' . $this->wrapTable($blueprint) . ' modify ' . implode(', modify ', $columns);
    }

    /**
     * Compile a primary key command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compilePrimary(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $command->name(null);

        return $this->compileKey($blueprint, $command, 'primary key');
    }

    /**
     * Compile a unique key command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileUnique(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return $this->compileKey($blueprint, $command, 'unique');
    }

    /**
     * Compile a plain index key command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileIndex(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return $this->compileKey($blueprint, $command, 'index');
    }

    /**
     * Compile a fulltext index key command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return string
     */
    public function compileFullText(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return $this->compileKey($blueprint, $command, 'fulltext');
    }

    /**
     * Compile a spatial index key command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileSpatialIndex(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return $this->compileKey($blueprint, $command, 'spatial index');
    }

    /**
     * Compile an index creation command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     * @param string                     $type
     *
     * @return string
     */
    protected function compileKey(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command, $type) {
        return sprintf(
            'alter table %s add %s %s%s(%s)',
            $this->wrapTable($blueprint),
            $type,
            $this->wrap($command->index),
            $command->algorithm ? ' using ' . $command->algorithm : '',
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a drop table command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDrop(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return 'drop table ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropIfExists(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return 'drop table if exists ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile a drop column command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropColumn(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $columns = $this->prefixArray('drop', $this->wrapArray($command->columns));

        return 'alter table ' . $this->wrapTable($blueprint) . ' ' . implode(', ', $columns);
    }

    /**
     * Compile a drop primary key command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropPrimary(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return 'alter table ' . $this->wrapTable($blueprint) . ' drop primary key';
    }

    /**
     * Compile a drop unique key command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropUnique(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop index {$index}";
    }

    /**
     * Compile a drop index command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropIndex(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop index {$index}";
    }

    /**
     * Compile a drop fulltext index command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropFullText(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * Compile a drop spatial index command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropSpatialIndex(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * Compile a drop foreign key command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropForeign(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop foreign key {$index}";
    }

    /**
     * Compile a rename table command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileRename(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $from = $this->wrapTable($blueprint);

        return "rename table {$from} to " . $this->wrapTable($command->to);
    }

    /**
     * Compile a rename index command.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $command
     *
     * @return string
     */
    public function compileRenameIndex(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return sprintf(
            'alter table %s rename index %s to %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile the SQL needed to drop all tables.
     *
     * @param array $tables
     *
     * @return string
     */
    public function compileDropAllTables($tables) {
        return 'drop table ' . implode(',', $this->wrapArray($tables));
    }

    /**
     * Compile the SQL needed to drop all views.
     *
     * @param array $views
     *
     * @return string
     */
    public function compileDropAllViews($views) {
        return 'drop view ' . implode(',', $this->wrapArray($views));
    }

    /**
     * Compile the SQL needed to retrieve all table names.
     *
     * @return string
     */
    public function compileGetAllTables() {
        return 'SHOW FULL TABLES WHERE table_type = \'BASE TABLE\'';
    }

    /**
     * Compile the SQL needed to retrieve all view names.
     *
     * @return string
     */
    public function compileGetAllViews() {
        return 'SHOW FULL TABLES WHERE table_type = \'VIEW\'';
    }

    /**
     * Compile the command to enable foreign key constraints.
     *
     * @return string
     */
    public function compileEnableForeignKeyConstraints() {
        return 'SET FOREIGN_KEY_CHECKS=1;';
    }

    /**
     * Compile the command to disable foreign key constraints.
     *
     * @return string
     */
    public function compileDisableForeignKeyConstraints() {
        return 'SET FOREIGN_KEY_CHECKS=0;';
    }

    /**
     * Create the column definition for a char type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeChar(CBase_Fluent $column) {
        return "char({$column->length})";
    }

    /**
     * Create the column definition for a string type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeString(CBase_Fluent $column) {
        return "varchar({$column->length})";
    }

    /**
     * Create the column definition for a tiny text type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTinyText(CBase_Fluent $column) {
        return 'tinytext';
    }

    /**
     * Create the column definition for a text type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeText(CBase_Fluent $column) {
        return 'text';
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeMediumText(CBase_Fluent $column) {
        return 'mediumtext';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeLongText(CBase_Fluent $column) {
        return 'longtext';
    }

    /**
     * Create the column definition for a big integer type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeBigInteger(CBase_Fluent $column) {
        return 'bigint';
    }

    /**
     * Create the column definition for an integer type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeInteger(CBase_Fluent $column) {
        return 'int';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeMediumInteger(CBase_Fluent $column) {
        return 'mediumint';
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTinyInteger(CBase_Fluent $column) {
        return 'tinyint';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeSmallInteger(CBase_Fluent $column) {
        return 'smallint';
    }

    /**
     * Create the column definition for a float type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeFloat(CBase_Fluent $column) {
        return $this->typeDouble($column);
    }

    /**
     * Create the column definition for a double type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeDouble(CBase_Fluent $column) {
        if ($column->total && $column->places) {
            return "double({$column->total}, {$column->places})";
        }

        return 'double';
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeDecimal(CBase_Fluent $column) {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeBoolean(CBase_Fluent $column) {
        return 'tinyint(1)';
    }

    /**
     * Create the column definition for an enumeration type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeEnum(CBase_Fluent $column) {
        return sprintf('enum(%s)', $this->quoteString($column->allowed));
    }

    /**
     * Create the column definition for a set enumeration type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeSet(CBase_Fluent $column) {
        return sprintf('set(%s)', $this->quoteString($column->allowed));
    }

    /**
     * Create the column definition for a json type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeJson(CBase_Fluent $column) {
        return 'json';
    }

    /**
     * Create the column definition for a jsonb type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeJsonb(CBase_Fluent $column) {
        return 'json';
    }

    /**
     * Create the column definition for a date type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeDate(CBase_Fluent $column) {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeDateTime(CBase_Fluent $column) {
        $columnType = $column->precision ? "datetime({$column->precision})" : 'datetime';

        $current = $column->precision ? "CURRENT_TIMESTAMP({$column->precision})" : 'CURRENT_TIMESTAMP';

        $columnType = $column->useCurrent ? "{$columnType} default {$current}" : $columnType;

        return $column->useCurrentOnUpdate ? "{$columnType} on update {$current}" : $columnType;
    }

    /**
     * Create the column definition for a date-time (with time zone) type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeDateTimeTz(CBase_Fluent $column) {
        return $this->typeDateTime($column);
    }

    /**
     * Create the column definition for a time type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTime(CBase_Fluent $column) {
        return $column->precision ? "time({$column->precision})" : 'time';
    }

    /**
     * Create the column definition for a time (with time zone) type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTimeTz(CBase_Fluent $column) {
        return $this->typeTime($column);
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTimestamp(CBase_Fluent $column) {
        $columnType = $column->precision ? "timestamp({$column->precision})" : 'timestamp';

        $current = $column->precision ? "CURRENT_TIMESTAMP({$column->precision})" : 'CURRENT_TIMESTAMP';

        $columnType = $column->useCurrent ? "{$columnType} default {$current}" : $columnType;

        return $column->useCurrentOnUpdate ? "{$columnType} on update {$current}" : $columnType;
    }

    /**
     * Create the column definition for a timestamp (with time zone) type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTimestampTz(CBase_Fluent $column) {
        return $this->typeTimestamp($column);
    }

    /**
     * Create the column definition for a year type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeYear(CBase_Fluent $column) {
        return 'year';
    }

    /**
     * Create the column definition for a binary type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeBinary(CBase_Fluent $column) {
        return 'blob';
    }

    /**
     * Create the column definition for a uuid type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeUuid(CBase_Fluent $column) {
        return 'char(36)';
    }

    /**
     * Create the column definition for an IP address type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeIpAddress(CBase_Fluent $column) {
        return 'varchar(45)';
    }

    /**
     * Create the column definition for a MAC address type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeMacAddress(CBase_Fluent $column) {
        return 'varchar(17)';
    }

    /**
     * Create the column definition for a spatial Geometry type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    protected function typeGeometry(CBase_Fluent $column) {
        $subtype = $column->subtype ? strtolower($column->subtype) : null;
        if (!in_array($subtype, ['point', 'linestring', 'polygon', 'geometrycollection', 'multipoint', 'multilinestring', 'multipolygon'])) {
            $subtype = null;
        }
        $srid = '';
        if ($column->srid && c::optional($this->connection)->isMaria()) {
            $srid = ' ref_system_id=' . $column->srid;
        } elseif ((bool) $column->srid) {
            $srid = ' srid ' . $column->srid;
        }

        return sprintf(
            '%s%s',
            $subtype ?? 'geometry',
            $srid
        );
    }

    /**
     * Create the column definition for a spatial Point type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    public function typePoint(CBase_Fluent $column) {
        return 'point';
    }

    /**
     * Create the column definition for a spatial LineString type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    public function typeLineString(CBase_Fluent $column) {
        return 'linestring';
    }

    /**
     * Create the column definition for a spatial Polygon type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    public function typePolygon(CBase_Fluent $column) {
        return 'polygon';
    }

    /**
     * Create the column definition for a spatial GeometryCollection type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    public function typeGeometryCollection(CBase_Fluent $column) {
        return 'geometrycollection';
    }

    /**
     * Create the column definition for a spatial MultiPoint type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    public function typeMultiPoint(CBase_Fluent $column) {
        return 'multipoint';
    }

    /**
     * Create the column definition for a spatial MultiLineString type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    public function typeMultiLineString(CBase_Fluent $column) {
        return 'multilinestring';
    }

    /**
     * Create the column definition for a spatial MultiPolygon type.
     *
     * @param CBase_Fluent $column
     *
     * @return string
     */
    public function typeMultiPolygon(CBase_Fluent $column) {
        return 'multipolygon';
    }

    /**
     * Create the column definition for a generated, computed column type.
     *
     * @param CBase_Fluent $column
     *
     * @throws RuntimeException
     *
     * @return void
     */
    protected function typeComputed(CBase_Fluent $column) {
        throw new RuntimeException('This database driver requires a type, see the virtualAs / storedAs modifiers.');
    }

    /**
     * Get the SQL for a generated virtual column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyVirtualAs(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->virtualAs)) {
            return " as ({$column->virtualAs})";
        }
    }

    /**
     * Get the SQL for a generated stored column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyStoredAs(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->storedAs)) {
            return " as ({$column->storedAs}) stored";
        }
    }

    /**
     * Get the SQL for an unsigned column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyUnsigned(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if ($column->unsigned) {
            return ' unsigned';
        }
    }

    /**
     * Get the SQL for a character set column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyCharset(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->charset)) {
            return ' character set ' . $column->charset;
        }
    }

    /**
     * Get the SQL for a collation column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyCollate(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->collation)) {
            return " collate '{$column->collation}'";
        }
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyNullable(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (is_null($column->virtualAs) && is_null($column->storedAs)) {
            return $column->nullable ? ' null' : ' not null';
        }

        if ($column->nullable === false) {
            return ' not null';
        }
    }

    /**
     * Get the SQL for an invisible column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyInvisible(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->invisible)) {
            return ' invisible';
        }
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyDefault(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->default)) {
            return ' default ' . $this->getDefaultValue($column->default);
        }
    }

    /**
     * Get the SQL for an "on update" column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyOnUpdate(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->onUpdate)) {
            return ' on update ' . $this->getValue($column->onUpdate);
        }
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyIncrement(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' auto_increment primary key';
        }
    }

    /**
     * Get the SQL for a "first" column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyFirst(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->first)) {
            return ' first';
        }
    }

    /**
     * Get the SQL for an "after" column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyAfter(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->after)) {
            return ' after ' . $this->wrap($column->after);
        }
    }

    /**
     * Get the SQL for a "comment" column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyComment(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->comment)) {
            return " comment '" . addslashes($column->comment) . "'";
        }
    }

    /**
     * Get the SQL for a SRID column modifier.
     *
     * @param CDatabase_Schema_Blueprint $blueprint
     * @param CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifySrid(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->srid) && is_int($column->srid) && $column->srid > 0) {
            return ' srid ' . $column->srid;
        }
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param string $value
     *
     * @return string
     */
    protected function wrapValue($value) {
        if ($value !== '*') {
            return '`' . str_replace('`', '``', $value) . '`';
        }

        return $value;
    }
}
