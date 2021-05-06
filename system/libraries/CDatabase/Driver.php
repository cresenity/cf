<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Database API driver
 */
abstract class CDatabase_Driver {
    protected $query_cache;

    /**
     * @var CDatabase
     */
    protected $db;

    /**
     * Connect to our database.
     * Returns FALSE on failure or a MySQL resource.
     *
     * @return mixed
     */
    abstract public function connect();

    /**
     * Perform a query based on a manually written query.
     *
     * @param string $sql SQL query to execute
     *
     * @return CDatabase_Result
     */
    abstract public function query($sql);

    /**
     * Closing connection.
     *
     * @return void
     */
    abstract public function close();

    /**
     * Builds a DELETE query.
     *
     * @param string $table table name
     * @param array  $where where clause
     *
     * @return string
     */
    public function delete($table, $where) {
        return 'DELETE FROM ' . $this->escape_table($table) . ' WHERE ' . implode(' ', $where);
    }

    /**
     * Builds an UPDATE query.
     *
     * @param string $table  table name
     * @param array  $values key => value pairs
     * @param array  $where  where clause
     *
     * @return string
     */
    public function update($table, $values, $where) {
        foreach ($values as $key => $val) {
            $valstr[] = $this->escape_column($key) . ' = ' . $val;
        }
        return 'UPDATE ' . $this->escape_table($table) . ' SET ' . implode(', ', $valstr) . ' WHERE ' . implode(' ', $where);
    }

    /**
     * Set the charset using 'SET NAMES <charset>'.
     *
     * @param string $charset character set to use
     */
    public function set_charset($charset) {
        throw new CDatabase_Exception('The method you called, :method, is not supported by this driver', [':method', __FUNCTION__]);
    }

    /**
     * Wrap the tablename in backticks, has support for: table.field syntax.
     *
     * @param string $table table name
     *
     * @return string
     */
    abstract public function escape_table($table);

    /**
     * Escape a column/field name, has support for special commands.
     *
     * @param string $column column name
     *
     * @return string
     */
    abstract public function escape_column($column);

    /**
     * Builds a WHERE portion of a query.
     *
     * @param   mixed    key
     * @param   string   value
     * @param   string   type
     * @param   int      number of where clauses
     * @param   bool  escape the value
     * @param mixed $key
     * @param mixed $value
     * @param mixed $type
     * @param mixed $num_wheres
     * @param mixed $quote
     *
     * @return string
     */
    public function where($key, $value, $type, $num_wheres, $quote) {
        $prefix = ($num_wheres == 0) ? '' : $type;

        if ($quote === -1) {
            $value = '';
        } else {
            if ($value === null) {
                if (!$this->has_operator($key)) {
                    $key .= ' IS';
                }

                $value = ' NULL';
            } elseif (is_bool($value)) {
                if (!$this->has_operator($key)) {
                    $key .= ' =';
                }

                $value = ($value == true) ? ' 1' : ' 0';
            } else {
                if (!$this->has_operator($key) and !empty($key)) {
                    $key = $this->escape_column($key) . ' =';
                } else {
                    preg_match('/^(.+?)([<>!=]+|\bIS(?:\s+NULL))\s*$/i', $key, $matches);
                    if (isset($matches[1]) and isset($matches[2])) {
                        $key = $this->escape_column(trim($matches[1])) . ' ' . trim($matches[2]);
                    }
                }

                $value = ' ' . (($quote == true) ? $this->escape($value) : $value);
            }
        }

        return $prefix . $key . $value;
    }

    /**
     * Builds a LIKE portion of a query.
     *
     * @param   mixed    field name
     * @param   string   value to match with field
     * @param   bool  add wildcards before and after the match
     * @param   string   clause type (AND or OR)
     * @param   int      number of likes
     * @param mixed $field
     * @param mixed $match
     * @param mixed $auto
     * @param mixed $type
     * @param mixed $num_likes
     *
     * @return string
     */
    public function like($field, $match, $auto, $type, $num_likes) {
        $prefix = ($num_likes == 0) ? '' : $type;

        $match = $this->escape_str($match);

        if ($auto === true) {
            // Add the start and end quotes
            $match = '%' . str_replace('%', '\\%', $match) . '%';
        }

        return $prefix . ' ' . $this->escape_column($field) . ' LIKE \'' . $match . '\'';
    }

    /**
     * Builds a NOT LIKE portion of a query.
     *
     * @param   mixed   field name
     * @param   string  value to match with field
     * @param   string  clause type (AND or OR)
     * @param   int     number of likes
     * @param mixed $field
     * @param mixed $match
     * @param mixed $auto
     * @param mixed $type
     * @param mixed $num_likes
     *
     * @return string
     */
    public function notlike($field, $match, $auto, $type, $num_likes) {
        $prefix = ($num_likes == 0) ? '' : $type;

        $match = $this->escape_str($match);

        if ($auto === true) {
            // Add the start and end quotes
            $match = '%' . $match . '%';
        }

        return $prefix . ' ' . $this->escape_column($field) . ' NOT LIKE \'' . $match . '\'';
    }

    /**
     * Builds a REGEX portion of a query.
     *
     * @param   string   field name
     * @param   string   value to match with field
     * @param   string   clause type (AND or OR)
     * @param   int  number of regexes
     * @param mixed $field
     * @param mixed $match
     * @param mixed $type
     * @param mixed $num_regexs
     *
     * @return string
     */
    public function regex($field, $match, $type, $num_regexs) {
        throw new CDatabase_Exception('The method you called, :method, is not supported by this driver', [':method', __FUNCTION__]);
    }

