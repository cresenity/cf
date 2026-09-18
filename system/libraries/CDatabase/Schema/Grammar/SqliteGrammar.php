<?php
class CDatabase_Schema_Grammar_SqliteGrammar extends CDatabase_Schema_Grammar {
    /**
     * The possible column modifiers.
     *
     * @var string[]
     */
    protected $modifiers = ['VirtualAs', 'StoredAs', 'Nullable', 'Default', 'Increment'];

    /**
     * The columns available as serials.
     *
     * @var string[]
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * Compile the query to determine if a table exists.
     *
     * @return string
     */
    public function compileTableExists($schema, $table) {
        return "select * from sqlite_master where type = 'table' and name = ?";
    }

    /**
     * Compile the query to determine the list of columns.
     *
     * @param string $table
     *
     * @return string
     */
    public function compileColumnListing($table) {
        return 'pragma table_info(' . $this->wrap(str_replace('.', '__', $table)) . ')';
    }

    /**
     * Compile a create table command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return string
     */
    public function compileCreate(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return sprintf(
            '%s table %s (%s%s%s)',
            $blueprint->temporary ? 'create temporary' : 'create',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint)),
            (string) $this->addForeignKeys($blueprint),
            (string) $this->addPrimaryKeys($blueprint)
        );
    }

    /**
     * Get the foreign key syntax for a table creation statement.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     *
     * @return null|string
     */
    protected function addForeignKeys(CDatabase_Schema_Blueprint $blueprint) {
        $foreigns = $this->getCommandsByName($blueprint, 'foreign');

        return c::collect($foreigns)->reduce(function ($sql, $foreign) {
            // Once we have all the foreign key commands for the table creation statement
            // we'll loop through each of them and add them to the create table SQL we
            // are building, since SQLite needs foreign keys on the tables creation.
            $sql .= $this->getForeignKey($foreign);

            if (!is_null($foreign->onDelete)) {
                $sql .= " on delete {$foreign->onDelete}";
            }

            // If this foreign key specifies the action to be taken on update we will add
            // that to the statement here. We'll append it to this SQL and then return
            // the SQL so we can keep adding any other foreign constraints onto this.
            if (!is_null($foreign->onUpdate)) {
                $sql .= " on update {$foreign->onUpdate}";
            }

            return $sql;
        }, '');
    }

    /**
     * Get the SQL for the foreign key.
     *
     * @param \CBase_Fluent $foreign
     *
     * @return string
     */
    protected function getForeignKey($foreign) {
        // We need to columnize the columns that the foreign key is being defined for
        // so that it is a properly formatted list. Once we have done this, we can
        // return the foreign key SQL declaration to the calling method for use.
        return sprintf(
            ', foreign key(%s) references %s(%s)',
            $this->columnize($foreign->columns),
            $this->wrapTable($foreign->on),
            $this->columnize((array) $foreign->references)
        );
    }

    /**
     * Get the primary key syntax for a table creation statement.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     *
     * @return null|string
     */
    protected function addPrimaryKeys(CDatabase_Schema_Blueprint $blueprint) {
        if (!is_null($primary = $this->getCommandByName($blueprint, 'primary'))) {
            return ", primary key ({$this->columnize($primary->columns)})";
        }
    }

    /**
     * Compile alter table commands for adding columns.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return array
     */
    public function compileAdd(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $columns = $this->prefixArray('add column', $this->getColumns($blueprint));

        return c::collect($columns)->reject(function ($column) {
            return preg_match('/as \(.*\) stored/', $column) > 0;
        })->map(function ($column) use ($blueprint) {
            return 'alter table ' . $this->wrapTable($blueprint) . ' ' . $column;
        })->all();
    }

    /**
     * Compile a unique key command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return string
     */
    public function compileUnique(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return sprintf(
            'create unique index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a plain index key command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return string
     */
    public function compileIndex(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return sprintf(
            'create index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a spatial index key command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function compileSpatialIndex(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        throw new RuntimeException('The database driver in use does not support spatial indexes.');
    }

    /**
     * Compile a foreign key command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return string
     */
    public function compileForeign(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        // Handled on table creation...
    }

    /**
     * Compile a drop table command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDrop(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return 'drop table ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropIfExists(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        return 'drop table if exists ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile the SQL needed to drop all tables.
     *
     * @return string
     */
    public function compileDropAllTables() {
        return "delete from sqlite_master where type in ('table', 'index', 'trigger')";
    }

    /**
     * Compile the SQL needed to drop all views.
     *
     * @return string
     */
    public function compileDropAllViews() {
        return "delete from sqlite_master where type in ('view')";
    }

    /**
     * Compile the SQL needed to retrieve all table names.
     *
     * @return string
     */
    public function compileGetAllTables() {
        return 'select type, name from sqlite_master where type = \'table\' and name not like \'sqlite_%\'';
    }

    /**
     * Compile the SQL needed to retrieve all view names.
     *
     * @return string
     */
    public function compileGetAllViews() {
        return 'select type, name from sqlite_master where type = \'view\'';
    }

    /**
     * Compile the SQL needed to rebuild the database.
     *
     * @return string
     */
    public function compileRebuild() {
        return 'vacuum';
    }

    /**
     * Compile a drop column command.
     *
     * @param \CDatabase_Schema_Blueprint     $blueprint
     * @param \CBase_Fluent                   $command
     * @param \Illuminate\Database\Connection $connection
     *
     * @return array
     */
    public function compileDropColumn(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command, CDatabase_Connection $connection) {
        $tableDiff = $this->getDoctrineTableDiff(
            $blueprint,
            $schema = $connection->getDoctrineSchemaManager()
        );

        foreach ($command->columns as $name) {
            $tableDiff->removedColumns[$name] = $connection->getDoctrineColumn(
                $this->getTablePrefix() . $blueprint->getTable(),
                $name
            );
        }

        return (array) $schema->getDatabasePlatform()->getAlterTableSQL($tableDiff);
    }

    /**
     * Compile a drop unique key command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropUnique(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $index = $this->wrap($command->index);

        return "drop index {$index}";
    }

    /**
     * Compile a drop index command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return string
     */
    public function compileDropIndex(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $index = $this->wrap($command->index);

        return "drop index {$index}";
    }

    /**
     * Compile a drop spatial index command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function compileDropSpatialIndex(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        throw new RuntimeException('The database driver in use does not support spatial indexes.');
    }

    /**
     * Compile a rename table command.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $command
     *
     * @return string
     */
    public function compileRename(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command) {
        $from = $this->wrapTable($blueprint);

        return "alter table {$from} rename to " . $this->wrapTable($command->to);
    }

    /**
     * Compile a rename index command.
     *
     * @param \CDatabase_Schema_Blueprint     $blueprint
     * @param \CBase_Fluent                   $command
     * @param \Illuminate\Database\Connection $connection
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public function compileRenameIndex(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command, CDatabase_Connection $connection) {
        $schemaManager = $connection->getDoctrineSchemaManager();

        $indexes = $schemaManager->listTableIndexes($this->getTablePrefix() . $blueprint->getTable());

        $index = carr::get($indexes, $command->from);

        if (!$index) {
            throw new RuntimeException("Index [{$command->from}] does not exist.");
        }

        $newIndex = new CDatabase_Schema_Index(
            $command->to,
            $index->getColumns(),
            $index->isUnique(),
            $index->isPrimary(),
            $index->getFlags(),
            $index->getOptions()
        );

        $platform = $schemaManager->getDatabasePlatform();

        return [
            $platform->getDropIndexSQL($command->from, $this->getTablePrefix() . $blueprint->getTable()),
            $platform->getCreateIndexSQL($newIndex, $this->getTablePrefix() . $blueprint->getTable()),
        ];
    }

    /**
     * Compile the command to enable foreign key constraints.
     *
     * @return string
     */
    public function compileEnableForeignKeyConstraints() {
        return 'PRAGMA foreign_keys = ON;';
    }

    /**
     * Compile the command to disable foreign key constraints.
     *
     * @return string
     */
    public function compileDisableForeignKeyConstraints() {
        return 'PRAGMA foreign_keys = OFF;';
    }

    /**
     * Compile the SQL needed to enable a writable schema.
     *
     * @return string
     */
    public function compileEnableWriteableSchema() {
        return 'PRAGMA writable_schema = 1;';
    }

    /**
     * Compile the SQL needed to disable a writable schema.
     *
     * @return string
     */
    public function compileDisableWriteableSchema() {
        return 'PRAGMA writable_schema = 0;';
    }

    /**
     * Create the column definition for a char type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeChar(CBase_Fluent $column) {
        return 'varchar';
    }

    /**
     * Create the column definition for a string type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeString(CBase_Fluent $column) {
        return 'varchar';
    }

    /**
     * Create the column definition for a tiny text type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTinyText(CBase_Fluent $column) {
        return 'text';
    }

    /**
     * Create the column definition for a text type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeText(CBase_Fluent $column) {
        return 'text';
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeMediumText(CBase_Fluent $column) {
        return 'text';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeLongText(CBase_Fluent $column) {
        return 'text';
    }

    /**
     * Create the column definition for an integer type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeInteger(CBase_Fluent $column) {
        return 'integer';
    }

    /**
     * Create the column definition for a big integer type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeBigInteger(CBase_Fluent $column) {
        return 'integer';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeMediumInteger(CBase_Fluent $column) {
        return 'integer';
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTinyInteger(CBase_Fluent $column) {
        return 'integer';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeSmallInteger(CBase_Fluent $column) {
        return 'integer';
    }

    /**
     * Create the column definition for a float type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeFloat(CBase_Fluent $column) {
        return 'float';
    }

    /**
     * Create the column definition for a double type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeDouble(CBase_Fluent $column) {
        return 'float';
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeDecimal(CBase_Fluent $column) {
        return 'numeric';
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeBoolean(CBase_Fluent $column) {
        return 'tinyint(1)';
    }

    /**
     * Create the column definition for an enumeration type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeEnum(CBase_Fluent $column) {
        return sprintf(
            'varchar check ("%s" in (%s))',
            $column->name,
            $this->quoteString($column->allowed)
        );
    }

    /**
     * Create the column definition for a json type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeJson(CBase_Fluent $column) {
        return 'text';
    }

    /**
     * Create the column definition for a jsonb type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeJsonb(CBase_Fluent $column) {
        return 'text';
    }

    /**
     * Create the column definition for a date type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeDate(CBase_Fluent $column) {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeDateTime(CBase_Fluent $column) {
        return $this->typeTimestamp($column);
    }

    /**
     * Create the column definition for a date-time (with time zone) type.
     *
     * Note: "SQLite does not have a storage class set aside for storing dates and/or times."
     *
     * @param \CBase_Fluent $column
     *
     * @link https://www.sqlite.org/datatype3.html
     *
     * @return string
     */
    protected function typeDateTimeTz(CBase_Fluent $column) {
        return $this->typeDateTime($column);
    }

    /**
     * Create the column definition for a time type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTime(CBase_Fluent $column) {
        return 'time';
    }

    /**
     * Create the column definition for a time (with time zone) type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTimeTz(CBase_Fluent $column) {
        return $this->typeTime($column);
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTimestamp(CBase_Fluent $column) {
        return $column->useCurrent ? 'datetime default CURRENT_TIMESTAMP' : 'datetime';
    }

    /**
     * Create the column definition for a timestamp (with time zone) type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeTimestampTz(CBase_Fluent $column) {
        return $this->typeTimestamp($column);
    }

    /**
     * Create the column definition for a year type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeYear(CBase_Fluent $column) {
        return $this->typeInteger($column);
    }

    /**
     * Create the column definition for a binary type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeBinary(CBase_Fluent $column) {
        return 'blob';
    }

    /**
     * Create the column definition for a uuid type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeUuid(CBase_Fluent $column) {
        return 'varchar';
    }

    /**
     * Create the column definition for an IP address type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeIpAddress(CBase_Fluent $column) {
        return 'varchar';
    }

    /**
     * Create the column definition for a MAC address type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    protected function typeMacAddress(CBase_Fluent $column) {
        return 'varchar';
    }

    /**
     * Create the column definition for a spatial Geometry type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    public function typeGeometry(CBase_Fluent $column) {
        return 'geometry';
    }

    /**
     * Create the column definition for a spatial Point type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    public function typePoint(CBase_Fluent $column) {
        return 'point';
    }

    /**
     * Create the column definition for a spatial LineString type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    public function typeLineString(CBase_Fluent $column) {
        return 'linestring';
    }

    /**
     * Create the column definition for a spatial Polygon type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    public function typePolygon(CBase_Fluent $column) {
        return 'polygon';
    }

    /**
     * Create the column definition for a spatial GeometryCollection type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    public function typeGeometryCollection(CBase_Fluent $column) {
        return 'geometrycollection';
    }

    /**
     * Create the column definition for a spatial MultiPoint type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    public function typeMultiPoint(CBase_Fluent $column) {
        return 'multipoint';
    }

    /**
     * Create the column definition for a spatial MultiLineString type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    public function typeMultiLineString(CBase_Fluent $column) {
        return 'multilinestring';
    }

    /**
     * Create the column definition for a spatial MultiPolygon type.
     *
     * @param \CBase_Fluent $column
     *
     * @return string
     */
    public function typeMultiPolygon(CBase_Fluent $column) {
        return 'multipolygon';
    }

    /**
     * Create the column definition for a generated, computed column type.
     *
     * @param \CBase_Fluent $column
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function typeComputed(CBase_Fluent $column) {
        throw new RuntimeException('This database driver requires a type, see the virtualAs / storedAs modifiers.');
    }

    /**
     * Get the SQL for a generated virtual column modifier.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyVirtualAs(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($virtualAs = $column->virtualAsJson)) {
            if ($this->isJsonSelector($virtualAs)) {
                $virtualAs = $this->wrapJsonSelector($virtualAs);
            }

            return " as ({$virtualAs})";
        }

        if (!is_null($virtualAs = $column->virtualAs)) {
            return " as ({$virtualAs})";
        }
    }

    /**
     * Get the SQL for a generated stored column modifier.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyStoredAs(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($storedAs = $column->storedAsJson)) {
            if ($this->isJsonSelector($storedAs)) {
                $storedAs = $this->wrapJsonSelector($storedAs);
            }

            return " as ({$storedAs}) stored";
        }

        if (!is_null($storedAs = $column->storedAs)) {
            return " as ({$column->storedAs}) stored";
        }
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyNullable(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (is_null($column->virtualAs)
            && is_null($column->virtualAsJson)
            && is_null($column->storedAs)
            && is_null($column->storedAsJson)
        ) {
            return $column->nullable ? '' : ' not null';
        }

        if ($column->nullable === false) {
            return ' not null';
        }
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyDefault(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (!is_null($column->default) && is_null($column->virtualAs) && is_null($column->virtualAsJson) && is_null($column->storedAs)) {
            return ' default ' . $this->getDefaultValue($column->default);
        }
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @param \CDatabase_Schema_Blueprint $blueprint
     * @param \CBase_Fluent               $column
     *
     * @return null|string
     */
    protected function modifyIncrement(CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $column) {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' primary key autoincrement';
        }
    }

    /**
     * Wrap the given JSON selector.
     *
     * @param string $value
     *
     * @return string
     */
    protected function wrapJsonSelector($value) {
        list($field, $path) = $this->wrapJsonFieldAndPath($value);

        return 'json_extract(' . $field . $path . ')';
    }
}
