<?php

class CDaemon_Supervisor_Queue_DatabaseQueue extends CQueue_Queue_DatabaseQueue {
    /**
     * Get the number of queue jobs that are ready to process.
     *
     * @param null|string $queue
     *
     * @return int
     */
    public function readyNow($queue = null) {
        $query = $this->database->table($this->table)
            ->where('name', $this->getQueue($queue));

        $this->isAvailable($query);

        return $query->count();
    }
}
