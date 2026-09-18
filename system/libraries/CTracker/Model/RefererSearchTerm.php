<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 9:39:37 PM
 */
class CTracker_Model_RefererSearchTerm extends CTracker_Model {
    use CModel_Tracker_TrackerRefererSearchTermTrait;

    protected $table = 'log_referer_search_term';

    protected $fillable = [
        'referer_id',
        'search_term',
    ];
}
