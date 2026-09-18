<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 10:51:07 PM
 */
class CTracker_Model_Connection extends CTracker_Model {
    use CModel_Tracker_TrackerConnectionTrait;

    protected $table = 'log_connection';

    protected $fillable = [
        'name',
    ];
}
