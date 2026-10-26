<?php

class CQueue_FailedJob_DatabaseUuidFailedJob extends CQueue_AbstractFailedJob implements CQueue_Contract_PrunableFailedJobInterface, CQueue_Contract_CountableFailedJobInterface {
    /**
     * The database connection name.
     *
     * @var CDatabase_Connection
     */
    protected $database;

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
    public function __construct(CDatabase_Connection $database, $table) {
        $this->table = $table;
        $this->database = $database;
    }

    /**
     * Log a failed job into storage.
     *
     * @param string     $connection
     * @param string     $queue
     * @param string     $payload
     * @param \Throwable $exception
     *
     * @return null|int
     */
    public function log($connection, $queue, $payload, $exception) {
        $this->getTable()->insert([
            'uuid' => $uuid = json_decode($payload, true)['uuid'],
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => (string) $exception,
            'failed_at' => c::now(),
        ]);

        return $uuid;
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return array
     */
    public function all() {
        return $this->getTable()->orderBy('id', 'desc')->get()->map(function ($record) {
            $record->id = $record->uuid;
            unset($record->uuid);

            return $record;
        })->all();
    }

    /**
     * Get a single failed job.
     *
     * @param mixed $id
     *
     * @return null|object
     */
    public function find($id) {
        if ($record = $this->getTable()->where('uuid', $id)->first()) {
            $record->id = $record->uuid;
            unset($record->uuid);
        }

        return $record;
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function forget($id) {
        return $this->getTable()->where('uuid', $id)->delete() > 0;
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

        return $query->orderBy('id', 'desc')->pluck('uuid')->all();
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
     * Prune all of the entries older than the given date.
     *
     * @return int
     */
    public function prune(DateTimeInterface $before) {
        $query = $this->getTable()->where('failed_at', '<', $before);

        $totalDeleted = 0;

        do {
            $deleted = $query->take(1000)->delete();

            $totalDeleted += $deleted;
        } while ($deleted !== 0);

        return $totalDeleted;
    }

    /**
     * Get a new query builder instance for the table.
     *
     * @return \CDatabase_Query_Builder
     */
    protected function getTable() {
        return $this->database->table($this->table);
    }
}