    /**
     * Builds a NOT REGEX portion of a query.
     *
     * @param   string   field name
     * @param   string   value to match with field
     * @param   string   clause type (AND or OR)
     * @param   int  number of regexes
     * @param mixed $field
     * @param mixed $match
     * @param mixed $type
     * @param mixed $num_regexs
     *
     * @return string
     */
    public function notregex($field, $match, $type, $num_regexs) {
        throw new CDatabase_Exception('The method you called, :method, is not supported by this driver', [':method', __FUNCTION__]);
    }

    /**
     * Builds an INSERT query.
     *
     * @param   string  table name
     * @param   array   keys
     * @param   array   values
     * @param mixed $table
     * @param mixed $keys
     * @param mixed $values
     *
     * @return string
     */
    public function insert($table, $keys, $values) {
        // Escape the column names
        foreach ($keys as $key => $value) {
            $keys[$key] = $this->escape_column($value);
        }
        return 'INSERT INTO ' . $this->escape_table($table) . ' (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
    }

    /**
     * Builds a MERGE portion of a query.
     *
     * @param   string  table name
     * @param   array   keys
     * @param   array   values
     * @param mixed $table
     * @param mixed $keys
     * @param mixed $values
     *
     * @return string
     */
    public function merge($table, $keys, $values) {
        throw new CDatabase_Exception('The method you called, :method, is not supported by this driver', [':method', __FUNCTION__]);
    }

    /**
     * Builds a LIMIT portion of a query.
     *
     * @param   int  limit
     * @param   int  offset
     * @param mixed $limit
     * @param mixed $offset
     *
     * @return string
     */
    abstract public function limit($limit, $offset = 0);

    /**
     * Creates a prepared statement.
     *
     * @param string $sql SQL query
     *
     * @return CDatabase_Stmt
     */
    public function stmt_prepare($sql = '') {
        throw new CDatabase_Exception('The method you called, :method, is not supported by this driver', [':method', __FUNCTION__]);
    }

    /**
     *  Compiles the SELECT statement.
     *  Generates a query string based on which functions were used.
     *  Should not be called directly, the get() function calls it.
     *
     * @param   array   select query values
     * @param mixed $database
     *
     * @return string
     */
    abstract public function compile_select($database);

    /**
     * Determines if the string has an arithmetic operator in it.
     *
     * @param string $str string to check
     *
     * @return bool
     */
    public function has_operator($str) {
        return (bool) preg_match('/[<>!=]|\sIS(?:\s+NOT\s+)?\b|BETWEEN/i', trim($str));
    }

    /**
     * Escapes any input value.
     *
     * @param   mixed   value to escape
     * @param mixed $value
     *
     * @return string
     */
    public function escape($value) {
        if (!$this->dbConfig['escape']) {
            return $value;
        }

        switch (gettype($value)) {
            case 'string':
                $value = '\'' . $this->escape_str($value) . '\'';
                break;
            case 'boolean':
                $value = (int) $value;
                break;
            case 'double':
                // Convert to non-locale aware float to prevent possible commas
                $value = sprintf('%F', $value);
                break;
            default:
                $value = ($value === null) ? 'NULL' : $value;
                break;
        }

        return (string) $value;
    }

    /**
     * Escapes a string for a query.
     *
     * @param mixed $str value to escape
     *
     * @return string
     */
    abstract public function escape_str($str);

    /**
     * Lists all tables in the database.
     *
     * @return array
     */
    abstract public function list_tables();

    /**
     * Lists all fields in a table.
     *
     * @param string $table table name
     *
     * @return array
     */
    abstract public function list_fields($table);

    /**
     * Returns the last database error.
     *
     * @return string
     */
    abstract public function show_error();

    /**
     * Returns field data about a table.
     *
     * @param string $table table name
     *
     * @return array
     */
    abstract public function field_data($table);

    /**
     * Fetches SQL type information about a field, in a generic format.
     *
     * @param   string  field datatype
     * @param mixed $str
     *
     * @return array
     */
    protected function sql_type($str) {
        static $sql_types;

        if ($sql_types === null) {
            // Load SQL data types
            $sql_types = CF::config('sql_types');
        }

        $str = strtolower(trim($str));

        if (($open = strpos($str, '(')) !== false) {
            // Find closing bracket
            $close = strpos($str, ')', $open) - 1;

            // Find the type without the size
            $type = substr($str, 0, $open);
        } else {
            // No length
            $type = $str;
        }

        empty($sql_types[$type]) and exit('Unknown field type: ' . $type);

        // Fetch the field definition
        $field = $sql_types[$type];

        switch ($field['type']) {
            case 'string':
            case 'float':
                if (isset($close)) {
                    // Add the length to the field info
                    $field['length'] = substr($str, $open + 1, $close - $open);
                }
                break;
            case 'int':
                // Add unsigned value
                $field['unsigned'] = (strpos($str, 'unsigned') !== false);
                break;
        }

        return $field;
    }

    /**
     * Clears the internal query cache.
     *
     * @param  string  SQL query
     * @param null|mixed $sql
     */
    public function clear_cache($sql = null) {
        if (empty($sql)) {
            $this->query_cache = [];
        } else {
            unset($this->query_cache[$this->query_hash($sql)]);
        }

        CF::log('debug', 'Database cache cleared: ' . get_class($this));
    }

    /**
     * Creates a hash for an SQL query string. Replaces newlines with spaces,
     * trims, and hashes.
     *
     * @param string $sql SQL query
     *
     * @return string
     */
    protected function query_hash($sql) {
        return sha1(str_replace("\n", ' ', trim($sql)));
    }

    public function beginTransaction() {
        $this->query('START TRANSACTION;');
    }

    public function rollback() {
        $this->query('ROLLBACK;');
    }

    public function commit() {
        $this->query('COMMIT;');
    }

    /**
     * @return CDatabase
     */
    public function db() {
        return $this->db;
    }
}

// End Database Driver Interface
