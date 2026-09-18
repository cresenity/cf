<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 4:33:13 PM
 */
class CTracker_Repository_Path extends CTracker_AbstractRepository {
    public function __construct() {
        $this->className = CTracker::config()->get('pathModel', CTracker_Model_Path::class);
        $this->createModel();

        parent::__construct();
    }
}
