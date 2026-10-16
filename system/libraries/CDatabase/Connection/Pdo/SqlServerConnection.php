<?php

class CDatabase_Connection_Pdo_SqlServerConnection extends CDatabase_Connection {
    /**
     * Execute a Closure within a transaction.
     *
     * @param int $attempts
     *
     * @throws \Throwable
     *
     * @return mixed
     */
    public function transaction(Closure $callback, $attempts = 1) {
        for ($a = 1; $a <= $attempts; $a++) {
            if ($this->getDriverName() === 'sqlsrv') {
                return parent::transaction($callback, $attempts);
            }

            $this->getPdo()->exec('BEGIN TRAN');

            // We'll simply execute the given callback within a try / catch block
            // and if we catch any exception we can rollback the transaction
            // so that none of the changes are persisted to the database.
            try {
                $result = $callback($this);

                $this->getPdo()->exec('COMMIT TRAN');
            } catch (Throwable $e) {
                // If we catch an exception, we will rollback so nothing gets messed
                // up in the database. Then we'll re-throw the exception so it can
                // be handled how the developer sees fit for their applications.
                $this->getPdo()->exec('ROLLBACK TRAN');

                throw $e;
            }

            return $result;
        }
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

        return "0x{$hex}";
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \CDatabase_Query_Grammar_SqlServerGrammar
     */
    protected function getDefaultQueryGrammar() {
        ($grammar = new CDatabase_Query_Grammar_SqlServerGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \CDatabase_Schema_Builder_SqlServerBuilder
     */
    public function getSchemaBuilder() {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new CDatabase_Schema_Builder_SqlServerBuilder($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \CDatabase_Schema_Grammar_SqlServerGrammar
     */
    protected function getDefaultSchemaGrammar() {
        ($grammar = new CDatabase_Schema_Grammar_SqlServerGrammar())->setConnection($this);

        return $this->withTablePrefix($grammar);
    }

    /**
     * Get the schema state for the connection.
     *
     * @throws \RuntimeException
     */
    public function getSchemaState(?callable $processFactory = null) {
        throw new RuntimeException('Schema dumping is not supported when using SQL Server.');
    }

    /**
     * Get the default post processor instance.
     *
     * @return \CDatabase_Query_Processor_SqlServerProcessor
     */
    protected function getDefaultPostProcessor() {
        return new CDatabase_Query_Processor_SqlServerProcessor();
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return \CDatabase_Doctrine_Driver_SqlServerDriver
     */
    protected function getDoctrineDriver() {
        return new CDatabase_Doctrine_Driver_SqlServerDriver();
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
     * SQL Server punya `ALTER COLUMN`/`sp_rename` native tanpa syarat versi -
     * SqlServerGrammar::compileChange()/compileRenameColumn() memakainya langsung.
     *
     * @inheritdoc
     */
    public function usingNativeSchemaOperations() {
        return true;
    }
}
