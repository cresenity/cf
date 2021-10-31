<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Sep 1, 2018, 1:14:07 PM
 */

/**
 * Event used when the portable index definition is generated inside CDatabase_Schema_Manager.
 */
class CDatabase_Event_Schema_OnIndexDefinition extends CDatabase_Event_Schema {
    /**
     * @var CDatabase_Schema_Index|null
     */
    private $index = null;

    /**
     * Raw index data as fetched from the database.
     *
     * @var array
     */
    private $tableIndex;

    /**
     * @var string
     */
    private $table;

    /**
     * @var CDatabase
     */
    private $connection;

    /**
     * @param array     $tableIndex
     * @param string    $table
     * @param CDatabase $connection
     */
    public function __construct(array $tableIndex, $table, CDatabase $connection) {
        $this->tableIndex = $tableIndex;
        $this->table = $table;
        $this->connection = $connection;
    }

    /**
     * Allows to clear the index which means the index will be excluded from tables index list.
     *
     * @param null|CDatabase_Schema_Index $index
     *
     * @return CDatabase_Event_Schema_OnIndexDefinition
     */
    public function setIndex(CDatabase_Schema_Index $index = null) {
        $this->index = $index;
        return $this;
    }

    /**
     * @return CDatabase_Schema_Index|null
     */
    public function getIndex() {
        return $this->index;
    }

    /**
     * @return array
     */
    public function getTableIndex() {
        return $this->tableIndex;
    }

    /**
     * @return string
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * @return CDatabase
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * @return CDatabase_Platform
     */
    public function getDatabasePlatform() {
        return $this->connection->getDatabasePlatform();
    }
}
