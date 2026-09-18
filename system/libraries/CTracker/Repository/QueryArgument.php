<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 9:59:35 PM
 */
class CTracker_Repository_QueryArgument extends CTracker_AbstractRepository {
    public function __construct() {
        $this->className = CTracker::config()->get('queryArgumentModel', CTracker_Model_QueryArgument::class);
        $this->createModel();

        parent::__construct();
    }
}
