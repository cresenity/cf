<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 3:10:34 PM
 */
class CTracker_Model_Agent extends CTracker_Model {
    use CModel_Tracker_TrackerAgentTrait;

    protected $table = 'log_agent';

    protected $fillable = [
        'name',
        'browser',
        'browser_version',
        'name_hash',
    ];
}
