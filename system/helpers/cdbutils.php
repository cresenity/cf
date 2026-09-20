<?php

//@codingStandardsIgnoreStart
class cdbutils {
    public static function table_exists($table, $db = null) {
        if ($db == null) {
            $db = c::db();
        }
        $q = 'SHOW TABLES LIKE ' . $db->escape($table) . '';

        return cdbutils::get_value($q, $db);
    }

    public static function empty_table($table, $db = null) {
        if ($db == null) {
            $db = c::db();
        }
        $db->query('delete from `' . $table . '`');
        $db->query('alter table `' . $table . '` AUTO_INCREMENT = 1;');
    }

    public static function get_row_count_from_base_query($query, $db = null) {
        if ($db == null) {
            $db = c::db();
        }
        $qtotal = '';
        $qtotal .= 'select count(*) as cnt from (' . $query . ') as a';
        $cnt = 0;
        $r = $db->query($qtotal);
        if ($r->count() > 0) {
            $cnt = $r[0]->cnt;
        }

        return $cnt;
    }

    /**
     * @param string               $query
     * @param array                $where
     * @param CDatabase_Connection $db
     * @param mixed                $table
     *
     * @deprecated 1.6 dont use this anymore
     *
     * @return mixed
     */
    public static function row_exists($table, $where = [], $db = null) {
        CF::deprecated(__METHOD__, null, '1.6', null, true);
        if ($db == null) {
            $db = c::db();
        }
        $q = 'select count(1) as cnt from ' . $db->escapeTable($table) . ' where 1=1 ';
        foreach ($where as $k => $v) {
            $q .= ' and ' . $db->escapeColumn($k) . '=' . $db->escape($v);
        }
        $cnt = cdbutils::get_value($q, $db);

        return $cnt > 0;
    }

    /**
     * @param string               $query
     * @param CDatabase_Connection $db
     *
     * @deprecated 1.6 use c::db()->getValue
     *
     * @return mixed
     */
    public static function get_value($query, $db = null) {
        CF::deprecated(__METHOD__, 'c::db()->getValue', '1.6', null, true);
        if ($db == null) {
            $db = c::db();
        }
        $result = $db->query($query);

        $value = null;
        foreach ($result as $row) {
            foreach ($row as $v) {
                $value = $v;

                break;
            }

            break;
        }

        return $value;
    }

    /**
     * @param string               $query
     * @param CDatabase_Connection $db
     *
     * @deprecated 1.6 use c::db()->getRow
     *
     * @return object
     */
    public static function get_row($query, $db = null) {
        CF::deprecated(__METHOD__, 'c::db()->getRow', '1.6', null, true);
        if ($db == null) {
            $db = c::db();
        }
        $r = $db->select($query);
        if (is_array($r) && count($r) > 0) {
            return $r[0];
        }

        return null;
    }

    /**
     * @param string               $query
     * @param CDatabase_Connection $db
     *
     * @deprecated 1.6 use c::db()->getArray
     *
     * @return array
     */
    public static function get_array($query, $db = null) {
        CF::deprecated(__METHOD__, 'c::db()->getArray', '1.6', null, true);
        if ($db == null) {
            $db = c::db();
        }
        /** @var CDatabase_Connection $db */
        $result = $db->select($query);

        $res = [];
        foreach ($result as $row) {
            $cnt = 0;
            $arr_val = '';
            foreach ($row as $k => $v) {
                if ($cnt == 0) {
                    $arr_val = $v;
                }
                $cnt++;
                if ($cnt > 0) {
                    break;
                }
            }
            $res[] = $arr_val;
        }

        return $res;
    }

    /**
     * @param string               $query
     * @param CDatabase_Connection $db
     *
     * @deprecated 1.6 use c::db()->getList
     *
     * @return array
     */
    public static function get_list($query, $db = null) {
        CF::deprecated(__METHOD__, 'c::db()->getList', '1.6', null, true);
        if ($db == null) {
            $db = c::db();
        }
        $r = $db->query($query);
        $result = $r->result(false);
        $res = [];
        foreach ($result as $row) {
            $cnt = 0;
            $arr_key = '';
            $arr_val = '';
            foreach ($row as $k => $v) {
                if ($cnt == 0) {
                    $arr_key = $v;
                }
                if ($cnt == 1) {
                    $arr_val = $v;
                }
                $cnt++;
                if ($cnt > 1) {
                    break;
                }
            }
            $res[$arr_key] = $arr_val;
        }

        return $res;
    }

    public static function get_table_list($db = null) {
        if ($db == null) {
            $db = c::db();
        }
        $tables = [];
        $array = cdbutils::get_array('SHOW TABLES');

        foreach ($array as $arr) {
            $tables[$arr] = $arr;
        }

        return $tables;
    }

