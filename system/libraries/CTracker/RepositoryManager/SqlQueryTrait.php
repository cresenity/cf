<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 10:31:29 PM
 */
trait CTracker_RepositoryManager_SqlQueryTrait {
    /**
     * @var CTracker_Repository_SqlQuery
     */
    protected $sqlQueryRepository;

    protected function bootSqlQueryTrait() {
        $this->sqlQueryRepository = new CTracker_Repository_SqlQuery();
    }

    public function logSqlQuery($query, $bindings, $time, $name) {
        $this->sqlQueryRepository->push([
            'query' => $query,
            'bindings' => $bindings,
            'time' => $time,
            'name' => $name,
        ]);
    }
}
