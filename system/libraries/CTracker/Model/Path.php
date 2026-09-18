<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 4:33:46 PM
 */
class CTracker_Model_Path extends CTracker_Model {
    use CModel_Tracker_TrackerPathTrait;

    protected $table = 'log_path';

    protected $fillable = [
        'path',
    ];
}
