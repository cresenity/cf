<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 5:43:14 AM
 */
trait CTracker_RepositoryManager_GeoIpTrait {
    /**
     * @var CTracker_Repository_GeoIp
     */
    protected $geoIpRepository;

    /**
     * @var CTracker_GeoIp
     */
    protected $geoIp;

    protected function bootGeoIpTrait() {
        $this->geoIpRepository = new CTracker_Repository_GeoIp();
        $this->geoIp = new CTracker_GeoIp();
    }

    public function getGeoIpId($clientIp) {
        if (strpos($clientIp, ',') !== false) {
            $clientIp = trim(carr::get(explode(',', $clientIp), 0));
        }

        $id = null;
        if ($geoIpData = $this->geoIp->searchAddr($clientIp)) {
            $id = $this->geoIpRepository->findOrCreate(
                $geoIpData,
                ['latitude', 'longitude']
            );
        }
        return $id;
    }
}
