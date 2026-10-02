<?php

/**
 * @property array $rows                     rows the array-driven table is seeded with, set by the consuming model
 * @property array $schema                    column name => type map used when $rows is empty, set by the consuming model
 * @property int   $arrayDriverInsertChunkSize number of rows inserted per batch during migrate(), set by the consuming model
 */
trait CModel_ArrayDriver_ArrayDriverTrait {
    /**
     * @var array
     */
    protected static $arrayDriverConnection;

    public function getRows() {
        return $this->rows;
    }

    public function getSchema() {
        return $this->schema ?? [];
    }

    protected function arrayDriverCacheReferencePath() {
        return (new \ReflectionClass(static::class))->getFileName();
    }

    protected function arrayDriverShouldCache() {
        return property_exists(static::class, 'rows');
    }

    /**
     * @param mixed $connection
     *
     * @return CDatabase
     */
    public static function resolveConnection($connection = null) {
        return static::$arrayDriverConnection;
    }

    public static function bootArrayDriverTrait() {
        $instance = (new static());

        $cacheFileName = CF::config('model.array_driver.cache-prefix', 'array-driver') . '-' . cstr::kebab(str_replace('\\', '', static::class)) . '.sqlite';
        $cacheDirectory = static::arrayDriverCacheDirectory();
        $cachePath = $cacheDirectory . '/' . $cacheFileName;
        $dataPath = $instance->arrayDriverCacheReferencePath();

        $states = [
            'cache-file-found-and-up-to-date' => function () use ($cachePath) {
                static::setSqliteConnection($cachePath);
            },
            'cache-file-not-found-or-stale' => function () use ($cachePath, $dataPath, $instance) {
                file_put_contents($cachePath, '');

                static::setSqliteConnection($cachePath);

                $instance->migrate();

                touch($cachePath, filemtime($dataPath));
            },
            'no-caching-capabilities' => function () use ($instance) {
                static::setSqliteConnection(':memory:');

                $instance->migrate();
            },
        ];

        switch (true) {
            case !$instance->arrayDriverShouldCache():
                $states['no-caching-capabilities']();

                break;

            case file_exists($cachePath) && filemtime($dataPath) <= filemtime($cachePath):
                $states['cache-file-found-and-up-to-date']();

                break;

            case file_exists($cacheDirectory) && is_writable($cacheDirectory):
                $states['cache-file-not-found-or-stale']();

                break;

            default:
                $states['no-caching-capabilities']();

                break;
        }
    }

    /**
     * Direktori cache sqlite per app. Caching tetap opt-in seperti sebelumnya: folder per app hanya dibuat
     * bila folder lama yang dipakai bersama sudah ada dan bisa ditulis.
     *
     * @return string
     */
    protected static function arrayDriverCacheDirectory() {
        $directory = DOCROOT . 'temp' . DS . CTemporary::appFolder('model/array/cache');
        $legacyDirectory = DOCROOT . 'temp' . DS . 'model' . DS . 'array' . DS . 'cache';
        if (!is_dir($directory) && is_dir($legacyDirectory) && is_writable($legacyDirectory)) {
            @mkdir($directory, 0777, true);
        }

        return $directory;
    }

    /**
     * @param string $database
     * @return void
     */
    protected static function setSqliteConnection($database) {
        $config = [
            'pdo' => true,
            'type' => 'sqlite',
            'database' => $database,
        ];

        static::$arrayDriverConnection = CDatabase::connectionFactory()->make($config);
        CDatabase::manager()->addConnection($config, static::class);
    }

    public function migrate() {
        $rows = $this->getRows();
        $tableName = $this->getTable();

        if (count($rows)) {
            $this->createTable($tableName, $rows[0]);
        } else {
            $this->createTableWithNoData($tableName);
        }

        foreach (array_chunk($rows, $this->getArrayDriverInsertChunkSize()) ?? [] as $inserts) {
            if (!empty($inserts)) {
                static::insert($inserts);
            }
        }
    }

    /**
     * @param string $tableName
     * @param array $firstRow
     * @return void
     */
    public function createTable(string $tableName, $firstRow) {
        $this->createTableSafely($tableName, function ($table) use ($firstRow) {
            // Add the "id" column if it doesn't already exist in the rows.
            if ($this->incrementing && !array_key_exists($this->primaryKey, $firstRow)) {
                $table->increments($this->primaryKey);
            }

            foreach ($firstRow as $column => $value) {
                switch (true) {
                    case is_int($value):
                        $type = 'integer';

                        break;
                    case is_numeric($value):
                        $type = 'float';

                        break;
                    case is_string($value):
                        $type = 'string';

                        break;
                    case is_object($value) && $value instanceof \DateTime:
                        $type = 'dateTime';

                        break;
                    default:
                        $type = 'string';
                }

                if ($column === $this->primaryKey && $type == 'integer') {
                    $table->increments($this->primaryKey);

                    continue;
                }

                $schema = $this->getSchema();

                $type = $schema[$column] ?? $type;

                $table->{$type}($column)->nullable();
            }

            if ($this->usesTimestamps() && (!in_array('updated_at', array_keys($firstRow)) || !in_array('created_at', array_keys($firstRow)))) {
                $table->timestamps();
            }
        });
    }

    public function createTableWithNoData(string $tableName) {
        $this->createTableSafely($tableName, function ($table) {
            $schema = $this->getSchema();

            if ($this->incrementing && !in_array($this->primaryKey, array_keys($schema))) {
                $table->increments($this->primaryKey);
            }

            foreach ($schema as $name => $type) {
                if ($name === $this->primaryKey && $type == 'integer') {
                    $table->increments($this->primaryKey);

                    continue;
                }

                $table->{$type}($name)->nullable();
            }

            if ($this->usesTimestamps() && (!in_array('updated_at', array_keys($schema)) || !in_array('created_at', array_keys($schema)))) {
                $table->timestamps();
            }
        });
    }

    protected function createTableSafely(string $tableName, Closure $callback) {
        $connection = static::resolveConnection();

        /** @var \CDatabase_Schema_Builder_SQLiteBuilder $schemaBuilder */
        $schemaBuilder = static::resolveConnection()->getSchemaBuilder();

        try {
            $schemaBuilder->create($tableName, $callback);
        } catch (CDatabase_Exception_QueryException $e) {
            if (cstr::contains($e->getMessage(), 'already exists (SQL: create table')) {
                // This error can happen in rare circumstances due to a race condition.
                // Concurrent requests may both see the necessary preconditions for
                // the table creation, but only one can actually succeed.
                return;
            }

            throw $e;
        }
    }

    public function usesTimestamps() {
        // Override the Laravel default value of $timestamps = true; Unless otherwise set.
        return (new \ReflectionClass($this))->getProperty('timestamps')->class === static::class
            ? parent::usesTimestamps()
            : false;
    }

    public function getArrayDriverInsertChunkSize() {
        return $this->arrayDriverInsertChunkSize ?? 100;
    }

    public function getConnectionName() {
        return static::class;
    }
}
