<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 5:46:39 PM
 */
class CTracker_Model_Log extends CTracker_Model {
    use CModel_Tracker_TrackerLogTrait;

    protected $table = 'log_log';

    protected $fillable = [
        'log_session_id',
        'method',
        'log_path_id',
        'log_query_id',
        'log_route_path_id',
        'log_referer_id',
        'is_ajax',
        'is_secure',
        'is_json',
        'wants_json',
        'log_error_id',
    ];
}
