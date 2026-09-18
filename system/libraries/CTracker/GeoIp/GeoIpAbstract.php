<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @since Jun 23, 2019, 5:07:31 PM
 */
abstract class CTracker_GeoIp_GeoIpAbstract implements CTracker_GeoIp_GeoIpInterface {

    protected $enabled = true;
    protected $handle;
    protected $geoIpData;

    /**
     * @return boolean
     */
    public function isEnabled() {
        return $this->enabled;
    }

    /**
     * @param boolean $enabled
     */
    public function setEnabled($enabled) {
        $this->enabled = $enabled;
    }

}
