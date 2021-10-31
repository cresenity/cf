<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 23, 2019, 9:51:55 PM
 */
class CTracker_Model_Query extends CTracker_Model {
    use CModel_Tracker_TrackerQueryTrait;

    protected $table = 'log_query';

    protected $fillable = [
        'query',
    ];
}