    public function parse_column_type($str) {
        $result = [];
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
        $result['datatype'] = $type;
        $result['unsigned'] = false;
        $result['notnull'] = false;
        if ((strpos($str, 'unsigned')) !== false) {
            $result['unsigned'] = true;
        }
    }

    public static function get_table_count($db = null) {
        if ($db == null) {
            $db = c::db();
        }
        $q = 'SHOW table status';

        $res = cdbutils::get_array($q);

        return count($res);
    }

    public static function get_table_info($db = null) {
        if ($db == null) {
            $db = c::db();
        }
        $q = 'SHOW table status';

        $res = $db->query($q);
        $r = $res->result_array(true);
        $result = [];
        foreach ($r as $row) {
            // Make an associative array
            $engine = strtolower($row->Engine);
            $version = strtolower($row->Version);
            $row_format = strtolower($row->Row_format);
            $collation = strtolower($row->Collation);
            $charset = 'utf8';
            if (strlen($collation) > 0) {
                $collation_array = explode('_', $collation);
                if (count($collation_array) > 0) {
                    $charset = $collation_array[0];
                }
            }
            $table_info = [];

            $table_info['engine'] = $engine;
            $table_info['version'] = $version;
            $table_info['row_format'] = $row_format;
            $table_info['charset'] = $charset;
            $table_info['collation'] = $collation;

            $result[$row->Name] = $table_info;
        }

        return $result;
    }

    public static function get_column_info($table, $db = null) {
        if ($db == null) {
            $db = c::db();
        }
        $q = 'SHOW FULL COLUMNS FROM ' . $db->escape_table($table);

        $res = $db->query($q);
        $r = $res->result_array(true);
        $result = [];
        foreach ($r as $row) {
            // Make an associative array
            $str_type = $row->Type;
            $comment = $row->Comment;

            $field_info = [];
            $str = strtolower(trim($str_type));

            if (($open = strpos($str, '(')) !== false) {
                // Find closing bracket
                $close = strpos($str, ')', $open) - 1;
                // Find the type without the size
                $type = substr($str, 0, $open);
            } else {
                // No length
                $type = $str;
            }
            $field_info['datatype'] = $type;
            $field_info['default'] = null;
            $field_info['comment'] = $comment;
            $field_info['collation'] = null;
            $field_info['charset'] = null;

            $field_info['unsigned'] = false;
            $field_info['notnull'] = false;
            $field_info['primary_key'] = false;
            $field_info['auto_increment'] = false;
            $field_info['length'] = '';

            if ($row->Collation !== '(NULL)') {
                $collation = $row->Collation;
                $field_info['collation'] = $collation;

                $charset = 'utf8';
                if (strlen($collation) > 0) {
                    $collation_array = explode('_', $collation);
                    if (count($collation_array) > 0) {
                        $charset = $collation_array[0];
                    }
                }
                $field_info['charset'] = $charset;
            }
            if ($row->Default !== '(NULL)') {
                $field_info['default'] = $row->Default;
            }
            if ((strpos($str, '(')) !== false) {
                // Add the length to the field info
                $field_info['length'] = substr($str, $open + 1, $close - $open);
            }
            if ((strpos($str, 'unsigned')) !== false) {
                $field_info['unsigned'] = true;
            }
            if ($row->Extra === 'auto_increment') {
                // For sequenced (AUTO_INCREMENT) tables
                $field_info['auto_increment'] = true;
            }
            if ($row->Key === 'PRI') {
                // For sequenced (AUTO_INCREMENT) tables
                $field_info['primary_key'] = true;
            }
            if ($row->Null === 'YES') {
                // Set NULL status
                $field_info['notnull'] = false;
            }
            $result[$row->Field] = $field_info;
        }

        return $result;
    }

    public static function convert_table_engine($engine = 'InnoDB', $db = null) {
        if ($db == null) {
            $db = c::db();
        }
        $tables = cdbutils::get_array('show tables');
        foreach ($tables as $table) {
            $db->query('alter table `' . $table . '` ENGINE=' . $engine);
        }
    }

    public static function convert_table_charset($charset = 'utf8', $collate = 'utf8_unicode_ci', $db = null) {
        if ($db == null) {
            $db = c::db();
        }
        $tables = cdbutils::get_array('show tables');
        foreach ($tables as $table) {
            $db->query('alter table `' . $table . '` CONVERT TO CHARACTER SET ' . $charset . ' COLLATE ' . $collate . ';');
        }
    }

    public static function load_sql($sql) {
        $db = c::db();
        //$sql = explode("\n", $sql);
        $sql = preg_split('/\r\n|\r|\n/', $sql);

        $buffer = '';
        foreach ($sql as $line) {
            $buffer .= $line;
            if (preg_match('/;$/', $line)) {
                $db->query($buffer);
                $buffer = '';
            }
        }
    }
}
