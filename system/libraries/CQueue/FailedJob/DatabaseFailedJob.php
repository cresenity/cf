<?php

defined('SYSPATH') or die('No direct access allowed.');

class CQueue_FailedJob_DatabaseFailedJob extends CQueue_AbstractFailedJob implements CQueue_Contract_PrunableFailedJobInterface, CQueue_Contract_CountableFailedJobInterface {
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
     * @param string $table
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
        return $this->getTable()->where($this->table . '_id', $id)->delete() > 0;
    }

    /**
     * Get the IDs of all of the failed jobs.
     *
     * @param null|string $queue
     *
     * @return array
     */
    public function ids($queue = null) {
        $query = $this->getTable();
        if (!is_null($queue)) {
            $query->where('queue', $queue);
        }

        return $query->orderBy($this->table . '_id', 'desc')->pluck($this->table . '_id')->all();
    }

    /**
     * Get the ID of the failed job whose payload has the given uuid (Batch::$failedJobIds).
     *
     * @param string $uuid
     *
     * @return null|int
     */
    public function findIdByUuid($uuid) {
        return $this->getTable()->where('payload', 'like', '%"uuid":"' . $uuid . '"%')->value($this->table . '_id');
    }

    /**
     * Flush all of the failed jobs from storage.
     *
     * @param null|int $hours only the jobs that failed more than this many hours ago
     *
     * @return void
     */
    public function flush($hours = null) {
        $query = $this->getTable();
        if ($hours) {
            $query->where('failed_at', '<=', c::now()->subHours($hours));
        }
        $query->delete();
    }

    /**
     * Prune all of the entries older than the given date.
     *
     * @return int
     */
    public function prune(DateTimeInterface $before) {
        $key = $this->table . '_id';
        $totalDeleted = 0;
        //per 1000 baris lewat id, DELETE ... LIMIT tidak didukung semua driver
        do {
            $ids = $this->getTable()->where('failed_at', '<', $before)->take(1000)->pluck($key)->all();
            $deleted = $ids ? $this->getTable()->whereIn($key, $ids)->delete() : 0;
            $totalDeleted += $deleted;
        } while ($deleted > 0);

        return $totalDeleted;
    }

    /**
     * Count the failed jobs.
     *
     * @param null|string $connection
     * @param null|string $queue
     *
     * @return int
     */
    public function count($connection = null, $queue = null) {
        $query = $this->getTable();
        if (!is_null($connection)) {
            $query->where('connection', $connection);
        }
        if (!is_null($queue)) {
            $query->where('queue', $queue);
        }

        return $query->count();
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
