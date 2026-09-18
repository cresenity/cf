<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 3:16:21 PM
 */
class CTracker_Model_Referer extends CTracker_Model {
    use CModel_Tracker_TrackerRefererTrait;

    protected $table = 'log_referer';

    protected $fillable = [
        'url',
        'host',
        'log_domain_id',
        'medium',
        'source',
        'search_terms_hash',
    ];
}
