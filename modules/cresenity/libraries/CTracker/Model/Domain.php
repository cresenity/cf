<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 23, 2019, 3:16:24 PM
 */
class CTracker_Model_Domain extends CTracker_Model {
    use CModel_Tracker_TrackerDomainTrait;

    protected $table = 'log_domain';

    protected $fillable = [
        'name',
    ];
}
