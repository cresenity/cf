<?php

class CDatabase_Connection_Pdo_PostgresConnection extends CDatabase_Connection {
    /**
     * Escape a binary value for safe SQL embedding.
     *
     * @param string $value
     *
     * @return string
     */
    protected function escapeBinary($value) {
        $hex = bin2hex($value);

        return "'\x{$hex}'::bytea";
    }

    /**
     * Escape a bool value for safe SQL embedding.
     *
     * @param bool $value
     *
     * @return string
     */
    protected function escapeBool($value) {
        return $value ? 'true' : 'false';
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \CDatabase_Query_Grammar_PostgresGrammar
     */
    protected function getDefaultQueryGrammar() {
        ($grammar = new CDatabase_Query_Grammar_PostgresGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \CDatabase_Schema_Builder_PostgresBuilder
     */
    public function getSchemaBuilder() {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new CDatabase_Schema_Builder_PostgresBuilder($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \CDatabase_Schema_Grammar_PostgresGrammar
     */
    protected function getDefaultSchemaGrammar() {
        ($grammar = new CDatabase_Schema_Grammar_PostgresGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Get the schema state for the connection.
     *
     * @return \CDatabase_Schema_SchemaState_PostgresSchemaState
     */
    public function getSchemaState(?callable $processFactory = null) {
        return new CDatabase_Schema_SchemaState_PostgresSchemaState($this, $processFactory);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \CDatabase_Query_Processor_PostgresProcessor
     */
    protected function getDefaultPostProcessor() {
        return new CDatabase_Query_Processor_PostgresProcessor();
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return \CDatabase_Doctrine_PostgresDriver
     */
    protected function getDoctrineDriver() {
        return new CDatabase_Doctrine_Driver_PostgresDriver();
    }

    /**
     * @inheritdoc
     *
     * @return CDatabase_Platform_Doctrine
     */
    public function getDefaultDatabasePlatform() {
        return new CDatabase_Platform_Doctrine($this->getDoctrineConnection()->getDatabasePlatform());
    }

    /**
     * @inheritdoc
     *
     * @return CDatabase_Schema_Manager_Doctrine
     */
    public function getSchemaManager() {
        return new CDatabase_Schema_Manager_Doctrine($this, $this->getDoctrineSchemaManager());
    }

    /**
     * Postgres punya `ALTER COLUMN` native tanpa syarat versi - PostgresGrammar::compileChange()/
     * compileRenameColumn() memakainya langsung, tanpa perlu diffing lewat getSchemaManager().
     *
     * @inheritdoc
     */
    public function usingNativeSchemaOperations() {
        return true;
    }
}
