<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 10:27:25 PM
 */
trait CTracker_RepositoryManager_LogTrait {
    /**
     * @var CTracker_Repository_Log
     */
    protected $logRepository;

    protected function bootLogTrait() {
        $this->logRepository = new CTracker_Repository_Log();
    }

    public function createLog($data) {
        $this->logRepository->createLog($data);
        $this->sqlQueryRepository->fire();
    }
}
