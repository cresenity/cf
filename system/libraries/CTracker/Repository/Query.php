<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 9:49:19 PM
 */
class CTracker_Repository_Query extends CTracker_AbstractRepository {
    public function __construct() {
        $this->className = CTracker::config()->get('queryModel', CTracker_Model_Query::class);
        $this->createModel();

        parent::__construct();
    }
}
