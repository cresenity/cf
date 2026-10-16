<?php
class CDatabase_Schema_Grammar_ChangeColumn {
    /**
     * Compile a change column command into a series of SQL statements.
     *
     * $connection sebelumnya type-hint ke facade `CDatabase` (bukan `CDatabase_Connection`,
     * yang method `getSchemaManager()`-nya sungguh dipakai persis di baris pertama method ini)
     * - kelas ini sendiri tidak pernah dipanggil dari mana pun sebelum #-21866, jadi salah
     * ketik itu tidak pernah ketahuan lewat jalur manapun. Diperbaiki di sini supaya benar-benar
     * bisa dipanggil (SqliteGrammar::compileChange() sekarang memanggilnya).
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public static function compile(CDatabase_Schema_Grammar $grammar, CDatabase_Schema_Blueprint $blueprint, CBase_Fluent $command, CDatabase_Connection $connection) {
        $schema = $connection->getSchemaManager();
        $databasePlatform = $schema->getDatabasePlatform();
        $databasePlatform->registerDoctrineTypeMapping('enum', 'string');

        $tableDiff = static::getChangedDiff(
            $grammar,
            $blueprint,
            $schema
        );

        if ($tableDiff !== false) {
            return (array) $databasePlatform->getAlterTableSQL($tableDiff);
        }

        return [];
    }

    /**
     * Get the Doctrine table difference for the given changes.
     *
     * @param \CDatabase_Schema_Grammar $grammar
     *
     * @return \CDatabase_Schema_Table_Diff|bool
     */
    protected static function getChangedDiff($grammar, CDatabase_Schema_Blueprint $blueprint, CDatabase_Schema_Manager $schema) {
        $current = $schema->listTableDetails($grammar->getTablePrefix() . $blueprint->getTable());

        return (new CDatabase_Schema_Comparator())->diffTable(
            $current,
            static::getTableWithColumnChanges($blueprint, $current)
        );
    }

    /**
     * Get a copy of the given Doctrine table after making the column changes.
     *
     * @return \CDatabase_Schema_Table
     */
    protected static function getTableWithColumnChanges(CDatabase_Schema_Blueprint $blueprint, CDatabase_Schema_Table $table) {
        $table = clone $table;

        foreach ($blueprint->getChangedColumns() as $fluent) {
            $column = static::getDoctrineColumn($table, $fluent);

            // Here we will spin through each fluent column definition and map it to the proper
            // Doctrine column definitions - which is necessary because CF and Doctrine
            // use some different terminology for various column attributes on the tables.
            foreach ($fluent->getAttributes() as $key => $value) {
                if (!is_null($option = static::mapFluentOptionToDoctrine($key))) {
                    if (method_exists($column, $method = 'set' . ucfirst($option))) {
                        $column->{$method}(static::mapFluentValueToDoctrine($option, $value));

                        continue;
                    }

                    $column->setCustomSchemaOption($option, static::mapFluentValueToDoctrine($option, $value));
                }
            }
        }

        return $table;
    }

    /**
     * Get the Doctrine column instance for a column change.
     *
     * @return \CDatabase_Schema_Column
     */
    protected static function getDoctrineColumn(CDatabase_Schema_Table $table, CBase_Fluent $fluent) {
        return $table->changeColumn(
            $fluent['name'],
            static::getDoctrineColumnChangeOptions($fluent)
        )->getColumn($fluent['name']);
    }

    /**
     * Get the Doctrine column change options.
     *
     * @return array
     */
    protected static function getDoctrineColumnChangeOptions(CBase_Fluent $fluent) {
        $options = ['type' => static::getDoctrineColumnType($fluent['type'])];

        if (in_array($fluent['type'], ['text', 'mediumText', 'longText'])) {
            $options['length'] = static::calculateDoctrineTextLength($fluent['type']);
        }

        if (static::doesntNeedCharacterOptions($fluent['type'])) {
            $options['customSchemaOptions'] = [
                'collation' => '',
                'charset' => '',
            ];
        }

        return $options;
    }

    /**
     * Get the doctrine column type.
     *
     * @param string $type
     *
     * @return \CDatabase_Type
     */
    protected static function getDoctrineColumnType($type) {
        $type = strtolower($type);

        switch ($type) {
            case 'biginteger':
                $type = 'bigint';

                break;
            case 'smallinteger':
                $type = 'smallint';

                break;
            case 'mediumtext':
            case 'longtext':
                $type = 'text';

                break;
            case 'binary':
                $type = 'blob';

                break;
            case 'uuid':
                $type = 'guid';

                break;
        }

        return CDatabase_Type::getType($type);
    }

    /**
     * Calculate the proper column length to force the Doctrine text type.
     *
     * @param string $type
     *
     * @return int
     */
    protected static function calculateDoctrineTextLength($type) {
        switch ($type) {
            case 'mediumText':
                return 65535 + 1;
            case 'longText':
                return 16777215 + 1;
            default:
                return 255 + 1;
        }
    }

    /**
     * Determine if the given type does not need character / collation options.
     *
     * @param string $type
     *
     * @return bool
     */
    protected static function doesntNeedCharacterOptions($type) {
        return in_array($type, [
            'bigInteger',
            'binary',
            'boolean',
            'date',
            'dateTime',
            'decimal',
            'double',
            'float',
            'integer',
            'json',
            'mediumInteger',
            'smallInteger',
            'time',
            'tinyInteger',
        ]);
    }

    /**
     * Get the matching Doctrine option for a given Fluent attribute name.
     *
     * @param string $attribute
     *
     * @return null|string
     */
    protected static function mapFluentOptionToDoctrine($attribute) {
        switch ($attribute) {
            case 'type':
            case 'name':
                return;
            case 'nullable':
                return 'notnull';
            case 'total':
                return 'precision';
            case 'places':
                return 'scale';
            default:
                return $attribute;
        }
    }

    /**
     * Get the matching Doctrine value for a given Fluent attribute.
     *
     * @param string $option
     * @param mixed  $value
     *
     * @return mixed
     */
    protected static function mapFluentValueToDoctrine($option, $value) {
        return $option === 'notnull' ? !$value : $value;
    }
}
