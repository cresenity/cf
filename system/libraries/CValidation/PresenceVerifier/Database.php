<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since Apr 14, 2019, 10:25:47 AM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
class CValidation_PresenceVerifier_Database implements CValidation_PresenceVerifierInterface {

    /**
     * The database connection instance.
     *
     * @var CDatabase
     */
    protected $db;

    /**
     * Create a new database presence verifier.
     *
     * @param  CDatabase  $db
     * @return void
     */
    public function __construct(CDatabase $db) {
        $this->db = $db;
    }

    /**
     * Count the number of objects in a collection having the given value.
     *
     * @param  string  $collection
     * @param  string  $column
     * @param  string  $value
     * @param  int|null  $excludeId
     * @param  string|null  $idColumn
     * @param  array  $extra
     * @return int
     */
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = []) {
        $query = $this->table($collection)->where($column, '=', $value);

        if (!is_null($excludeId) && $excludeId !== 'NULL') {
            $query->where($idColumn ?: 'id', '<>', $excludeId);
        }

        return $this->addConditions($query, $extra)->count();
    }

    /**
     * Count the number of objects in a collection with the given values.
     *
     * @param  string  $collection
     * @param  string  $column
     * @param  array   $values
     * @param  array   $extra
     * @return int
     */
    public function getMultiCount($collection, $column, array $values, array $extra = []) {
        $query = $this->table($collection)->whereIn($column, $values);

        return $this->addConditions($query, $extra)->count();
    }

    /**
     * Add the given conditions to the query.
     *
     * @param  CDatabase_Query_Builder  $query
     * @param  array  $conditions
     * @return CDatabase_Query_Builder
     */
    protected function addConditions($query, $conditions) {
        foreach ($conditions as $key => $value) {
            if ($value instanceof Closure) {
                $query->where(function ($query) use ($value) {
                    $value($query);
                });
            } else {
                $this->addWhere($query, $key, $value);
            }
        }

        return $query;
    }

    /**
     * Add a "where" clause to the given query.
     *
     * @param  CDatabase_Query_Builder  $query
     * @param  string  $key
     * @param  string  $extraValue
     * @return void
     */
    protected function addWhere($query, $key, $extraValue) {
        if ($extraValue === 'NULL') {
            $query->whereNull($key);
        } elseif ($extraValue === 'NOT_NULL') {
            $query->whereNotNull($key);
        } elseif (cstr::startsWith($extraValue, '!')) {
            $query->where($key, '!=', mb_substr($extraValue, 1));
        } else {
            $query->where($key, $extraValue);
        }
    }

    /**
     * Get a query builder for the given table.
     *
     * @param  string  $table
     * @return CDatabase_Query_Builder
     */
    protected function table($table) {
        return $this->db->table($table);
    }

    /**
     * Set the connection to be used.
     *
     * @param  string  $connection
     * @return void
     */
    public function setConnection($connection) {
        $this->connection = $connection;
    }

}
