<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 5:08:03 PM
 */
interface CTracker_GeoIp_GeoIpInterface {
    public function searchAddr($addr);
}
