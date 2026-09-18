<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 4:32:14 PM
 */
trait CTracker_RepositoryManager_PathTrait {
    /**
     * @var CTracker_Repository_Path
     */
    protected $pathRepository;

    protected function bootPathTrait() {
        $this->pathRepository = new CTracker_Repository_Path();
    }

    public function findOrCreatePath($path) {
        return $this->pathRepository->findOrCreate($path, ['path']);
    }
}
