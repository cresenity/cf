<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 23, 2019, 11:55:49 PM
 */
class CTracker_Model_Route extends CTracker_Model {
    use CModel_Tracker_TrackerRouteTrait;

    protected $table = 'log_route';

    protected $fillable = [
        'name',
        'action',
    ];
}
