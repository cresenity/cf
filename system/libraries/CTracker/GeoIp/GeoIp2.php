<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 23, 2019, 5:06:17 PM
 */
use GeoIp2\Database\Reader as GeoIpReader;
use GeoIp2\Exception\AddressNotFoundException;

class CTracker_GeoIp_GeoIp2 extends CTracker_GeoIp_GeoIpAbstract {
    const DATABASE_FILE_NAME = 'GeoLite2-City.mmdb';

    private $reader;

    public function __construct($databasePath = null) {
        $this->reader = new GeoIpReader($this->getGeoliteFileName($databasePath));
    }

    public function searchAddr($addr) {
        if (!$this->isEnabled()) {
            return;
        }
        if ($this->geoIpData = $this->getCity($addr)) {
            return $this->renderData();
        }

        return null;
    }

    /**
     * Get the GeoIp database file name and path.
     *
     * @param null $databasePath
     *
     * @return string
     */
    private function getGeoliteFileName($databasePath = null) {
        return ($databasePath ?: __DIR__) . DIRECTORY_SEPARATOR . static::DATABASE_FILE_NAME;
    }

    private function renderData() {
        return [
            'latitude' => $this->geoIpData->location->latitude,
            'longitude' => $this->geoIpData->location->longitude,
            'country_code' => $this->geoIpData->country->isoCode,
            'country_code3' => null,
            'country_name' => $this->geoIpData->country->name,
            'region' => $this->geoIpData->continent->code,
            'city' => $this->geoIpData->city->name,
            'postal_code' => $this->geoIpData->postal->code,
            'area_code' => null,
            'dma_code' => null,
            'metro_code' => $this->geoIpData->location->metroCode,
            'continent_code' => $this->geoIpData->continent->code,
        ];
    }

    /**
     * @param $addr
     *
     * @return \GeoIp2\Model\City
     */
    private function getCity($addr) {
        try {
            $city = $this->reader->city($addr);
        } catch (AddressNotFoundException $e) {
            $city = null;
        }

        return $city;
    }
}
