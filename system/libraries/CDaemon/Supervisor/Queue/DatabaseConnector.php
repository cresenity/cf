<?php

class CDaemon_Supervisor_Queue_DatabaseConnector extends CQueue_Connector_DatabaseConnector {
    /**
     * Establish a queue connection.
     *
     * @param array $config
     *
     * @return \CDaemon_Supervisor_Queue_DatabaseQueue
     */
    public function connect(array $config) {
        $isLegacy = is_array(CF::config('database.default'));
        $connection = $isLegacy ? c::db() : $this->connections->connection(carr::get($config, 'connection'));

        return new CDaemon_Supervisor_Queue_DatabaseQueue(
            $connection,
            carr::get($config, 'table'),
            carr::get($config, 'queue'),
            carr::get($config, 'retry_after', 60)
        );
    }
}
