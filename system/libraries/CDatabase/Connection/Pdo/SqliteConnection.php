<?php

class CDatabase_Connection_Pdo_SqliteConnection extends CDatabase_Connection {
    /**
     * Create a new database connection instance.
     *
     * @param \PDO|\Closure $pdo
     * @param string        $database
     * @param string        $tablePrefix
     *
     * @return void
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = []) {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        $enableForeignKeyConstraints = $this->getForeignKeyConstraintsConfigurationValue();

        if ($enableForeignKeyConstraints === null) {
            return;
        }

        $enableForeignKeyConstraints
            ? $this->getSchemaBuilder()->enableForeignKeyConstraints()
            : $this->getSchemaBuilder()->disableForeignKeyConstraints();
    }

    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param string $value
     *
     * @return string
     */
    protected function escapeBinary($value) {
        $hex = bin2hex($value);

        return "x'{$hex}'";
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \CDatabase_Query_Grammar_SqliteGrammar
     */
    protected function getDefaultQueryGrammar() {
        ($grammar = new CDatabase_Query_Grammar_SqliteGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \CDatabase_Schema_Builder_SqliteBuilder
     */
    public function getSchemaBuilder() {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new CDatabase_Schema_Builder_SqliteBuilder($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \CDatabase_Schema_Grammar_SqliteGrammar
     */
    protected function getDefaultSchemaGrammar() {
        ($grammar = new CDatabase_Schema_Grammar_SqliteGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Get the schema state for the connection.
     *
     * @throws \RuntimeException
     */
    public function getSchemaState(?callable $processFactory = null) {
        return new CDatabase_Schema_SchemaState_SqliteSchemaState($this, $processFactory);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \CDatabase_Query_Processor_SqliteProcessor
     */
    protected function getDefaultPostProcessor() {
        return new CDatabase_Query_Processor_SqliteProcessor();
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return \CDatabase_Doctrine_Driver_SqliteDriver
     */
    protected function getDoctrineDriver() {
        return new CDatabase_Doctrine_Driver_SqliteDriver();
    }

    /**
     * @inheritdoc
     *
     * @return CDatabase_Platform_Sqlite
     */
    public function getDefaultDatabasePlatform() {
        return new CDatabase_Platform_Sqlite();
    }

    /**
     * @inheritdoc
     *
     * @return CDatabase_Schema_Manager_Sqlite
     */
    public function getSchemaManager() {
        return new CDatabase_Schema_Manager_Sqlite($this);
    }

    /**
     * Get the database connection foreign key constraints configuration option.
     *
     * @return null|bool
     */
    protected function getForeignKeyConstraintsConfigurationValue() {
        return $this->getConfig('foreign_key_constraints');
    }
}
