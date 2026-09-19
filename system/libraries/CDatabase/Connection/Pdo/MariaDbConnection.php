<?php

class CDatabase_Connection_Pdo_MariaDbConnection extends CDatabase_Connection_Pdo_MySqlConnection {
    /**
     * Determine if the connected database is a MariaDB database.
     *
     * @return bool
     */
    public function isMaria() {
        return true;
    }

    /**
     * Get the server version for the connection.
     *
     * @return string
     */
    public function getServerVersion(): string {
        return cstr::between(parent::getServerVersion(), '5.5.5-', '-MariaDB');
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \CDatabase_Query_Grammar_MariaDbGrammar
     */
    protected function getDefaultQueryGrammar() {
        ($grammar = new CDatabase_Query_Grammar_MariaDbGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \CDatabase_Schema_Builder_MariaDbBuilder
     */
    public function getSchemaBuilder() {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new CDatabase_Schema_Builder_MariaDbBuilder($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \CDatabase_Schema_Grammar_MariaDbGrammar
     */
    protected function getDefaultSchemaGrammar() {
        ($grammar = new CDatabase_Schema_Grammar_MariaDbGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Get the schema state for the connection.
     *
     * @param null|callable $processFactory
     *
     * @return \CDatabase_Schema_SchemaState_MariaDbSchemaState
     */
    public function getSchemaState(?callable $processFactory = null) {
        return new CDatabase_Schema_SchemaState_MariaDbSchemaState($this, $processFactory);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \CDatabase_Query_Processor_MariaDbProcessor
     */
    protected function getDefaultPostProcessor() {
        return new CDatabase_Query_Processor_MariaDbProcessor();
    }
}
