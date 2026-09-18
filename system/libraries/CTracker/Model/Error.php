<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jul 20, 2019, 11:10:36 PM
 */
class CTracker_Model_Error extends CTracker_Model {
    use CModel_Tracker_TrackerErrorTrait;

    protected $table = 'log_error';

    protected $fillable = [
        'message',
        'code',
    ];
}
