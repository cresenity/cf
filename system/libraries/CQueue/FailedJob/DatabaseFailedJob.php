<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Nov 4, 2019, 5:14:15 PM
 */
class CQueue_FailedJob_DatabaseFailedJob extends CQueue_AbstractFailedJob {
    /**
     * The current connection.
     *
     * @var CDatabase_Connection
     */
    protected $db;

    /**
     * The database table.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new database failed job provider.
     *
     * @param CDatabase_Connection $database
     * @param string               $table
     *
     * @return void
     */
    public function __construct(CDatabase_Connection $db, $table) {
        $this->table = $table;
        $this->db = $db;
    }

    /**
     * Log a failed job into storage.
     *
     * @param string     $connection
     * @param string     $queue
     * @param string     $payload
     * @param \Exception $exception
     *
     * @return null|int
     */
    public function log($connection, $queue, $payload, $exception) {
        $failed_at = c::now();
        $exception = (string) $exception;

        return $this->getTable()->insertGetId(compact(
            'connection',
            'queue',
            'payload',
            'exception',
            'failed_at'
        ));
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return array
     */
    public function all() {
        return $this->getTable()->orderBy($this->table . '_id', 'desc')->get()->all();
    }

    /**
     * Get a single failed job.
     *
     * @param mixed $id
     *
     * @return null|object
     */
    public function find($id) {
        return $this->getTable()->find($id);
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function forget($id) {
        return $this->getTable()->where('id', $id)->delete() > 0;
    }

    /**
     * Flush all of the failed jobs from storage.
     *
     * @return void
     */
    public function flush() {
        $this->getTable()->delete();
    }

    /**
     * Get a new query builder instance for the table.
     *
     * @return CDatabase_Query_Builder
     */
    protected function getTable() {
        return $this->db->table($this->table);
    }
}
